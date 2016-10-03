<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Queue extends Mage_Core_Helper_Abstract
{
    const UPDATE_LIMIT = 5000;

    protected $_prefix = NULL;
    protected $_itemIds = array();

    public function processItems($itemIds = array())
    {
        if (count($itemIds) > 50 && Mage::helper('tnw_salesforce')->isAdmin()) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper("tnw_salesforce")->__("Please synchronize no more then 50 item at one time"));
            return false;
        }

        set_time_limit(0);

        try {
            $this->_itemIds = $itemIds;
            $this->_synchronizePreSet();
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @param $_type
     * @return mixed
     * Load queued enteties
     */
    protected function _loadQueue($_type = NULL)
    {
        $_collection = Mage::getModel('tnw_salesforce/queue_storage')->getCollection()
            ->addStatusNoToFilter('sync_running')
            ->addStatusNoToFilter('success')
            ->setOrder('status', 'ASC');
        if ($_type) {
            $_collection->addSftypeToFilter($_type);
        }

        if (count($this->_itemIds) > 0) {
            $_collection->getSelect()->where('id IN (?)', $this->_itemIds);
        }

        return $_collection;
    }

    protected function _synchronizePreSet()
    {
        $_collection = $this->_loadQueue();

        $_idSet = array();
        $_objectIdSet = array();
        $_objectType = array();

        foreach ($_collection as $item) {
            $_idSet[] = $item->getData('id');
            $_objectIdSet[] = $item->getData('object_id');
            if (!in_array($item->getData('sf_object_type'), $_objectType)) {
                $_objectType[] = $item->getData('sf_object_type');
            }
        }

        if (count($_objectType) > 1) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Synchronization of multiple record types is not supported yet, try synchronizing one record type.");
            return false;
        }

        $_type = $_objectType[0];

        if (!empty($_objectIdSet)) {
            // set status to 'sync_running'
            Mage::getModel('tnw_salesforce/localstorage')->updateObjectStatusById($_idSet);

            if (in_array(strtolower($_type), array('order', 'abandoned', 'invoice', 'shipment', 'creditmemo'))) {
                $_idPrefix = strtolower($_type);
                switch (strtolower($_type)) {
                    case 'order':
                        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
                        break;
                    case 'abandoned':
                        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getAbandonedObject());
                        $_idPrefix = 'order';
                        break;
                    case 'invoice':
                        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getInvoiceObject());
                        break;
                    case 'shipment':
                        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getShipmentObject());
                        break;
                    case 'creditmemo':
                        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getCreditmemoObject());
                        break;
                }

                Mage::dispatchEvent(
                    sprintf('tnw_salesforce_%s_process', $_syncType),
                    array(
                        $_idPrefix . 'Ids' => $_objectIdSet,
                        'message' => NULL,
                        'type' => 'bulk',
                        'isQueue' => true,
                        'queueIds' => $_idSet
                    )
                );
            }
            else {
                /**
                 * @var $manualSync TNW_Salesforce_Helper_Bulk_Product|TNW_Salesforce_Helper_Bulk_Customer|TNW_Salesforce_Helper_Bulk_Website
                 */
                $manualSync = Mage::helper('tnw_salesforce/bulk_' . strtolower($_type));
                if ($manualSync->reset()) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('################################## manual processing from queue for ' . $_type . ' started ##################################');
                    // sync products with sf
                    $checkAdd = $manualSync->massAdd($_objectIdSet);

                    // Delete Skipped Entity
                    $skipped = $manualSync->getSkippedEntity();
                    if (!empty($skipped)) {
                        $objectId = array();
                        foreach ($skipped as $entity_id) {
                            $objectId[] = @$_idSet[array_search($entity_id, $_objectIdSet)];
                        }

                        Mage::getModel('tnw_salesforce/localstorage')
                            ->deleteObject($objectId, true);
                    }

                    if ($checkAdd) {
                        $manualSync->setIsCron(true);
                        $manualSync->process();
                    }
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('################################## manual processing from queue for ' . $_type . ' finished ##################################');

                    // Update Queue
                    Mage::getModel('tnw_salesforce/localstorage')
                        ->updateQueue($_objectIdSet, $_idSet, $manualSync->getSyncResults(), $manualSync->getAlternativeKeys());
                } else {
                    Mage::getModel('tnw_salesforce/localstorage')->updateObjectStatusById($_idSet, 'new');
                }
            }

            if (!empty($_idSet)) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("INFO: " . $_type . " total synced: " . count($_idSet));
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("INFO: removing synced rows from mysql table...");
            }
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Salesforce connection failed");
            return false;
        }
    }

    /**
     * @TODO change this method calling to the addObject localstorage method
     * @deprecated, exists modules compatibility for
     * @param array $itemIds
     * @param null $_sfObject
     * @param null $_mageModel
     */
    public function prepareRecordsToBeAddedToQueue($itemIds = array(), $_sfObject = NULL, $_mageModel = NULL)
    {
        if (empty($itemIds) || !$_sfObject || !$_mageModel) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Could not add records to the queue!');
        }


        $localstorage = Mage::getModel('tnw_salesforce/localstorage');

        $localstorage->addObject($itemIds, $_sfObject, $_mageModel);

    }
}