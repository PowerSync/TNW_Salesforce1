<?php

class TNW_Salesforce_Model_Order_Save
{
    public function __construct()
    {

    }

    public function salesforceUpdate($observer)
    {
        if (!Mage::helper('tnw_salesforce')->isEnabledOrderSync()) {
            Mage::helper("tnw_salesforce")->log('SKIPING: Order synchronization disabled');
            return; // Disabled
        }

        $order = $observer->getEvent()->getOrder();
        $order_id = $order->getId();
        $customer_id = $order->getCustomerId();
        unset($order);

        if (
            Mage::helper('tnw_salesforce')->isWorking() &&
            !Mage::getSingleton('core/session')->getFromSalesForce()
        ) {

            Mage::helper('tnw_salesforce/order')->doSalesForce($order_id, $customer_id);
            unset($order_id);
            unset($customer_id);
        }
    }
}