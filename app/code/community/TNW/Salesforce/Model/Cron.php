<?php

/**
 * Class TNW_Salesforce_Model_Cron
 */
class TNW_Salesforce_Model_Cron
{
    // Buffer 1 hour to identify stuck records
    const INTERVAL_BUFFER              = 3600;
    const SYNC_TYPE_OUTGOING           = 'outgoing';
    const SYNC_TYPE_BULK               = 'bulk';
    const CRON_LAST_RUN_TIMESTAMP_PATH = 'salesforce/syncronization/cron_last_run_timestamp';

    /**
     * @var string
     */
    protected $_syncType = self::SYNC_TYPE_OUTGOING;

    /**
     * @return string
     */
    public function getSyncType()
    {
        return $this->_syncType;
    }

    /**
     * @param string $syncType
     */
    public function setSyncType($syncType)
    {
        $this->_syncType = $syncType;
    }

    /**
     * @var null
     */
    protected $_serverName = NULL;

    /**
     *
     */
    public function backgroundProcess()
    {
        // Only process if module is enabled
        if (Mage::helper('tnw_salesforce')->isEnabled()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("=== Salesforce 2 Magento queue START ===");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Check Salesforce to Magento queue ...");
            Mage::getModel('tnw_salesforce/imports_bulk')->process();
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Check Salesforce to Magento queue ... done");
            Mage::dispatchEvent('tnw_salesforce_cron_after', array('observer' => $this, 'method' => 'backgroundProcess'));
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("=== Salesforce 2 Magento queue END ===");
        }
    }

    /**
     *
     */
    public function updateFeed()
    {
        Mage::getModel('tnw_salesforce/feed')->checkUpdate();
        Mage::dispatchEvent('tnw_salesforce_cron_after', array('observer' => $this, 'method' => 'updateFeed'));
    }

