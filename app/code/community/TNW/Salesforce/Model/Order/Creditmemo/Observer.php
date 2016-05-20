<?php

class TNW_Salesforce_Model_Order_Creditmemo_Observer
{
    const OBJECT_TYPE = 'creditmemo';

    /**
     * @param Varien_Event_Observer $_observer
     * @return bool|void
     */
    public function saveAfter(Varien_Event_Observer $_observer)
    {
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Connector is disabled');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce/config_sales_creditmemo')->syncCreditmemo()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Credit Memo synchronization disabled');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }

        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }

        /** @var Mage_Sales_Model_Order_Creditmemo $_creditmemo */
        $_creditmemo = $_observer->getEvent()->getCreditmemo();
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('TNW EVENT: Credit Memo #' . $_creditmemo->getIncrementId() . ' Sync');

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array($_creditmemo->getId()), 'Credit Memo', 'creditmemo');
            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Invoice not saved to local storage');
            }

            return;
        }

        if ($_creditmemo->getId()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("############################ New Credit Memo Start ############################");

            // Fire event that will process the request
            $_syncType = strtolower(Mage::helper('tnw_salesforce')->getCreditmemoObject());
            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                'creditmemoIds' => array($_creditmemo->getId()),
                'message'     => "SUCCESS: Upserting Credit Memo #" . $_creditmemo->getIncrementId(),
                'type'        => 'salesforce'
            ));

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("############################ New Credit Memo End ############################");
        }
        else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---- SKIPPING Credit Memo SYNC ----");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Credit Memo Id: " . $_creditmemo->getIncrementId());
            if (Mage::getSingleton('core/session')->getFromSalesForce()) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Transaction is from Salesforce!");
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("--------");
        }
    }
}