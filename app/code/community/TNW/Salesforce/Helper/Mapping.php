<?php

class TNW_Salesforce_Helper_Mapping
{
    /**
     * @return TNW_Salesforce_Helper_Data
     */
    protected function getMainHelper()
    {
        return Mage::helper('tnw_salesforce');
    }

    public function getOrderDescription(Mage_Sales_Model_Order $order)
    {
        $helper = $this->getMainHelper();

        $currency = '';
        $baseCurrency = Mage::helper('tnw_salesforce/config_sales')->useBaseCurrency();
        if ($helper->isMultiCurrency()) {
            $currency = $baseCurrency ? $order->getBaseCurrencyCode() : $order->getOrderCurrencyCode();
        }

        ## Put Products into Single field
        $delimiter = '=======================================';
        $lines = array();
        $lines[] = 'Items ordered:';
        $lines[] = $delimiter;
        $lines[] = 'SKU, Qty, Name, Price, Tax, Subtotal, Net Total';
        $lines[] = $delimiter;

        /**
         * @var $item Mage_Sales_Model_Order_Item
         */
        foreach ($order->getAllVisibleItems() as $itemId => $item) {
            $rowTotalInclTax = $baseCurrency ? $item->getBaseRowTotalInclTax() : $item->getRowTotalInclTax();
            $discount = $baseCurrency ? $item->getBaseDiscountAmount() : $item->getDiscountAmount();

            $lines[] = implode(', ', array(
                $item->getSku(),
                $helper->numberFormat($item->getQtyOrdered()),
                $item->getName(),
                $currency . $helper->numberFormat($baseCurrency ? $item->getBasePrice() : $item->getPrice()),
                $currency . $helper->numberFormat($baseCurrency ? $item->getBaseTaxAmount() : $item->getTaxAmount()),
                $currency . $helper->numberFormat($rowTotalInclTax),
                $currency . $helper->numberFormat($rowTotalInclTax - $discount),
            ));
        }
        $lines[] = $delimiter;

        $subtotal = $baseCurrency ? $order->getBaseSubtotal() : $order->getSubtotal();
        $tax = $baseCurrency ? $order->getBaseTaxAmount() : $order->getTaxAmount();
        $shipping = $baseCurrency ? $order->getBaseShippingAmount() : $order->getShippingAmount();
        $grandTotal = $baseCurrency ? $order->getBaseGrandTotal() : $order->getGrandTotal();
        foreach (array(
                     'Sub Total' => $subtotal,
                     'Tax' => $tax,
                     'Shipping (' . $order->getShippingDescription() . ')' => $shipping,
                     'Discount Amount' => $grandTotal - ($shipping + $tax + $subtotal),
                     'Total' => $grandTotal,
                 ) as $label => $totalValue) {
            $lines[] = sprintf('%s: %s%s', $label, $currency, $helper->numberFormat($totalValue));
        }

        return implode("\n", $lines) . "\n";
    }
}