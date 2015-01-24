<?php

$installer = $this;

$installer->startSetup();

$_collection = Mage::getModel('tnw_salesforce/order_status')->getCollection();
foreach($_collection as $_item) {
    if (
        $_item->getData('status') == 'pending'
        || $_item->getData('status') == 'pending_paypal'
        || $_item->getData('status') == 'pending_payment'
    ) {
        $_item->setData('sf_order_status', 'Draft');
    } else {
        $_item->setData('sf_order_status', 'Activated');
    }
    $_item->save();

}

$installer->endSetup();