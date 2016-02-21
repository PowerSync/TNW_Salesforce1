<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Order_Invoice_Observer
{
    const OBJECT_TYPE = 'invoice';

    public function saveAfter($_observer) {
        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::helper("tnw_salesforce")->log('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }
        $_invoice = $_observer->getEvent()->getInvoice();

        Mage::helper("tnw_salesforce")->log('TNW EVENT: Invoice #' . $_invoice->getIncrementId() . ' Sync');

        if (!Mage::helper('tnw_salesforce/config_sales_invoice')->syncInvoices()) {
            Mage::helper("tnw_salesforce")->log('SKIPING: Invoice synchronization disabled');
            return; // Disabled
        }

        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
        ) {
            Mage::helper("tnw_salesforce")->log('SKIPING: Connector is disabled');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::helper("tnw_salesforce")->log('ERROR: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array(intval($_invoice->getId())), 'Invoice', 'invoice');
            if (!$res) {
                Mage::helper("tnw_salesforce")->log('ERROR: Invoice not saved to local storage');
                return false;
            }
            return true;
        }

        $_invoice = Mage::getModel('sales/order_invoice')->load($_invoice->getId());

        if (
            $_invoice->getId()
        ) {
            Mage::helper('tnw_salesforce')->log("############################ New Invoice Start ############################");

            // Allow Powersync to overwite fired event for customizations
            $_object = new Varien_Object(array('object_type' => self::OBJECT_TYPE));
            Mage::dispatchEvent('tnw_salesforce_set_invoice_object', array('sf_object' => $_object));

            // Fire event that will process the request
            Mage::dispatchEvent(
                'tnw_sales_process_' . $_object->getObjectType(),
                array(
                    'invoiceIds'      => array($_invoice->getId()),
                    'message'       => "SUCCESS: Upserting Invoice #" . $_invoice->getIncrementId(),
                    'type'   => 'salesforce'
                )
            );

            Mage::helper('tnw_salesforce')->log("############################ New Invoice End ############################");
        } else {
            Mage::helper('tnw_salesforce')->log("---- SKIPPING INVOICE SYNC ----");
            Mage::helper('tnw_salesforce')->log("Invoice Status: " . $_invoice->getStateName());
            Mage::helper('tnw_salesforce')->log("Invoice Id: " . $_invoice->getIncrementId());
            if (Mage::getSingleton('core/session')->getFromSalesForce()) {
                Mage::helper('tnw_salesforce')->log("Transaction is from Salesforce!");
            }
            Mage::helper('tnw_salesforce')->log("--------");
        }
    }
}