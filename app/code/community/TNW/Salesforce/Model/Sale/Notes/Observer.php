<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Sale_Notes_Observer
{
    /**
     * @param Varien_Event_Observer $observer
     * @throws Exception
     */
    public function baseNotesPush(Varien_Event_Observer $observer)
    {
        $event = $observer->getEvent();
        $note  = $event->getNote();
        if ($note->getData('salesforce_id') || !$note->getData('comment')) {
            return;
        }

        switch (true) {
            case $note instanceof Mage_Sales_Model_Order_Invoice_Comment:
                /** @var Mage_Sales_Model_Order_Invoice $entity */
                $entity     = $note->getInvoice();
                $entityType = 'invoice';
                break;

            case $note instanceof Mage_Sales_Model_Order_Shipment_Comment:
                /** @var Mage_Sales_Model_Order_Shipment $entity */
                $entity     = $note->getShipment();
                $entityType = 'shipment';
                break;

            case $note instanceof Mage_Sales_Model_Order_Status_History:
                /** @var Mage_Sales_Model_Order $entity */
                $entity     = $note->getOrder();
                $entityType = 'order';
                break;

            case $note instanceof Mage_Sales_Model_Order_Creditmemo_Comment:
                /** @var Mage_Sales_Model_Order_Creditmemo $entity */
                $entity     = $note->getCreditmemo();
                $entityType = 'creditmemo';
                break;

            default:
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Unknown type of comment');
                return; // Disabled
        }

        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($note->getStore()->getWebsite(), function () use($entity, $note, $entityType) {
            if (!Mage::helper('tnw_salesforce')->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPING: Synchronization disabled');
                return; // Disabled
            }

            if (!Mage::helper('tnw_salesforce')->isOrderNotesEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPING: Notes synchronization disabled');
                return; // Disabled
            }

            if (!Mage::helper('tnw_salesforce')->canPush()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR: Salesforce connection could not be established, SKIPPING order notes sync');
                return; // Disabled
            }

            if (Mage::getSingleton('core/session')->getFromSalesForce()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("SKIPPING NOTES SYNC.");
                return; // Disabled
            }

            // check if queue sync setting is on - then save to database
            if (!Mage::helper('tnw_salesforce')->isRealTimeType()) {
                $res = Mage::getModel('tnw_salesforce/localstorage')
                    ->addObject(array($entity->getId()), ucfirst($entityType), $entityType);

                if (!$res) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError(sprintf('ERROR: %s could not be added to the queue', $entityType));
                }

                return;
            }

            if ($entity->getSalesforceId()) {
                Mage::dispatchEvent(sprintf('tnw_salesforce_sync_%s_comment_for_website', $entityType), array(
                    'note' => $note,
                    'entity' => $entity
                ));
            } else {
                Mage::dispatchEvent(sprintf('tnw_salesforce_sync_%s_for_website', $entityType), array(
                    'entityIds' => array($entity->getId()),
                    'syncType' => 'realtime'
                ));
            }
        });
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function opportunityCommentForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales')->integrationOpportunityAllowed()) {
            return; // Disabled
        }

        $observer->setData('entityPathPostfix', 'opportunity');
        $this->entityCommentForWebsite($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function orderCommentForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales')->integrationOrderAllowed()) {
            return; // Disabled
        }

        $observer->setData('entityPathPostfix', 'order');
        $this->entityCommentForWebsite($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function opportunityInvoiceCommentForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales_invoice')->syncInvoicesForOpportunity()) {
            return; // Disabled
        }

        $observer->setData('entityPathPostfix', 'opportunity_invoice');
        $this->entityCommentForWebsite($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function orderInvoiceCommentForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales_invoice')->syncInvoicesForOrder()) {
            return; // Disabled
        }

        $observer->setData('entityPathPostfix', 'order_invoice');
        $this->entityCommentForWebsite($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function opportunityShipmentCommentForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales_shipment')->syncShipmentsForOpportunity()) {
            return; // Disabled
        }

        $observer->setData('entityPathPostfix', 'opportunity_shipment');
        $this->entityCommentForWebsite($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function orderShipmentCommentForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales_shipment')->syncShipmentsForOrder()) {
            return; // Disabled
        }

        $observer->setData('entityPathPostfix', 'order_shipment');
        $this->entityCommentForWebsite($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function orderCreditmemoCommentForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales_creditmemo')->syncCreditMemoForOrder()) {
            return; // Disabled
        }

        $observer->setData('entityPathPostfix', 'order_creditmemo');
        $this->entityCommentForWebsite($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function entityCommentForWebsite($observer)
    {
        $note = $observer->getData('note');
        if (!is_object($note)) {
            throw new InvalidArgumentException('note argument not object');
        }

        $entity = $observer->getData('entity');
        if (!is_object($entity)) {
            throw new InvalidArgumentException('entity argument not object');
        }

        $entityPathPostfix = $observer->getData('entityPathPostfix');
        if (!is_string($entityPathPostfix)) {
            throw new InvalidArgumentException('entityPathPostfix argument not string');
        }

        // Process Notes
        /** @var TNW_Salesforce_Helper_Salesforce_Order_Invoice $syncHelper */
        $syncHelper = Mage::helper(sprintf('tnw_salesforce/salesforce_%s', $entityPathPostfix));
        if ($syncHelper->reset()) {
            $syncHelper->createObjNones(array($note));
            $syncHelper->_cache['upserted' . $syncHelper->getManyParentEntityType()][$entity->getIncrementId()] = $entity->getSalesforceId();
            $syncHelper->pushDataNotes();
        }
    }
}