    /**
     * @comment add Abandoned carts to quote for synchronization
     * @throws Exception
     */
    public function addAbandonedToQueue()
    {
        /** @var $collection Mage_Reports_Model_Resource_Quote_Collection */
        $collection = Mage::getModel('tnw_salesforce/abandoned')->getAbandonedCollection();
        $collection->addFieldToFilter('sf_sync_force', 1);
        $collection->addFieldToFilter('customer_id', array('neq' => 'NULL' ));

        $itemIds = $collection->getAllIds();
        if (empty($itemIds)) {
            return false;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('=== Magento Abandoned Cart queue preparation START ===');

        Mage::getSingleton('tnw_salesforce/abandoned')->syncAbandoned($itemIds, false);

        foreach (array_chunk($itemIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT) as $_chunk) {
            Mage::helper('tnw_salesforce')->getDbConnection('write')
                ->update($collection->getMainTable(), array('sf_sync_force' => 0), array('entity_id IN (?)' => $_chunk));
        }

        Mage::dispatchEvent('tnw_salesforce_cron_after', array('observer' => $this, 'method' => 'addAbandonedToQueue'));
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('=== Magento Abandoned Cart queue preparation END ===');
        return true;
    }

    /**
     * @comment Auto Currency Sync
     * @throws Exception
     */
    public function syncCurrency()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("=== Magento 2 Salesforce currency sync START ===");

        foreach (Mage::helper('tnw_salesforce/config')->getWebsitesDifferentConfig() as $website) {
            Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function() {
                /** @var TNW_Salesforce_Helper_Data $_helperData */
                $_helperData = Mage::helper('tnw_salesforce');
                if (!$_helperData->isEnabled() || !$_helperData->isMultiCurrency()) {
                    return;
                }

                $currencies = Mage::getModel('directory/currency')
                    ->getConfigAllowCurrencies();

                try {
                    $manualSync = Mage::helper('tnw_salesforce/salesforce_currency');
                    if ($manualSync->reset() && $manualSync->massAdd($currencies) && $manualSync->process() && $successCount = $manualSync->countSuccessEntityUpsert()) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveTrace($_helperData->__('%d Magento currency entities were successfully synchronized', $successCount));
                    }
                } catch (Exception $e) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError($e->getMessage());
                }
            });
        }

        Mage::dispatchEvent('tnw_salesforce_cron_after', array('observer' => $this, 'method' => 'syncCurrency'));
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("=== Magento 2 Salesforce currency sync END ===");
    }

    /**
     * this method is called instantly from cron script
     */
    public function processQueue()
    {
        set_time_limit(0);
        @define('PHP_SAPI', 'cli');
        $this->_syncType = self::SYNC_TYPE_OUTGOING;

        /** @var TNW_Salesforce_Helper_Data $_helperData */
        // Do not process the queue if extension is disabled
        $_helperData = Mage::helper('tnw_salesforce');
        if (!$_helperData->isEnabled()) {
            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("=== Magento 2 Salesforce queue START ===");
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf("PowerSync background process for store (%s) and website id (%s) ...",
            $_helperData->getStoreId(), $_helperData->getWebsiteId()));

        $this->_updateQueue();

        // cron is running now, thus save last cron run timestamp
        Mage::getConfig()->saveConfig(self::CRON_LAST_RUN_TIMESTAMP_PATH, (int)$_helperData->getTime());

        $this->_syncObjectForBulkMode();

        $this->_deleteSuccessfulRecords();
        Mage::dispatchEvent('tnw_salesforce_cron_after', array('observer' => $this, 'method' => 'processQueue'));
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("=== Magento 2 Salesforce queue END ===");
    }

    /**
     *
     */
    public function processBulkQueue()
    {
        set_time_limit(0);
        @define('PHP_SAPI', 'cli');
        $this->_syncType = self::SYNC_TYPE_BULK;

        /** @var TNW_Salesforce_Helper_Data $_helperData */
        $_helperData = Mage::helper('tnw_salesforce');
        if (!$_helperData->isEnabled()) {
            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("=== Magento 2 Salesforce BULK queue START ===");
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf("PowerSync Bulk background process for store (%s) and website id (%s) ...",
            $_helperData->getStoreId(), $_helperData->getWebsiteId()));

        $this->_syncObjectForBulkMode();
        Mage::dispatchEvent('tnw_salesforce_cron_after', array('observer' => $this, 'method' => 'processBulkQueue'));
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("=== Magento 2 Salesforce BULK queue END ===");
    }

    protected function _syncObjectForBulkMode()
    {
        Mage::dispatchEvent('tnw_salesforce_cron_sync_object_bulk_before', array('cron_object' => $this));

        // Synchronize Websites
        $this->_syncWebsites();

        // Sync Products
        $this->syncProduct();

        // Sync Customers
        $this->syncCustomer();

        // Sync abandoned
        $this->syncAbandoned();

        // Sync orders
        $this->syncOrder();

        // Sync invoices
        $this->syncInvoices();

        // Sync shipment
        $this->syncShipment();

        // Sync shipment
        $this->syncCreditMemo();

        // Sync SalesRule
        $this->syncSalesRule();

        // Sync CatalogRule
        //$this->syncCatalogRule();

        // Sync WishList
        $this->syncWishlist();

        // Sync custom objects
        $this->_syncCustomObjects();

        Mage::dispatchEvent('tnw_salesforce_cron_sync_object_bulk_after', array('cron_object' => $this));
    }

    protected function _syncObjectForRealTimeMode()
    {
        Mage::dispatchEvent('tnw_salesforce_cron_sync_object_real_time_before', array('cron_object' => $this));

        // Sync Products
        $this->syncProduct();

        // Sync abandoned
        $this->syncAbandoned();

        Mage::dispatchEvent('tnw_salesforce_cron_sync_object_real_time_after', array('cron_object' => $this));
    }

    public function _syncCustomObjects()
    {
        // Implemented for customization
    }

    public function _syncWebsites()
    {
        try {

            $this->syncEntity('website');
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("ERROR: website not synced: %s", $e->getMessage()));
            return false;
        }

        return true;
    }

    /**
     * @param $type
     * @return mixed|null|string
     * @throws Exception
     */
    public function getBatchSize($type)
    {
        /** @var TNW_Salesforce_Helper_Config_Bulk $_configHelper */
        $_configHelper = Mage::helper('tnw_salesforce/config_bulk');
        switch ($type) {
            case 'customer':
                $batchSize = $_configHelper->getCustomerBatchSize();
                break;
            case 'product':
                $batchSize = $_configHelper->getProductBatchSize();
                break;
            case 'website':
                $batchSize = $_configHelper->getWebsiteBatchSize();
                break;
            case 'order':
                $batchSize = $_configHelper->getOrderBatchSize();
                break;
            case 'abandoned':
                $batchSize = $_configHelper->getAbandonedBatchSize();
                break;
            case 'invoice':
                $batchSize = $_configHelper->getInvoiceBatchSize();
                break;
            case 'shipment':
                $batchSize = $_configHelper->getShipmentBatchSize();
                break;
            case 'creditmemo':
                $batchSize = $_configHelper->getCreditMemoBatchSize();
                break;
            case 'campaign_salesrule':
                $batchSize = $_configHelper->getSalesRuleBatchSize();
                break;
            case 'campaign_catalogrule':
                $batchSize = $_configHelper->getCatalogRuleBatchSize();
                break;
            case 'wishlist':
                $batchSize = $_configHelper->getWishlistBatchSize();
                break;
            default:
                $transport = new Varien_Object(array('batch_size' => null));
                Mage::dispatchEvent(sprintf('tnw_salesforce_%s_batch_size', $type), array('transport' => $transport));

                $batchSize = $transport->getData('batch_size');
                if (is_null($batchSize)) {
                    throw new Exception('Incorrect entity type, no batch size for "' . $type . '" type');
                }
        }

        return $batchSize;

    }

    /**
     * Delete synced records from the queue
     */
    protected function _deleteSuccessfulRecords()
    {
        $sql = "DELETE FROM `" . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . "` WHERE status = 'success';";
        Mage::helper('tnw_salesforce')->getDbConnection('delete')->query($sql);
        //Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Synchronized records removed from the queue ...");
    }

    protected function _resetStuckRecords()
    {
        $_whenToReset = Mage::helper('tnw_salesforce')->getTime() - self::INTERVAL_BUFFER;
        $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . "` SET status = '' WHERE status = 'sync_running' AND date_created < '" . Mage::helper('tnw_salesforce')->getDate($_whenToReset) . "';";
        Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
        //Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Trying to reset any stuck records ...");
    }

    /**
     * Delete successful records, add new, reset stuck items
     */
    protected function _updateQueue()
    {
        // Add pending items into the queue
        $_collection = Mage::getModel('tnw_salesforce/queue')->getCollection();
        /** @var TNW_Salesforce_Model_Queue $_pendingItem */
        foreach ($_collection as $_pendingItem) {
            $_method = ($_pendingItem->getData('mage_object_type') == 'product')
                ? 'addObjectProduct'
                : 'addObject';

            $_result = call_user_func_array(array(Mage::getModel('tnw_salesforce/localstorage'), $_method), array(
                unserialize($_pendingItem->getData('record_ids')),
                $_pendingItem->getData('sf_object_type'),
                $_pendingItem->getData('mage_object_type')
            ));

            if ($_result) {
                $_pendingItem->delete();
            }
        }

        $this->_resetStuckRecords();
        $this->_deleteSuccessfulRecords();
    }

    /**
     * Sync queue items by entity type = order|abandoned|invoice|customer|product|website
     *
     * @param string $type
     * @return void
     * @throws Exception
     */
    public function syncEntity($type)
    {
        $this->_resetStuckRecords();
        $this->_deleteSuccessfulRecords();

        switch (true) {
            case in_array($type, array('order', 'abandoned')):
                $_dependencies = Mage::getModel('tnw_salesforce/localstorage')
                    ->getAllDependencies(array('customer', 'product'));
                break;

            case in_array($type, array('invoice', 'shipment', 'creditmemo')):
                $_dependencies = Mage::getModel('tnw_salesforce/localstorage')
                    ->getAllDependencies(array('order'));
                break;

            default:
                $_dependencies = array();
                break;
        }

        // get entity id list from local storage
        /** @var TNW_Salesforce_Model_Mysql4_Queue_Storage_Collection $list */
        $list = Mage::getResourceModel('tnw_salesforce/queue_storage_collection')
            ->addSftypeToFilter($type)
            ->addSyncAttemptToFilter()
            ->addStatusNoToFilter('sync_running')
            ->addStatusNoToFilter('success')
            ->addFieldToFilter('sync_type', array('eq' => $this->_syncType))
            ->addFieldToFilter('website_id', array('eq' => Mage::app()->getWebsite()->getId()))
            ->setOrder('status', 'ASC')    // Leave 'error' at the end of the collection
        ;

        $list->setPageSize($this->getBatchSize($type));
        $lPage = $list->getLastPageNumber();

        for($page = 1; $page <= $lPage; $page++) {

            try {

                $list->clear();

                $storageItems = array_filter($list->getItems(), function (TNW_Salesforce_Model_Queue_Storage $storage) use($_dependencies) {
                    if (!in_array($storage->getMageObjectType(), array('sales/order', 'sales/quote'))) {
                        return true;
                    }

                    if (!isset($_dependencies['Customer']) && !isset($_dependencies['Product'])) {
                        return true;
                    }

                    /** @var Mage_Sales_Model_Order|Mage_Sales_Model_Quote $_entity */
                    $_entity = Mage::getModel($storage->getMageObjectType())
                        ->load($storage->getObjectId());

                    if ($_entity->getCustomerId() && isset($_dependencies['Customer'])
                        && in_array($_entity->getCustomerId(), $_dependencies['Customer'])
                    ) {
                        return false;
                    }

                    if (isset($_dependencies['Product'])) {
                        /** @var Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item $_item */
                        foreach ($_entity->getAllVisibleItems() as $_item) {
                            $id = $_item instanceof Mage_Sales_Model_Order_Item
                                ? Mage::helper('tnw_salesforce/salesforce_order')->getProductIdFromCart($_item)
                                : Mage::helper('tnw_salesforce/salesforce_abandoned_opportunity')->getProductIdFromCart($_item);

                            if (in_array($id, $_dependencies['Product'])) {
                                return false;
                            }
                        }
                    }

                    return true;
                });

                $storageItems = array_filter($storageItems, function (TNW_Salesforce_Model_Queue_Storage $storage) use($_dependencies) {
                    if (!in_array($storage->getMageObjectType(), array('sales/order_invoice', 'sales/order_shipment', 'sales/order_creditmemo'))) {
                        return true;
                    }

                    if (!isset($_dependencies['Order'])) {
                        return true;
                    }

                    /** @var Mage_Sales_Model_Order_Invoice $_entity */
                    $_entity = Mage::getModel($storage->getMageObjectType())
                        ->load($storage->getObjectId());

                    if ($_entity->getOrderId() && in_array($_entity->getOrderId(), $_dependencies['Order'])) {
                        return false;
                    }

                    return true;
                });

                $this->syncQueueStorage($storageItems);
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("ERROR: %s not synced: %s", $type, $e->getMessage()));
            }
        }
    }

    /**
     * @param $iterate TNW_Salesforce_Model_Queue_Storage[]
     * @throws Exception
     */
    public function syncQueueStorage(array $iterate)
    {
        $websiteStorage = array();
        foreach ($iterate as $queueStorage) {
            $websiteStorage = array_merge_recursive($websiteStorage, array($queueStorage->getWebsite()->getCode() => array(
                strtolower($queueStorage->getSfObjectType()) => array(
                    'storageId' => array($queueStorage->getId()),
                    'objectId' => array($queueStorage->getObjectId()),
                )
            )));
        }

        foreach ($websiteStorage as $website => $typeStorage) {
            Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($typeStorage) {
                foreach ($typeStorage as $type => $queueStorage) {
                    $idSet = $queueStorage['storageId'];
                    $objectIdSet = $queueStorage['objectId'];

                    Mage::getModel('tnw_salesforce/localstorage')->updateObjectStatusById($idSet);

                    if (in_array($type, array('order', 'abandoned', 'invoice', 'shipment', 'creditmemo'))) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveTrace(sprintf('Processing %s: %s records', $type, count($objectIdSet)));

                        $syncObjStack = new SplStack();
                        Mage::dispatchEvent(sprintf('tnw_salesforce_sync_%s_for_website', $type), array(
                            'entityIds' => $objectIdSet,
                            'syncType' => 'bulk',
                            'isCron' => true,
                            'syncObjectStack' => $syncObjStack
                        ));

                        /** @var TNW_Salesforce_Helper_Salesforce_Abstract_Base $manualSync */
                        foreach ($syncObjStack as $manualSync) {
                            // Delete Skipped Entity
                            $skipped  = $manualSync->getSkippedEntity();
                            if (!empty($skipped)) {
                                $objectId = array();
                                foreach ($skipped as $entity_id) {
                                    $objectId[] = @$idSet[array_search($entity_id, $objectIdSet)];
                                }

                                Mage::getModel('tnw_salesforce/localstorage')
                                    ->deleteObject($objectId, true);
                            }

                            // Update Queue
                            Mage::getModel('tnw_salesforce/localstorage')
                                ->updateQueue($objectIdSet, $idSet, $manualSync->getSyncResults(), $manualSync->getAlternativeKeys());
                        }
                    } else {
                        /**
                         * @var $manualSync TNW_Salesforce_Helper_Bulk_Product|TNW_Salesforce_Helper_Bulk_Customer|TNW_Salesforce_Helper_Bulk_Website
                         */
                        $manualSync = Mage::helper('tnw_salesforce/bulk_' . $type);
                        if ($manualSync->reset()) {

                            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("################################## synchronization $type started ##################################");
                            // sync products with sf
                            $checkAdd = $manualSync->massAdd($objectIdSet);

                            // Delete Skipped Entity
                            $skipped  = $manualSync->getSkippedEntity();
                            if (!empty($skipped)) {
                                $objectId = array();
                                foreach ($skipped as $entity_id) {
                                    $objectId[] = @$idSet[array_search($entity_id, $objectIdSet)];
                                }

                                Mage::getModel('tnw_salesforce/localstorage')
                                    ->deleteObject($objectId, true);
                            }

                            if ($checkAdd) {
                                $manualSync->setIsCron(true);
                                $manualSync->process();
                            }

                            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("################################## synchronization $type finished ##################################");

                            // Update Queue
                            Mage::getModel('tnw_salesforce/localstorage')
                                ->updateQueue($objectIdSet, $idSet, $manualSync->getSyncResults(), $manualSync->getAlternativeKeys());
                        }
                        else {
                            Mage::getModel('tnw_salesforce/localstorage')->updateObjectStatusById($idSet, 'new');
                            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("error: salesforce connection failed");
                            return;
                        }
                    }
                }
            });
        }
    }

    /**
     * fetch customer ids from local storage and sync products with sf
     *
     * @return bool
     */
    public function syncCustomer()
    {
        try {
            $this->syncEntity('customer');
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("ERROR: customer not synced: %s", $e->getMessage()));
            return false;
        }

        return true;
    }

    /**
     * fetch order ids from local storage and sync products with sf
     *
     * @return bool
     */
    public function syncOrder()
    {
        try {
            $this->syncEntity('order');
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("ERROR: order not synced: %s", $e->getMessage()));
            return false;
        }

        return true;
    }

    /**
     * fetch invoice ids from local storage and sync with sf
     *
     * @return bool
     */
    public function syncInvoices()
    {
        try {
            $this->syncEntity('invoice');
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("ERROR: order not synced: %s", $e->getMessage()));
            return false;
        }

        return true;
    }

    /**
     * fetch shipment ids from local storage and sync with sf
     *
     * @return bool
     */
    public function syncShipment()
    {
        try {
            $this->syncEntity('shipment');
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("ERROR: shipment not synced: %s", $e->getMessage()));
            return false;
        }

        return true;
    }

    /**
     * fetch credit memo ids from local storage and sync with sf
     *
     * @return bool
     */
    public function syncCreditMemo()
    {
        try {
            $this->syncEntity('creditmemo');
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("ERROR: creditmemo not synced: %s", $e->getMessage()));
            return false;
        }

        return true;
    }

    /**
     * fetch shipment ids from local storage and sync with sf
     *
     * @return bool
     */
    public function syncSalesRule()
    {
        try {
            $this->syncEntity('campaign_salesrule');
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("ERROR: SalesRule not synced: %s", $e->getMessage()));
            return false;
        }

        return true;
    }

    /**
     * fetch CatalogRule ids from local storage and sync with sf
     *
     * @return bool
     */
    public function syncCatalogRule()
    {
        try {
            $this->syncEntity('campaign_catalogrule');
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("ERROR: CatalogRule not synced: %s", $e->getMessage()));
            return false;
        }

        return true;
    }

    /**
     * fetch Wishlist ids from local storage and sync with sf
     *
     * @return bool
     */
    public function syncWishlist()
    {
        try {
            $this->syncEntity('wishlist');
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf('ERROR: Wishlist not synced: %s', $e->getMessage()));
            return false;
        }

        return true;
    }

    /**
     * fetch abandoned cart ids from local storage and sync products with sf
     *
     * @return bool
     */
    public function syncAbandoned()
    {
        try {
            $this->syncEntity('abandoned');
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("ERROR: order not synced: %s", $e->getMessage()));
            return false;
        }

        return true;
    }

    /**
     * fetch product ids from local storage and sync products with sf
     *
     * @return bool
     */
    public function syncProduct()
    {
        try {
            $this->syncEntity('product');
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("ERROR: product not synced: %s", $e->getMessage()));
            return false;
        }

        return true;
    }

    public function entityCacheFill()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('=== Fill magento cache START ===');

        foreach (Mage::helper('tnw_salesforce/config')->getWebsitesDifferentConfig() as $website) {
            Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function() {
                if (!Mage::helper('tnw_salesforce')->isEnabled()) {
                    return;
                }

                Mage::getSingleton('tnw_salesforce/sforce_entity_cache')->importFromSalesforce();
            });
        }

        Mage::dispatchEvent('tnw_salesforce_cron_after', array('observer' => $this, 'method' => 'entityCacheFill'));
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('=== Fill magento cache END ===');
    }
}