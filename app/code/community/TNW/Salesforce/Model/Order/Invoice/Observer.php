<?php

class TNW_Salesforce_Model_Order_Invoice_Observer
{
    const OBJECT_TYPE = 'invoice';

    public function saveAfter($_observer) {
        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }
        $_invoice = $_observer->getEvent()->getInvoice();

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('TNW EVENT: Invoice #' . $_invoice->getIncrementId() . ' Sync');

        if (!Mage::helper('tnw_salesforce/config_sales_invoice')->syncInvoices()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Invoice synchronization disabled');
            return; // Disabled
        }

        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Connector is disabled');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array(intval($_invoice->getId())), 'Invoice', 'invoice');
            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Invoice not saved to local storage');
                return false;
            }
            return true;
        }

        if (
            $_invoice->getId()
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("############################ New Invoice Start ############################");

            // Allow Powersync to overwite fired event for customizations
            $_object = new Varien_Object(array('object_type' => self::OBJECT_TYPE));
            Mage::dispatchEvent('tnw_salesforce_invoice_set_object', array('sf_object' => $_object));

            // Fire event that will process the request
            Mage::dispatchEvent(
                sprintf('tnw_salesforce_%s_process', $_object->getObjectType()),
                array(
                    'invoiceIds'      => array($_invoice->getId()),
                    'message'       => "SUCCESS: Upserting Invoice #" . $_invoice->getIncrementId(),
                    'type'   => 'salesforce'
                )
            );

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("############################ New Invoice End ############################");
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---- SKIPPING INVOICE SYNC ----");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Invoice Status: " . $_invoice->getStateName());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Invoice Id: " . $_invoice->getIncrementId());
            if (Mage::getSingleton('core/session')->getFromSalesForce()) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Transaction is from Salesforce!");
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("--------");
        }
    }
}