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
     * @return TNW_Salesforce_Model_Mysql4_Queue_Storage_Collection
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
        Mage::getSingleton('tnw_salesforce/cron')
            ->syncQueueStorage($this->_loadQueue()->getItems());
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