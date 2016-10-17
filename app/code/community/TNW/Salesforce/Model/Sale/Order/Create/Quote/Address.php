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
        $productPrices = array();
        $feeIds        = Mage::helper('tnw_salesforce/magento_order')->getFeeIds();
        $object        = Mage::helper('tnw_salesforce/magento_order')->getSalesforceObject();

        //Fill $productPrices
        foreach ($object->OrderItems->records as $record) {
            $productPrices[$record->PricebookEntry->Product2Id] = $record->UnitPrice;
        }

        $quote  = $this->getQuote();
        $isBase = empty($object->CurrencyIsoCode) || (!empty($object->CurrencyIsoCode) && $quote->getBaseCurrencyCode() == $object->CurrencyIsoCode);

        Mage::dispatchEvent($this->_eventPrefix . '_collect_totals_before', array($this->_eventObject => $this));
        /** @var Mage_Sales_Model_Quote_Address_Total_Abstract $model */
        foreach ($this->getTotalCollector()->getCollectors() as $model) {
            switch ($model->getCode()) {
                case 'shipping':
                    if (!Mage::helper('tnw_salesforce')->isUpdateShippingTotal()) {
                        break;
                    }

                    if (empty($productPrices[$feeIds['shipping']])) {
                        break;
                    }

                    $price = $productPrices[$feeIds['shipping']];
                    $this->addData(array(
                        'shipping_amount'      => $isBase  ? $this->convertPrice($price, $quote->getBaseCurrencyCode(), $quote->getQuoteCurrencyCode()) : $price,
                        'base_shipping_amount' => !$isBase ? $this->convertPrice($price, $quote->getQuoteCurrencyCode(), $quote->getBaseCurrencyCode()) : $price
                    ));

                    $this->setGrandTotal($this->getGrandTotal() + $this->getShippingAmount());
                    $this->setBaseGrandTotal($this->getBaseGrandTotal() + $this->getBaseShippingAmount());
                    continue 2;

                case 'tax':
                    if (!Mage::helper('tnw_salesforce')->isUpdateTaxTotal()) {
                        break;
                    }

                    if (empty($productPrices[$feeIds['tax']])) {
                        break;
                    }

                    $price = $productPrices[$feeIds['tax']];
                    $this->addData(array(
                        'tax_amount'           => $isBase  ? $this->convertPrice($price, $quote->getBaseCurrencyCode(), $quote->getQuoteCurrencyCode()) : $price,
                        'base_tax_amount'      => !$isBase ? $this->convertPrice($price, $quote->getQuoteCurrencyCode(), $quote->getBaseCurrencyCode()) : $price
                    ));

                    $this->setGrandTotal($this->getGrandTotal() + $this->getTaxAmount());
                    $this->setBaseGrandTotal($this->getBaseGrandTotal() + $this->getBaseTaxAmount());
                    continue 2;
/*
                case 'tax_subtotal':
                case 'tax_shipping':
                    continue 2;
*/
                case 'discount':
                    if (!Mage::helper('tnw_salesforce')->isUpdateDiscountTotal()) {
                        break;
                    }

                    if (empty($productPrices[$feeIds['discount']])) {
                        break;
                    }

                    $price = $productPrices[$feeIds['discount']];
                    $this->addData(array(
                        'discount_amount'      => $isBase  ? $this->convertPrice($price, $quote->getBaseCurrencyCode(), $quote->getQuoteCurrencyCode()) : $price,
                        'base_discount_amount' => !$isBase ? $this->convertPrice($price, $quote->getQuoteCurrencyCode(), $quote->getBaseCurrencyCode()) : $price
                    ));

                    $this->setGrandTotal($this->getGrandTotal() + $this->getDiscountAmount());
                    $this->setBaseGrandTotal($this->getBaseGrandTotal() + $this->getBaseDiscountAmount());
                    continue 2;
            }

            $model->collect($this);
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