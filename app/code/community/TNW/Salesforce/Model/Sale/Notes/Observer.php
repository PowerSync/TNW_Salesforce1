<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Sale_Notes_Observer
{
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

        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SKIPPING NOTES SYNC.");
            return; // Disabled
        }

        $event = $observer->getEvent();
        $note  = $event->getNote();
        if ($note->getData('salesforce_id') || !$note->getData('comment')) {
            return;
        }

        switch (true) {
            case $note instanceof Mage_Sales_Model_Order_Invoice_Comment:
                if (!Mage::helper('tnw_salesforce/config_sales_invoice')->syncInvoices()) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Invoice synchronization disabled');
                    return; // Disabled
                }

                /** @var Mage_Sales_Model_Order_Invoice $entity */
                $entity     = $note->getInvoice();
                $entityType = 'invoice';
                break;

            case $note instanceof Mage_Sales_Model_Order_Shipment_Comment:
                if (!Mage::helper('tnw_salesforce/config_sales_shipment')->syncShipments()) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Shipment synchronization disabled');
                    return; // Disabled
                }

                /** @var Mage_Sales_Model_Order_Shipment $entity */
                $entity     = $note->getShipment();
                $entityType = 'shipment';
                break;

            case $note instanceof Mage_Sales_Model_Order_Status_History:
                if (!Mage::helper('tnw_salesforce')->isEnabledOrderSync()) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Order synchronization disabled');
                    return; // Disabled
                }

                /** @var Mage_Sales_Model_Order $entity */
                $entity     = $note->getOrder();
                $entityType = 'order';
                break;

            case 'creditmemo':
                if (!Mage::helper('tnw_salesforce/config_sales_creditmemo')->syncCreditMemoForOrder()) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Credit Memo synchronization disabled');
                    return; // Disabled
                }

                /** @var Mage_Sales_Model_Order_Creditmemo $entity */
                $entity = Mage::getModel('sales/order_creditmemo')
                    ->load($event->getOid());

                break;

            default:
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Unknown type of comment');
                return; // Disabled
        }

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array($entity->getId()), ucfirst($entityType), $entityType);
            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf('ERROR: %s could not be added to the queue', $entityType));
            }

            return;
        }

        if ($entity->getSalesforceId()) {
            // Process Notes
            /** @var TNW_Salesforce_Helper_Salesforce_Order_Invoice $syncHelper */
            $syncHelper = Mage::helper($this->_syncHelperName($entityType));
            $syncHelper->reset();
            $syncHelper->createObjNones(array($note));
            $syncHelper->_cache['upserted' . $syncHelper->getManyParentEntityType()][$entity->getIncrementId()] = $entity->getSalesforceId();
            $syncHelper->pushDataNotes();
        }
        else {
            Mage::dispatchEvent($this->_syncEventName($entityType), array(
                "{$entityType}Ids"  => array($entity->getId()),
                'message'           => NULL,
                'type'              => 'salesforce'
            ));
        }
    }

    /**
     * @param $entityType
     * @return string
     */
    protected function _syncHelperName($entityType)
    {
        $entityType  = strtolower($entityType);
        $map         = array();
        $orderObject = Mage::helper('tnw_salesforce')->getOrderObject();

        switch (0) {
            case strcasecmp(TNW_Salesforce_Helper_Config_Sales::SYNC_TYPE_ORDER, $orderObject):
                $map = array(
                    'order'     => 'tnw_salesforce/salesforce_order',
                    'invoice'   => 'tnw_salesforce/salesforce_order_invoice',
                    'shipment'  => 'tnw_salesforce/salesforce_order_shipment',
                );
                break;

            case strcasecmp(TNW_Salesforce_Helper_Config_Sales::SYNC_TYPE_OPPORTUNITY, $orderObject):
                $map = array(
                    'order'     => 'tnw_salesforce/salesforce_opportunity',
                    'invoice'   => 'tnw_salesforce/salesforce_opportunity_invoice',
                    'shipment'  => 'tnw_salesforce/salesforce_opportunity_shipment',
                );
                break;
        }

        if (array_key_exists($entityType, $map)) {
            return $map[$entityType];
        }

        return null;
    }

    /**
     * @param $entityType
     * @return string
     */
    protected function _syncEventName($entityType)
    {
        switch ($entityType) {
            case 'order':
                $entityType = Mage::helper('tnw_salesforce')->getOrderObject();
                break;
            case 'invoice':
                $entityType = Mage::helper('tnw_salesforce')->getInvoiceObject();
                break;
            case 'shipment':
                $entityType = Mage::helper('tnw_salesforce')->getShipmentObject();
                break;
        }

        return sprintf('tnw_salesforce_%s_process', strtolower($entityType));
    }
}