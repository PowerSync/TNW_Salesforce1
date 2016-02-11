<?php

class TNW_Salesforce_Model_Order_Invoice_Observer
{
    const OBJECT_TYPE = 'invoice';

    public function saveAfter($_observer)
    {
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Connector is disabled');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce/config_sales_invoice')->syncInvoices()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Invoice synchronization disabled');
            return; // Disabled
        }

        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }

        /** @var Mage_Sales_Model_Order_Invoice $_invoice */
        $_invoice = $_observer->getEvent()->getInvoice();
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('TNW EVENT: Invoice #' . $_invoice->getIncrementId() . ' Sync');

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array(intval($_invoice->getId())), 'Invoice', 'invoice');
            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Invoice not saved to local storage');
            }

            return;
        }

        if ($_invoice->getId()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("############################ New Invoice Start ############################");

            // Fire event that will process the request
            $_syncType = strtolower(Mage::helper('tnw_salesforce')->getInvoiceObject());
            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                'invoiceIds' => array($_invoice->getId()),
                'message'    => "SUCCESS: Upserting Invoice #" . $_invoice->getIncrementId(),
                'type'       => 'salesforce'
            ));

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("############################ New Invoice End ############################");
        }
        else {
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