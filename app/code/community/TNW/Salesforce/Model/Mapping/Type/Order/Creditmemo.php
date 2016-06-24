<?php

class TNW_Salesforce_Model_Mapping_Type_Order_Creditmemo extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Credit Memo';

    /**
     * @param $_entity Mage_Sales_Model_Order_Creditmemo
     * @return string
     */
    public function getValue($_entity)
    {
        $attribute = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attribute) {
            case 'cart_all':
                return $this->convertCartAll($_entity);

            case 'number':
                return $this->convertNumber($_entity);

            case 'website':
                return $this->convertWebsite($_entity);

            case 'sf_status':
                return $this->convertSfStatus($_entity);

            case 'sf_name':
                return $this->convertSfName($_entity);
        }

        return parent::getValue($_entity);
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $_entity
     * @return string
     */
    public function convertCartAll($_entity)
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $baseCurrency = Mage::helper('tnw_salesforce/config_sales')->useBaseCurrency();
        $currency = $baseCurrency ? $_entity->getBaseCurrencyCode() : $_entity->getOrderCurrencyCode();
        /**
         * use custome currency if Multicurrency enabled
         */
        if ($helper->isMultiCurrency()) {
            $currency = $_entity->getOrderCurrencyCode();
            $baseCurrency = false;
        }

        ## Put Products into Single field
        $delimiter = '=======================================';
        $lines = array();
        $lines[] = 'Items invoice:';
        $lines[] = $delimiter;
        $lines[] = 'SKU, Qty, Name, Price, Tax, Subtotal, Net Total';
        $lines[] = $delimiter;

        /** @var TNW_Salesforce_Helper_Salesforce_Creditmemo $_helperCreditmemo */
        $_helperCreditmemo = Mage::helper('tnw_salesforce/salesforce_creditmemo');

        /** @var Mage_Sales_Model_Order_Creditmemo_Item $item */
        foreach ($_helperCreditmemo->getItems($_entity) as $itemId => $item) {
            $rowTotalInclTax = $baseCurrency ? $item->getBaseRowTotalInclTax() : $item->getRowTotalInclTax();
            $discount = $baseCurrency ? $item->getBaseDiscountAmount() : $item->getDiscountAmount();

            $lines[] = implode(', ', array(
                $item->getSku(),
                $this->numberFormat($item->getQty()),
                $item->getName(),
                $currency . $this->numberFormat($baseCurrency ? $item->getBasePrice() : $item->getPrice()),
                $currency . $this->numberFormat($baseCurrency ? $item->getBaseTaxAmount() : $item->getTaxAmount()),
                $currency . $this->numberFormat($rowTotalInclTax),
                $currency . $this->numberFormat($rowTotalInclTax - $discount),
            ));
        }
        $lines[] = $delimiter;

        $subtotal = $baseCurrency ? $_entity->getBaseSubtotal() : $_entity->getSubtotal();
        $tax = $baseCurrency ? $_entity->getBaseTaxAmount() : $_entity->getTaxAmount();
        $shipping = $baseCurrency ? $_entity->getBaseShippingAmount() : $_entity->getShippingAmount();
        $grandTotal = $baseCurrency ? $_entity->getBaseGrandTotal() : $_entity->getGrandTotal();
        foreach (array(
                     'Sub Total' => $subtotal,
                     'Tax' => $tax,
                     'Shipping' => $shipping,
                     'Discount Amount' => $grandTotal - ($shipping + $tax + $subtotal),
                     'Total' => $grandTotal,
                 ) as $label => $totalValue) {
            $lines[] = sprintf('%s: %s%s', $label, $currency, $this->numberFormat($totalValue));
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $_entity
     * @return string
     */
    public function convertNumber($_entity)
    {
        return $_entity->getIncrementId();
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $_entity
     * @return string
     */
    public function convertWebsite($_entity)
    {
        /** @var tnw_salesforce_helper_magento_websites $websiteHelper */
        $websiteHelper = Mage::helper('tnw_salesforce/magento_websites');
        $_website = Mage::app()
            ->getStore($_entity->getStoreId())
            ->getWebsite();

        return $websiteHelper->getWebsiteSfId($_website);
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $_entity
     * @return string
     */
    public function convertSfStatus($_entity)
    {
        static $mappingState = null;
        if (is_null($mappingState)) {
            /** @var TNW_Salesforce_Model_Mysql4_Order_Creditmemo_Status_Collection $collection */
            $collection     = Mage::getResourceModel('tnw_salesforce/order_creditmemo_status_collection');
            $mappingState   = $collection->toStatusHash();
        }

        $stateId = $_entity->getState();
        if (isset($mappingState[$stateId])) {
            return $mappingState[$stateId];
        }

        return 'Draft';
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $_entity
     * @return string
     */
    public function convertSfName($_entity)
    {
        return $_entity->getIncrementId();
    }
}