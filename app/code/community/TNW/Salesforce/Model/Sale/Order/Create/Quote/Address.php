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

        $feeIds = Mage::helper('tnw_salesforce/magento_order')->getFeeIds();

        $productPrices = array();
        foreach ($object->OrderItems->records as $record) {
            $productPrices[$record->PricebookEntry->Product2Id] = $record->UnitPrice;
        }

        $quote  = $this->getQuote();
        $isBase = empty($object->CurrencyIsoCode) || (!empty($object->CurrencyIsoCode) && $quote->getBaseCurrencyCode() == $object->CurrencyIsoCode);

        Mage::dispatchEvent($this->_eventPrefix . '_collect_totals_before', array($this->_eventObject => $this));
        /** @var Mage_Sales_Model_Quote_Address_Total_Abstract $model */
        foreach ($this->getTotalCollector()->getCollectors() as $model) {
            $code = $model->getCode();
            if (!Mage::helper('tnw_salesforce')->isUpdateTotalByFeeType($code) || empty($productPrices[$feeIds[$code]])) {
                $model->collect($this);
                continue;
            }

            $price = $productPrices[$feeIds[$code]];
            $this->addData(array(
                "{$code}_amount" => $isBase ? $this->convertPrice($price, $quote->getBaseCurrencyCode(), $quote->getQuoteCurrencyCode()) : $price,
                "base_{$code}_amount" => !$isBase ? $this->convertPrice($price, $quote->getQuoteCurrencyCode(), $quote->getBaseCurrencyCode()) : $price
            ));

            if ($code == 'discount') {
                $baseSubtotalWithDiscount = $subtotalWithDiscount = 0;
                foreach ($this->getAllItems() as $item) {
                    $subtotalWithDiscount+=$item->getRowTotal();
                    $baseSubtotalWithDiscount+=$item->getBaseRowTotal();
                }

                $this->setSubtotalWithDiscount($subtotalWithDiscount);
                $this->setBaseSubtotalWithDiscount($baseSubtotalWithDiscount);
            }

            $this->setGrandTotal($this->getGrandTotal() + $this->getData("{$code}_amount"));
            $this->setBaseGrandTotal($this->getBaseGrandTotal() + $this->getData("base_{$code}_amount"));
        }

        Mage::dispatchEvent($this->_eventPrefix . '_collect_totals_after', array($this->_eventObject => $this));
        return $this;
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