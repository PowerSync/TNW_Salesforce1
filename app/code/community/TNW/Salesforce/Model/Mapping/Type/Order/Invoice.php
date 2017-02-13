<?php

class TNW_Salesforce_Model_Mapping_Type_Order_Invoice extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Invoice';

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @return string
     */
    protected function _prepareValue($_entity)
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

            case 'grand_total':
                return $this->convertGrandTotal($_entity);
        }

        return parent::_prepareValue($_entity);
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $_entity
     * @return string
     */
    public function convertCartAll($_entity)
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $baseCurrency = Mage::helper('tnw_salesforce/config_sales')->useBaseCurrency() && !$helper->isMultiCurrency();
        $currency = $baseCurrency ? $_entity->getBaseCurrencyCode() : $_entity->getOrderCurrencyCode();

        ## Put Products into Single field
        $delimiter = '=======================================';
        $lines = array();
        $lines[] = 'Items invoice:';
        $lines[] = $delimiter;
        $lines[] = 'SKU, Qty, Name, Price, Tax, Subtotal, Net Total';
        $lines[] = $delimiter;

        /** @var TNW_Salesforce_Model_Mapping_Type_Order_Invoice_Item $mappingItem */
        $mappingItem = Mage::getSingleton('tnw_salesforce/mapping_type_order_invoice_item');

        $_itemCollection = $_entity->getItemsCollection();
        $_hasOrderItemId = $_itemCollection->walk('getOrderItemId');

        /** @var Mage_Sales_Model_Order_Invoice_Item $item */
        foreach ($_itemCollection as $item) {
            if ($item->isDeleted() || $item->getOrderItem()->getParentItem()) {
                continue;
            }

            if ($item->getOrderItem()->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                $childrenItems = $item->getOrderItem()->getChildrenItems();

                $orderItemsIds = array_map(function (Mage_Sales_Model_Order_Item $item) {
                    return $item->getId();
                }, $childrenItems);

                if (count(array_intersect($orderItemsIds, $_hasOrderItemId)) === 0) {
                    continue;
                }

                $lines[] = implode(', ', array($item->getSku(), '-', $item->getName(), '-', '-', '-', '-'));

                /** @var Mage_Sales_Model_Order_Item $childrenItem */
                foreach ($childrenItems as $childrenItem) {
                    $_item = $_itemCollection->getItemById(array_search($childrenItem->getId(), $_hasOrderItemId));
                    if (!$_item instanceof Mage_Sales_Model_Order_Invoice_Item) {
                        continue;
                    }

                    $lines[] = implode(', ', array(
                        $childrenItem->getSku(),
                        $helper->numberFormat($_item->getQty()),
                        $childrenItem->getName(),
                        $currency . $mappingItem->convertUnitPrice($_item, false, false),
                        $currency . $helper->numberFormat($this->getEntityPrice($_item, 'TaxAmount')),
                        $currency . $mappingItem->convertUnitPrice($_item, true, false),
                        $currency . $mappingItem->convertUnitPrice($_item, true, true),
                    ));
                }

                continue;
            }

            $lines[] = implode(', ', array(
                $item->getSku(),
                $helper->numberFormat($item->getQty()),
                $item->getName(),
                $currency . $mappingItem->convertUnitPrice($item, false, false),
                $currency . $helper->numberFormat($this->getEntityPrice($item, 'TaxAmount')),
                $currency . $mappingItem->convertUnitPrice($item, true, false),
                $currency . $mappingItem->convertUnitPrice($item, true, true),
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
     * @param Mage_Sales_Model_Order_Invoice $_entity
     * @return string
     */
    public function convertNumber($_entity)
    {
        return $_entity->getIncrementId();
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $_entity
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
     * @param Mage_Sales_Model_Order_Invoice $_entity
     * @return string
     */
    public function convertSfStatus($_entity)
    {
        return $_entity->getStateName();
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $_entity
     * @return string
     */
    public function convertSfName($_entity)
    {
        return $_entity->getIncrementId();
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $_entity
     * @return mixed
     */
    public function convertGrandTotal($_entity)
    {
        return Mage::helper('tnw_salesforce/config_sales')->useBaseCurrency() && !Mage::helper('tnw_salesforce/config_sales')->isMultiCurrency()
            ? $_entity->getBaseGrandTotal()
            : $_entity->getGrandTotal();
    }
}