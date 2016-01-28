<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 10.03.15
 * Time: 22:18
 */
abstract class TNW_Salesforce_Model_Sync_Mapping_Quote_Base extends TNW_Salesforce_Model_Sync_Mapping_Order_Base
{


    /**
     * @comment list of the allowed mapping types
     * @var array
     */
    protected $_allowedMappingTypes = array(
        'Customer',
        'Billing',
        'Shipping',
        'Custom',
        'Cart',
        'Customer Group',
        'Payment',
    );


    /**
     * @var string
     */
    protected $_cachePrefix = 'quote';

    /**
     * @var string
     */
    protected $_cacheIdField = 'id';

    /**
     * @return TNW_Salesforce_Model_Mysql4_Mapping_Collection|null
     */
    public function getMappingCollection()
    {
        if (empty($this->_mappingCollection)) {
            $this->_mappingCollection = Mage::getModel('tnw_salesforce/mapping')->getCollection()->addObjectToFilter('Abandoned');
        }

        return $this->_mappingCollection;
    }

    /**
     * @param $quote Mage_Sales_Model_Quote
     * @return string
     */
    public static function getQuoteDescription($quote)
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
}