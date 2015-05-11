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
     * @param $quote Mage_Sales_Model_Quote
     * @return string
     */
    protected function _getDescriptionCart($quote)
    {
        $_currencyCode = $this->_getCurrencyCode($quote);

        ## Put Products into Single field
        $descriptionCart = "";
        $descriptionCart .= "Items quoteed:\n";
        $descriptionCart .= "=======================================\n";
        $descriptionCart .= "SKU, Qty, Name";
        $descriptionCart .= ", Price";
        $descriptionCart .= ", Tax";
        $descriptionCart .= ", Subtotal";
        $descriptionCart .= ", Net Total";
        $descriptionCart .= "\n";
        $descriptionCart .= "=======================================\n";

        foreach ($quote->getAllVisibleItems() as $itemId => $item) {
            $descriptionCart .= $item->getSku() . ", " . $this->_getNumberFormat($item->getQty()) . ", " . $item->getName();
            //Price
            $unitPrice = $this->_getNumberFormat($this->_getEntityPrice($item, 'Price'));
            $descriptionCart .= ", " . $_currencyCode . $unitPrice;
            //Tax
            $tax = $this->_getNumberFormat($this->_getEntityPrice($item, 'TaxAmount'));
            $descriptionCart .= ", " . $_currencyCode . $tax;
            //Subtotal
            $subtotal = $this->_getNumberFormat(($this->_getEntityPrice($item, 'Price') +$this->_getEntityPrice($item, 'TaxAmount')) * $item->getQty());
            $descriptionCart .= ", " . $_currencyCode . $subtotal;
            //Net Total
            $netTotal = $this->_getNumberFormat($subtotal - $this->_getEntityPrice($item, 'DiscountAmount'));
            $descriptionCart .= ", " . $_currencyCode . $netTotal;
            $descriptionCart .= "\n";
        }
        $descriptionCart .= "=======================================\n";
        $descriptionCart .= "Sub Total: " . $_currencyCode . $this->_getNumberFormat($this->_getEntityPrice($quote, 'Subtotal')) . "\n";
        $descriptionCart .= "Tax: " . $_currencyCode . $this->_getNumberFormat($this->_getEntityPrice($quote, 'TaxAmount')) . "\n";
        $descriptionCart .= "Total: " . $_currencyCode . $this->_getNumberFormat($this->_getEntityPrice($quote, 'GrandTotal'));
        $descriptionCart .= "\n";

        return $descriptionCart;
    }


}