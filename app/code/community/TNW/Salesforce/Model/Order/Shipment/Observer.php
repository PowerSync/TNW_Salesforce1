<?php

class TNW_Salesforce_Model_Order_Shipment_Observer
{
    const OBJECT_TYPE = 'shipment';

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

        if (!Mage::helper('tnw_salesforce/config_sales_shipment')->syncShipments()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Shipment synchronization disabled');
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

        /** @var Mage_Sales_Model_Order_Shipment $_shipment */
        $_shipment = $_observer->getEvent()->getShipment();
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('TNW EVENT: Shipment #' . $_shipment->getIncrementId() . ' Sync');

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array($_shipment->getId()), 'Shipment', 'shipment');
            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Invoice not saved to local storage');
            }

            return;
        }

        if ($_shipment->getId()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("############################ New Shipment Start ############################");

            // Fire event that will process the request
            $_syncType = strtolower(Mage::helper('tnw_salesforce')->getShipmentObject());
            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                'shipmentIds' => array($_shipment->getId()),
                'message'     => "SUCCESS: Upserting Shipment #" . $_shipment->getIncrementId(),
                'type'        => 'salesforce'
            ));

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("############################ New Shipment End ############################");
        }
        else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---- SKIPPING SHIPMENT SYNC ----");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Shipment Id: " . $_shipment->getIncrementId());
            if (Mage::getSingleton('core/session')->getFromSalesForce()) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Transaction is from Salesforce!");
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("--------");
        }
    }

    /**
     * @param Varien_Event_Observer $_observer
     */
    public function saveTrackAfter(Varien_Event_Observer $_observer)
    {
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Connector is disabled');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce/config_sales_shipment')->syncShipments()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Shipment synchronization disabled');
            return; // Disabled
        }

        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }

        /** @var Mage_Sales_Model_Order_Shipment_Track $_track */
        $_track = $_observer->getEvent()->getTrack();
        $entity = $_track->getShipment();
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('TNW EVENT: Shipment #' . $entity->getIncrementId() . ' Sync');

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array($entity->getId()), 'Shipment', 'shipment');
            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Invoice not saved to local storage');
            }

            return;
        }

        if ($entity->getSalesforceId()) {
            // Process Notes
            /** @var TNW_Salesforce_Helper_Salesforce_Shipment $syncHelper */
            $syncHelper = Mage::helper('tnw_salesforce/salesforce_shipment');
            $syncHelper->reset();
            $syncHelper->_cache['orderShipmentLookup'] = Mage::helper('tnw_salesforce/salesforce_data_shipment')
                ->lookup(array($entity->getIncrementId()));
            $syncHelper->_cache['upserted' . $syncHelper->getManyParentEntityType()][$entity->getIncrementId()] = $entity->getSalesforceId();
            $syncHelper->createObjTrack(array($_track));
            $syncHelper->pushDataTrack();
        }
        else {
            // Fire event that will process the request
            $_syncType = strtolower(Mage::helper('tnw_salesforce')->getShipmentObject());
            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                'shipmentIds' => array($entity->getId()),
                'message'     => "SUCCESS: Upserting Shipment #" . $entity->getIncrementId(),
                'type'        => 'salesforce'
            ));
        }
    }
}