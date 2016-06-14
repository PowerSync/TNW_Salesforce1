<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Sale_Notes_Observer
{
    /**
     * @param $observer
     */
    public function notesPush($observer)
    {
        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledOrderSync()
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Order synchronization disabled');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->isOrderNotesEnabled()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Notes synchronization disabled');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Salesforce connection could not be established, SKIPPING order notes sync');
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
                $syncHelper->createObjNones(array($note));
                $syncHelper->_cache['upserted' . $syncHelper->getManyParentEntityType()][$order->getRealOrderId()] = $order->getSalesforceId();
                $syncHelper->pushDataNotes();
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

    /**
     * @param Varien_Event_Observer $observer
     */
    public function baseNotesPush(Varien_Event_Observer $observer)
    {
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Synchronization disabled');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->isOrderNotesEnabled()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Notes synchronization disabled');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Salesforce connection could not be established, SKIPPING order notes sync');
            return; // Disabled
        }

        $orderObject = Mage::helper('tnw_salesforce')->getOrderObject();
        if (!in_array($orderObject, array(TNW_Salesforce_Helper_Config_Sales::SYNC_TYPE_ORDER, TNW_Salesforce_Helper_Config_Sales::SYNC_TYPE_OPPORTUNITY))) {
            return; // Disabled
        }

        $event      = $observer->getEvent();
        $entityType = $event->getType();
        switch ($entityType) {
            case 'invoice':
                if (!Mage::helper('tnw_salesforce/config_sales_invoice')->syncInvoices()) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Invoice synchronization disabled');
                    return; // Disabled
                }

                /** @var Mage_Sales_Model_Order_Invoice $entity */
                $entity = Mage::getModel('sales/order_invoice')
                    ->load($event->getOid());

                break;

            case 'shipment':
                if (!Mage::helper('tnw_salesforce/config_sales_shipment')->syncShipments()) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Shipment synchronization disabled');
                    return; // Disabled
                }

                /** @var Mage_Sales_Model_Order_Shipment $entity */
                $entity = Mage::getModel('sales/order_shipment')
                    ->load($event->getOid());

                break;

            default:
                return;
        }

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array($entity->getId()), ucfirst($entityType), $entityType);
            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf('ERROR: %s could not be added to the queue', $entityType));
            }

            return;
        }

        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace(sprintf("---- SKIPPING %s NOTES SYNC. ERRORS FOUND. PLEASE REFER TO LOG FILE ----", strtoupper($entityType)));

            return;
        }

        if ($entity->getSalesforceId()) {
            // Process Notes
            /** @var TNW_Salesforce_Model_Order_Invoice_Comment $note */
            $note = $event->getNote();
            call_user_func(array($note, sprintf('set%s', ucfirst($entityType))), $entity);

            $helperType = (TNW_Salesforce_Helper_Config_Sales::SYNC_TYPE_ORDER == $orderObject)
                ? 'order_' : 'opportunity_';

            /** @var TNW_Salesforce_Helper_Salesforce_Order_Invoice $syncHelper */
            $syncHelper = Mage::helper(sprintf('tnw_salesforce/salesforce_%s', $helperType . $entityType));
            $syncHelper->reset();
            $syncHelper->createObjNones(array($note));
            $syncHelper->_cache['upserted' . $syncHelper->getManyParentEntityType()][$entity->getIncrementId()] = $entity->getSalesforceId();
            $syncHelper->pushDataNotes();
        }
        else {
            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $entityType), array(
                sprintf('%sIds', $entityType) => array($entity->getId()),
                'message'    => NULL,
                'type'       => 'salesforce'
            ));
        }
    }
}