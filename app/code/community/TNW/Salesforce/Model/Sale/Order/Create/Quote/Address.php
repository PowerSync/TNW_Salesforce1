<?php

class TNW_Salesforce_Model_Sale_Order_Create_Quote_Address extends Mage_Sales_Model_Quote_Address
{
    /**
     * Collect address totals
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function collectTotals()
    {
        $object = $this->getQuote()->getData('_salesforce_object');
        if (empty($object)) {
            return parent::collectTotals();
        }

        /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $mappingCollection */
        $mappingCollection = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addFieldToFilter('sf_object', 'OrderItem')
            ->addFieldToFilter('sf_field', 'UnitPrice')
            ->addFieldToFilter('sf_magento_enable', 1)
            ->firstSystem()
        ;

        /** @var TNW_Salesforce_Model_Mapping $mapping */
        $mapping = $mappingCollection->getFirstItem();

        $clearType = array();
        switch ($mapping->getLocalFieldAttributeCode()) {
            case 'unit_price_including_tax_excluding_discounts':
                $clearType[] = 'tax';
                break;

            case 'unit_price_including_discounts_excluding_tax':
                $clearType[] = 'discount';
                break;

            case 'unit_price_including_tax_and_discounts':
                $clearType[] = 'tax';
                $clearType[] = 'discount';
                break;
        }

        $quote  = $this->getQuote();
        $isBase = empty($object->CurrencyIsoCode) || (!empty($object->CurrencyIsoCode) && $quote->getBaseCurrencyCode() == $object->CurrencyIsoCode);

        Mage::dispatchEvent($this->_eventPrefix . '_collect_totals_before', array($this->_eventObject => $this));
        /** @var Mage_Sales_Model_Quote_Address_Total_Abstract $model */
        foreach ($this->getTotalCollector()->getCollectors() as $model) {
            $code = $model->getCode();

            $price = $this->priceByFeeType($code);
            if (null === $price && in_array($code, $clearType)) {
                $price = 0;
            }

            // Default calculate price
            if (null === $price) {
                $model->collect($this);
                continue;
            }

            $total = $isBase ? $this->convertPrice($price, $quote->getBaseCurrencyCode(), $quote->getQuoteCurrencyCode()) : $price;
            $baseTotal = !$isBase ? $this->convertPrice($price, $quote->getQuoteCurrencyCode(), $quote->getBaseCurrencyCode()) : $price;

            switch ($code) {
                case 'discount':
                    $baseSubtotalWithDiscount = $subtotalWithDiscount = 0;

                    $items = $this->getAllItems();
                    if (!count($items)) {
                        $this->setDiscountAmount(0);
                        $this->setSubtotalWithDiscount($subtotalWithDiscount);
                        $this->setBaseDiscountAmount(0);
                        $this->setBaseSubtotalWithDiscount($baseSubtotalWithDiscount);
                        break;
                    }

                    /** @var Mage_Sales_Model_Quote_Item $item */
                    foreach ($items as $item) {
                        $item->setDiscountAmount(0);
                        $item->setBaseDiscountAmount(0);
                        $item->setRowTotalWithDiscount($item->getRowTotal());
                        $item->setBaseRowTotalWithDiscount($item->getBaseRowTotal());

                        $subtotalWithDiscount+=$item->getRowTotalWithDiscount();
                        $baseSubtotalWithDiscount+=$item->getBaseRowTotalWithDiscount();
                    }

                    $this->setDiscountAmount($total);
                    $this->setSubtotalWithDiscount($subtotalWithDiscount);
                    $this->setBaseDiscountAmount($baseTotal);
                    $this->setBaseSubtotalWithDiscount($baseSubtotalWithDiscount);

                    $this->setGrandTotal($this->getGrandTotal() - abs($total));
                    $this->setBaseGrandTotal($this->getBaseGrandTotal() - abs($baseTotal));
                    break;

                case 'shipping':
                    $items = $this->getAllNonNominalItems();
                    if (!count($items)) {
                        break;
                    }

                    $this->setShippingAmount($total);
                    $this->setBaseShippingAmount($baseTotal);
                    break;

                case 'tax':
                    $items = $this->getAllItems();
                    if (!count($items)) {
                        break;
                    }

                    $this->setTaxAmount($total);
                    $this->setBaseTaxAmount($baseTotal);

                    $this->setGrandTotal($this->getGrandTotal() + $this->getTaxAmount());
                    $this->setBaseGrandTotal($this->getBaseGrandTotal() + $this->getBaseTaxAmount());
                    break;
            }
        }

        Mage::dispatchEvent($this->_eventPrefix . '_collect_totals_after', array($this->_eventObject => $this));
        return $this;
    }

    /**
     * @param string $feeType
     * @return null
     */
    protected function priceByFeeType($feeType)
    {
        if (!Mage::helper('tnw_salesforce')->useFeeByType($feeType)) {
            return null;
        }

        if (!Mage::helper('tnw_salesforce')->isUpdateTotalByFeeType($feeType)) {
            return null;
        }

        $object = $this->getQuote()->getData('_salesforce_object');
        if (empty($object->OrderItems->records)) {
            return null;
        }

        $feeIds = Mage::helper('tnw_salesforce/magento_order')->getFeeIds();
        if (empty($feeIds[$feeType])) {
            return null;
        }

        $orderItem = Mage::helper('tnw_salesforce/magento_order')
            ->searchOrderItemByProductId($object->OrderItems->records, $feeIds[$feeType]);

        if (null === $orderItem) {
            return 0;
        }

        return $orderItem->UnitPrice;
    }

    /**
     * @param $value
     * @param $from
     * @param $to
     * @return float
     */
    protected function convertPrice($value, $from, $to)
    {
        $currency = Mage::getModel('directory/currency')->load($from);
        return $currency->convert($value, $to);
    }
}