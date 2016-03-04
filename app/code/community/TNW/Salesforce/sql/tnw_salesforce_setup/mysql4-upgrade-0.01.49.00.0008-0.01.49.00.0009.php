<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

$installer = $this;

$installer->startSetup();

$_collection = Mage::getModel('tnw_salesforce/order_status')->getCollection();
if ($_collection->count() == 0) {
    $_defaults = array(
        'complete' => 'Closed Won',
        'canceled' => 'Closed Lost',
        'closed' => 'Closed Lost',
        'fraud' => 'Negotiation/Review',
        'payment_review' => 'Negotiation/Review',
        'pending_payment' => 'Negotiation/Review',
        'pending' => 'Proposal/Price Quote',
        'holded' => 'Needs Analysis',
        'processing' => 'Qualification',
        'pending_paypal' => 'Negotiation/Review',
        'paypal_canceled_reversal' => 'Needs Analysis',
        'paypal_reversed' => 'Needs Analysis'
    );

    // Fresh Install
    $_statuses = Mage::getResourceModel('sales/order_status_collection');
    $_statuses
        ->joinStates();

    foreach($_statuses as $_status) {
        $_statusMapping = Mage::getModel('tnw_salesforce/order_status');
        $_statusMapping->setStatus($_status->getData('status'));
        if (array_key_exists($_status->getData('status'), $_defaults)) {
            $_statusMapping->setData('sf_opportunity_status_code', $_defaults[$_status->getData('status')]);
        }
        $_statusMapping->save();
    }
    $_collection = Mage::getModel('tnw_salesforce/order_status')->getCollection();
}

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