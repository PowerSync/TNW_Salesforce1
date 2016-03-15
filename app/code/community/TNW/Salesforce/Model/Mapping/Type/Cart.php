<?php

class TNW_Salesforce_Model_Mapping_Type_Cart extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Cart';

    /**
     * @param $_entity Mage_Sales_Model_Quote
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

            case 'sf_stage':
                return $this->convertSfStage($_entity);

            case 'sf_name':
                return $this->convertSfName($_entity);

            case 'price_book':
                return $this->convertPriceBook($_entity);

            case 'updated_at':
                return $this->convertUpdatedAt($_entity);
        }

        return parent::getValue($_entity);
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @return string
     */
    public function convertCartAll($quote)
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $baseCurrency = Mage::helper('tnw_salesforce/config_sales')->useBaseCurrency();
        $currency = $baseCurrency ? $quote->getBaseCurrencyCode() : $quote->getQuoteCurrencyCode();
        /**
         * use custome currency if Multicurrency enabled
         */
        if ($helper->isMultiCurrency()) {
            $currency = $quote->getQuoteCurrencyCode();
            $baseCurrency = false;
        }

        ## Put Products into Single field
        $delimiter = '=======================================';
        $lines = array();
        $lines[] = 'Items quote:';
        $lines[] = $delimiter;
        $lines[] = 'SKU, Qty, Name, Price, Tax, Subtotal, Net Total';
        $lines[] = $delimiter;

        /**
         * @var $item Mage_Sales_Model_Quote_Item
         */
        foreach ($quote->getAllVisibleItems() as $itemId => $item) {
            $rowTotalInclTax = $baseCurrency ? $item->getBaseRowTotalInclTax() : $item->getRowTotalInclTax();
            $discount = $baseCurrency ? $item->getBaseDiscountAmount() : $item->getDiscountAmount();

            $lines[] = implode(', ', array(
                $item->getSku(),
                $helper->numberFormat($item->getQty()),
                $item->getName(),
                $currency . $helper->numberFormat($baseCurrency ? $item->getBasePrice() : $item->getPrice()),
                $currency . $helper->numberFormat($baseCurrency ? $item->getBaseTaxAmount() : $item->getTaxAmount()),
                $currency . $helper->numberFormat($rowTotalInclTax),
                $currency . $helper->numberFormat($rowTotalInclTax - $discount),
            ));
        }
        $lines[] = $delimiter;

        $subtotal = $baseCurrency ? $quote->getBaseSubtotal() : $quote->getSubtotal();
        $grandTotal = $baseCurrency ? $quote->getBaseGrandTotal() : $quote->getGrandTotal();
        foreach (array(
                     'Sub Total' => $subtotal,
                     'Total' => $grandTotal,
                 ) as $label => $totalValue) {
            $lines[] = sprintf('%s: %s%s', $label, $currency, $helper->numberFormat($totalValue));
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param Mage_Sales_Model_Quote $_entity
     * @return string
     */
    public function convertNumber($_entity)
    {
        return TNW_Salesforce_Helper_Config_Sales_Abandoned::ABANDONED_CART_ID_PREFIX . $_entity->getId();
    }

    /**
     * @param Mage_Sales_Model_Quote $_entity
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
     * @param Mage_Sales_Model_Quote $_entity
     * @return string
     */
    public function convertSfStage($_entity)
    {
        if ($stage = Mage::helper('tnw_salesforce/config_sales_abandoned')->getDefaultAbandonedCartStageName()) {
            return $stage;
        }

        return 'Committed';
    }

    /**
     * @param Mage_Sales_Model_Quote $_entity
     * @return string
     */
    public function convertSfName($_entity)
    {
        return "Abandoned Cart #" . $this->convertNumber($_entity);
    }

    /**
     * @param Mage_Sales_Model_Quote $_entity
     * @return string
     */
    public function convertPriceBook($_entity)
    {
        $pricebook2Id = null;
        try {
            /** @var tnw_salesforce_helper_data $_helper */
            $_helper = Mage::helper('tnw_salesforce');
            $pricebook2Id = Mage::app()
                ->getStore($_entity->getStoreId())
                ->getConfig($_helper::PRODUCT_PRICEBOOK);
        }
        catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("INFO: Could not load pricebook based on the order ID. Loading default pricebook based on current store ID.");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());

            $_standardPricebookId = Mage::helper('tnw_salesforce/salesforce_data')->getStandardPricebookId();
            $pricebook2Id = (Mage::helper('tnw_salesforce')->getDefaultPricebook())
                ? Mage::helper('tnw_salesforce')->getDefaultPricebook() : $_standardPricebookId;
        }

        return $pricebook2Id;
    }

    /**
     * @param Mage_Sales_Model_Quote $_entity
     * @return string
     */
    public function convertUpdatedAt($_entity)
    {
        $closeDate = new Zend_Date($_entity->getUpdatedAt(), Varien_Date::DATETIME_INTERNAL_FORMAT);
        $closeDate->addDay(Mage::helper('tnw_salesforce/config_sales_abandoned')->getAbandonedCloseTimeAfter($_entity));

        // Always use quote date as closing date if quote already exists
        return gmdate(DATE_ATOM, $closeDate->getTimestamp());
    }
}