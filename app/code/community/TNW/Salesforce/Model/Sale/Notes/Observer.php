<?php

/**
 * Class TNW_Salesforce_Model_Sale_Notes_Observer
 */
class TNW_Salesforce_Model_Sale_Notes_Observer
{
    /**
     * @param $observer
     */
    public function notesPush($observer)
    {
        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Salesforce connection could not be established, SKIPPING order notes sync');
            return; // Disabled
        }
        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledOrderSync()
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Order synchronization disabled');
            return; // Disabled
        }

        $event = $observer->getEvent();
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($event->getOid());
        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());

        /** @var Mage_Sales_Model_Order_Status_History $note */
        $note = $event->getNote();
        $note->setOrder($order);

        if ($note->getData('salesforce_id') || !$note->getData('comment')) {
            return;
        }

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            // pass data to local storage

            // TODO add level up abstract class with Order as static values, now we have word 'Order' as parameter
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array(intval($order->getData('entity_id'))), 'Order', 'order');
            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Order could not be added to the queue');
            }
            return;
        }

        if (
            !Mage::getSingleton('core/session')->getFromSalesForce()
        ) {
            if ($order->getSalesforceId()) {
                // Process Notes
                /** @var TNW_Salesforce_Helper_Salesforce_Abstract_Order $syncHelper */
                $syncHelper = Mage::helper('tnw_salesforce/salesforce_'.$_syncType);
                $syncHelper->reset();
                $syncHelper->createObjNones(array($note))->pushDataNotes();
            } else {
                // Never was synced, new order
                Mage::dispatchEvent(
                    sprintf('tnw_salesforce_%s_process', $_syncType),
                    array(
                        'orderIds'      => array($order->getId()),
                        'message'       => NULL,
                        'type'   => 'salesforce'
                    )
                );
            }
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---- SKIPPING ORDER NOTES SYNC. ERRORS FOUND. PLEASE REFER TO LOG FILE ----");
        }
    }
}