<?php

class TNW_Salesforce_Test_Helper_Mapping extends TNW_Salesforce_Test_Case
{
    protected function getHelper()
    {
        return Mage::helper('tnw_salesforce/mapping');
    }

    /**
     * @loadFixture
     * @dataProvider dataProvider
     *
     * @param int $orderId
     * @param int $multiCurrency
     * @param int $useBaseCurrency
     */
    public function testGetOrderDescription($orderId, $multiCurrency, $useBaseCurrency)
    {
        //is multiple currency mock
        $this->mockHelper('tnw_salesforce', array('isMultiCurrency'))
            ->replaceByMock('helper')
            ->expects($this->any())
            ->method('isMultiCurrency')
            ->willReturn((bool)$multiCurrency);

        //use base price mock
        $this->mockHelper('tnw_salesforce/config_sales', array('useBaseCurrency'))
            ->replaceByMock('helper')
            ->expects($this->any())
            ->method('useBaseCurrency')
            ->willReturn((bool)$useBaseCurrency);

        $order = Mage::getModel('sales/order')->load($orderId);
        $dataHelper = Mage::helper('tnw_salesforce');

        $currencyCode = '';
        if ($dataHelper->isMultiCurrency()) {
            $currencyCode = $useBaseCurrency ? $order->getBaseCurrencyCode() : $order->getOrderCurrencyCode();
        }

        ## Put Products into Single field
        $expectation = "";
        $expectation .= "Items ordered:\n";
        $expectation .= "=======================================\n";
        $expectation .= "SKU, Qty, Name";
        $expectation .= ", Price";
        $expectation .= ", Tax";
        $expectation .= ", Subtotal";
        $expectation .= ", Net Total";
        $expectation .= "\n";
        $expectation .= "=======================================\n";

        /**
         * @var $item Mage_Sales_Model_Order_Item
         */
        foreach ($order->getAllVisibleItems() as $itemId => $item) {
            $price = $useBaseCurrency ? $item->getBasePrice() : $item->getPrice();
            $taxAmount = $useBaseCurrency ? $item->getBaseTaxAmount() : $item->getTaxAmount();
            $discountAmount = $useBaseCurrency ? $item->getBaseDiscountAmount() : $item->getDiscountAmount();

            $expectation .= $item->getSku() . ", " . $dataHelper->numberFormat($item->getQtyOrdered())
                . ", " . $item->getName();
            //Price
            $unitPrice = $dataHelper->numberFormat($price);
            $expectation .= ", " . $currencyCode . $unitPrice;
            //Tax
            $tax = $dataHelper->numberFormat($taxAmount);
            $expectation .= ", " . $currencyCode . $tax;
            //Subtotal
            $subtotal = $dataHelper->numberFormat(($price + $taxAmount) * $item->getQtyOrdered());
            $expectation .= ", " . $currencyCode . $subtotal;
            //Net Total
            $netTotal = $dataHelper->numberFormat($subtotal - $discountAmount);
            $expectation .= ", " . $currencyCode . $netTotal;
            $expectation .= "\n";
        }

        $orderSubtotal = $useBaseCurrency ? $order->getBaseSubtotal() : $order->getSubtotal();
        $orderTaxAmount = $useBaseCurrency ? $order->getBaseTaxAmount() : $order->getTaxAmount();
        $orderShipping = $useBaseCurrency ? $order->getBaseShippingAmount() : $order->getShippingAmount();
        $orderTotal = $useBaseCurrency ? $order->getBaseGrandTotal() : $order->getGrandTotal();

        $expectation .= "=======================================\n";
        $expectation .= "Sub Total: " . $currencyCode . $dataHelper->numberFormat($orderSubtotal) . "\n";
        $expectation .= "Tax: " . $currencyCode . $dataHelper->numberFormat($orderTaxAmount) . "\n";
        $expectation .= "Shipping (" . $order->getShippingDescription() . "): " . $currencyCode
            . $dataHelper->numberFormat($orderShipping) . "\n";
        $expectation .= "Discount Amount: " . $currencyCode
            . $dataHelper->numberFormat($orderTotal
                - ($orderShipping + $orderTaxAmount + $orderSubtotal)) . "\n";
        $expectation .= "Total: " . $currencyCode . $dataHelper->numberFormat($orderTotal);
        $expectation .= "\n";

        $this->assertEquals($expectation, $this->getHelper()->getOrderDescription($order));
    }
}