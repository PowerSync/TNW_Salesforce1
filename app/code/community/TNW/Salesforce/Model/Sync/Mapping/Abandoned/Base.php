<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 10.03.15
 * Time: 22:18
 */
abstract class TNW_Salesforce_Model_Sync_Mapping_Abandoned_Base extends TNW_Salesforce_Model_Sync_Mapping_Order_Base
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
    protected $_cachePrefix = 'abandoned';

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
        $_currencyCode = '';
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $_currencyCode = $quote->getData('quote_currency_code') . " ";
        }

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
            $descriptionCart .= $item->getSku() . ", " . number_format($item->getQty()) . ", " . $item->getName();
            //Price
            $unitPrice = number_format(($item->getPrice()), 2, ".", "");
            $descriptionCart .= ", " . $_currencyCode . $unitPrice;
            //Tax
            $tax = number_format(($item->getTaxAmount()), 2, ".", "");
            $descriptionCart .= ", " . $_currencyCode . $tax;
            //Subtotal
            $subtotal = number_format((($item->getPrice() + $item->getTaxAmount()) * $item->getQty()), 2, ".", "");
            $descriptionCart .= ", " . $_currencyCode . $subtotal;
            //Net Total
            $netTotal = number_format(($subtotal - $item->getDiscountAmount()), 2, ".", "");
            $descriptionCart .= ", " . $_currencyCode . $netTotal;
            $descriptionCart .= "\n";
        }
        $descriptionCart .= "=======================================\n";
        $descriptionCart .= "Sub Total: " . $_currencyCode . number_format(($quote->getSubtotal()), 2, ".", "") . "\n";
        $descriptionCart .= "Tax: " . $_currencyCode . number_format(($quote->getTaxAmount()), 2, ".", "") . "\n";
        $descriptionCart .= "Total: " . $_currencyCode . number_format(($quote->getGrandTotal()), 2, ".", "");
        $descriptionCart .= "\n";

        return $descriptionCart;
    }


}