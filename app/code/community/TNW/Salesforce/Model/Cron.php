<?php

/**
 * Class TNW_Salesforce_Model_Cron
 */
class TNW_Salesforce_Model_Cron
{
    const SYNC_TYPE_OUTGOING           = 'outgoing';
    const SYNC_TYPE_BULK               = 'bulk';
    const CRON_LAST_RUN_TIMESTAMP_PATH = 'salesforce/syncronization/cron_last_run_timestamp';

    /**
     * @var string
     */
    protected $_syncType = self::SYNC_TYPE_OUTGOING;

    /**
     * @var null
     */
    protected $_serverName = NULL;

    /**
     * cron run interval in minutes value
     * by default it's 5 minutes
     *
     * @var int
     */
    private $_cronRunIntervalMinute = 5;

    /**
     * we check sf sync type settings and decide if it's time to run cron
     *
     * @return bool
     */
    public function _isTimeToRun()
    {
        /** @var TNW_Salesforce_Helper_Data $_helperData */
        $_helperData = Mage::helper('tnw_salesforce');

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('========================= cron method _isTimeToRun() started =========================');
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('cron time (it differs from php timezone) %s', $_helperData->getDate(NULL, false)));

        $lastRunTime = (int)Mage::getStoreConfig(self::CRON_LAST_RUN_TIMESTAMP_PATH);

        $syncType = $_helperData->getObjectSyncType();
        switch ($syncType) {
            case 'sync_type_queue_interval':
                $configIntervalSeconds = (int)$_helperData->getObjectSyncIntervalValue();
                return ($_helperData->getTime() - $lastRunTime) >= ($configIntervalSeconds - 60);

            case 'sync_type_spectime':
                /**
                 * here we check if Frequency period passed,
                 * then if time hour == current hour,
                 * then if module diff between time minute and current minute less then 5 mins
                 * and then start cron.
                 * the cron start time inaccuracy is between 1 - 5 minutes
                 */
                $configFrequencySeconds = $_helperData->getObjectSyncSpectimeFreq();
                $configTimeHour         = (int)$_helperData->getObjectSpectimeHour();
                $configTimeMinute       = (int)$_helperData->getObjectSpectimeMinute();

                // log some help info in case we have claim from customer regarding cron job
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(($_helperData->getTime() - $lastRunTime) >= $configFrequencySeconds - 60);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(intval(date("H")) == intval($configTimeHour));
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(abs(intval(date("i")) - intval($configTimeMinute)) < $this->_cronRunIntervalMinute);

                if (($_helperData->getTime() - $lastRunTime) >= ($configFrequencySeconds - 60)
                    && intval(date("H")) == intval($configTimeHour)
                    && abs(intval(date("i")) - intval($configTimeMinute)) <= $this->_cronRunIntervalMinute
                ) {
                    // it's time for cron
                    if ($configFrequencySeconds <= 60 * 60 * 24) {
                        // daily
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('daily cron started');

                        return true;
                    } elseif ($configFrequencySeconds <= 60 * 60 * 24 * 7) {
                        // weekly
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('weekly cron started');
                        // check day of week
                        $curWeekDay = $_helperData->getObjectSyncSpectimeFreqWeekday();
                        $isTime = date("l", time()) == $curWeekDay ? true : false;
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("isTime = $isTime");

                        return $isTime;
                    } else {
                        // monthly
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('monthly cron started');
                        // check date (we run cron on 1st day of month)
                        $curMonthDay = $_helperData->getObjectSyncSpectimeFreqMonthday();
                        $isTime = intval(date("j", time())) == intval($curMonthDay) ? true : false;
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("isTime = $isTime");

                        return $isTime;
                    }
                }

                return false;

            default:
                return false;
        }
    }

    public function backgroundProcess()
    {
        set_time_limit(0);
        Mage::getModel('tnw_salesforce/feed')->checkUpdate();

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Check Salesforce to Magento queue ...");
        Mage::getModel('tnw_salesforce/imports_bulk')->process();
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Check Salesforce to Magento queue ... done");
        Mage::dispatchEvent('tnw_salesforce_cron_after', array('observer' => $this, 'method' => 'backgroundProcess'));

    }

    /**
     * @comment add Abandoned carts to quote for synchronization
     */
    public function addAbandonedToQueue()
    {
        if (!Mage::helper('tnw_salesforce/config_sales_abandoned')->isEnabled()) {
            return false;
        }

        /** @var $collection Mage_Reports_Model_Resource_Quote_Collection */
        $collection = Mage::getModel('tnw_salesforce/abandoned')->getAbandonedCollection();
        $collection->addFieldToFilter('sf_sync_force', 1);
        $collection->addFieldToFilter('customer_id', array('neq' => 'NULL' ));

        $itemIds = $collection->getAllIds();
        if (empty($itemIds)) {
            return false;
        }

        /** @var TNW_Salesforce_Model_Mysql4_Quote_Item_Collection $_collection */
        $_collection = Mage::getResourceModel('tnw_salesforce/quote_item_collection')
            ->addFieldToFilter('quote_id', array('in' => $itemIds));

        $productIds = $_collection->walk('getProductId');

        /** @var TNW_Salesforce_Model_Localstorage $localstorage */
        $localstorage = Mage::getModel('tnw_salesforce/localstorage');
        $localstorage->addObjectProduct(array_unique($productIds), 'Product', 'product');

        foreach (array_chunk($itemIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT) as $_chunk) {
            $localstorage->addObject($_chunk, 'Abandoned', 'abandoned');
            $bind = array(
                'sf_sync_force' => 0
            );

            $where = array(
                'entity_id IN (?)' => $_chunk
            );

            Mage::helper('tnw_salesforce')->getDbConnection('write')
                ->update(Mage::getResourceModel('sales/quote')->getMainTable(), $bind, $where);

        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(
            sprintf("Powersync background process for store (%s) and website id (%s): abandoned added to the queue",
                Mage::helper('tnw_salesforce')->getStoreId(), Mage::helper('tnw_salesforce')->getWebsiteId()));

        Mage::dispatchEvent('tnw_salesforce_cron_after', array('observer' => $this, 'method' => 'addAbandonedToQueue'));

        return true;
    }

    /**
     * @comment Auto Currency Sync
     */
    public function syncCurrency()
    {
        /** @var TNW_Salesforce_Helper_Data $_helperData */
        $_helperData = Mage::helper('tnw_salesforce');
        if (!$_helperData->isEnabled() || !$_helperData->isMultiCurrency()) {
            return;
        }

        $currencies = Mage::getModel('directory/currency')
            ->getConfigAllowCurrencies();

        try {
            $manualSync = Mage::helper('tnw_salesforce/salesforce_currency');
            if ($manualSync->reset() && $manualSync->massAdd($currencies) && $manualSync->process()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace($_helperData->__('%d Magento currency entities were successfully synchronized', count($currencies)));
            }
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError($e->getMessage());
        }
        Mage::dispatchEvent('tnw_salesforce_cron_after', array('observer' => $this, 'method' => 'syncCurrency'));
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
        $_helperData = Mage::helper('tnw_salesforce');
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf("PowerSync background process for store (%s) and website id (%s) ...",
            $_helperData->getStoreId(), $_helperData->getWebsiteId()));

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Queue updating ...");
        $this->_updateQueue();
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Queue updated!");

        // check if it's time to run cron
        if (!$_helperData->isEnabled()) {
            return;
        }

        $isRealtime = ($_helperData->getObjectSyncType() == 'sync_type_realtime');
        if (!$isRealtime && !$this->_isTimeToRun()) {
            return;
        }

        // cron is running now, thus save last cron run timestamp
        Mage::getModel('core/config_data')
            ->load(self::CRON_LAST_RUN_TIMESTAMP_PATH, 'path')
            ->setValue((int)$_helperData->getTime())
            ->setPath(self::CRON_LAST_RUN_TIMESTAMP_PATH)
            ->save();

        if ($isRealtime) {
            $this->_syncObjectForRealTimeMode();
        }
        else {
            $this->_syncObjectForBulkMode();
        }

        $this->_deleteSuccessfulRecords();
        Mage::dispatchEvent('tnw_salesforce_cron_after', array('observer' => $this, 'method' => 'processQueue'));
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

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf("PowerSync Bulk background process for store (%s) and website id (%s) ...",
            $_helperData->getStoreId(), $_helperData->getWebsiteId()));

        $this->_syncObjectForBulkMode();
        Mage::dispatchEvent('tnw_salesforce_cron_after', array('observer' => $this, 'method' => 'processBulkQueue'));
    }

    protected function _syncObjectForBulkMode()
    {
        Mage::dispatchEvent('tnw_salesforce_cron_sync_object_bulk_before', array('cron_object' => $this));

        // Sync Products
        $this->syncProduct();

        // Sync Customers
        $this->syncCustomer();

        // Synchronize Websites
        $this->_syncWebsites();

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
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Synchronized records removed from the queue ...");
    }

    protected function _resetStuckRecords()
    {
        $syncType = Mage::helper('tnw_salesforce')->getObjectSyncType();
        $_whenToReset = 0;
        switch ($syncType) {
            case 'sync_type_queue_interval':
                $configIntervalSeconds = (int)Mage::helper('tnw_salesforce')->getObjectSyncIntervalValue();
                $_whenToReset = Mage::helper('tnw_salesforce')->getTime() - ($configIntervalSeconds + TNW_Salesforce_Model_Config_Frequency::INTERVAL_BUFFER);
                break;
            case 'sync_type_spectime':
                // TODO: calculate when to reset the flag
                $_whenToReset = 0;
                break;
            default:
                break;
        }

        $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . "` SET status = '' WHERE status = 'sync_running' AND date_created < '" . Mage::helper('tnw_salesforce')->getDate($_whenToReset) . "';";
        Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Trying to reset any stuck records ...");
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

        // get entity id list from local storage
        /** @var TNW_Salesforce_Model_Mysql4_Queue_Storage_Collection $list */
        $list = Mage::getResourceModel('tnw_salesforce/queue_storage_collection')
            ->addSftypeToFilter($type)
            ->addSyncAttemptToFilter()
            ->addStatusNoToFilter('sync_running')
            ->addStatusNoToFilter('success')
            ->addFieldToFilter('sync_type', array('eq' => $this->_syncType))
            ->setOrder('status', 'ASC')    // Leave 'error' at the end of the collection
        ;

        $page = 0;
        $lPage = null;
        $break = false;

        $list->setPageSize($this->getBatchSize($type));
        $lPage = $list->getLastPageNumber();

        $idsSet = array();

        while ($break !== true) {

            try {

                $page++;

                if ($lPage == $page) {
                    $break = true;
                }

                $list->clear();
                $list->setCurPage(1);
                $list->load();

                if (count($list) > 0) {

                    $idSet = array();
                    $objectIdSet = array();
                    foreach ($list->getData() as $item) {
                        $_skip = false;
                        if ($type == 'order' || $type == 'abandoned') {

                            $_entityModel = $type == 'order' ? 'sales/order' : 'sales/quote';
                            /** @var Mage_Sales_Model_Order|Mage_Sales_Model_Quote $_entity */
                            $_entity = Mage::getModel($_entityModel)->load($item['object_id']);

                            //check dependencies
                            $_dependencies = Mage::getModel('tnw_salesforce/localstorage')->getAllDependencies();
                            if ($_entity->getCustomerId() && isset($_dependencies['Customer'])
                                && in_array($_entity->getCustomerId(), $_dependencies['Customer'])
                            ) {
                                $_skip = true;
                            }

                            if (!$_skip && isset($_dependencies['Product'])) {
                                /** @var Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item $_item */
                                foreach ($_entity->getAllVisibleItems() as $_item) {
                                    $id = $_item instanceof Mage_Sales_Model_Order_Item
                                        ? Mage::helper('tnw_salesforce/salesforce_order')->getProductIdFromCart($_item)
                                        : Mage::helper('tnw_salesforce/salesforce_abandoned_opportunity')->getProductIdFromCart($_item);

                                    if (in_array($id, $_dependencies['Product'])) {
                                        $_skip = true;
                                        break;
                                    }
                                }
                            }
                        }

                        if (!$_skip) {
                            $idSet[] = $item['id'];
                            $objectIdSet[] = $item['object_id'];
                        }
                    }

                    $idsSet = array_merge($idSet, $idsSet);

                    if (!empty($objectIdSet)) {
                        // set status to 'sync_running'
                        Mage::getModel('tnw_salesforce/localstorage')->updateObjectStatusById($idSet);

                        $eventTypes = array(
                            'order',
                            'abandoned',
                            'invoice',
                            'shipment',
                            'creditmemo',
                        );

                        if (in_array($type, $eventTypes)) {
                            $_prefix = 'order';

                            switch ($type) {
                                case 'order':
                                    $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
                                    break;
                                case 'abandoned':
                                    $_syncType = strtolower(Mage::helper('tnw_salesforce')->getAbandonedObject());
                                    break;
                                case 'invoice':
                                    $_syncType = strtolower(Mage::helper('tnw_salesforce')->getInvoiceObject());
                                    $_prefix = 'invoice';
                                    break;
                                case 'shipment':
                                    $_syncType = strtolower(Mage::helper('tnw_salesforce')->getShipmentObject());
                                    $_prefix = 'shipment';
                                    break;
                                case 'creditmemo':
                                    $_syncType = strtolower(Mage::helper('tnw_salesforce')->getCreditmemoObject());
                                    $_prefix = 'creditmemo';
                                    break;
                                default:
                                    $_syncType = $type;
                            }

                            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf("Processing %s: %s records", $type, count($objectIdSet)));

                            Mage::dispatchEvent(
                                sprintf('tnw_salesforce_%s_process', $_syncType),
                                array(
                                    $_prefix . 'Ids' => $objectIdSet,
                                    'message' => NULL,
                                    'type' => 'bulk',
                                    'isQueue' => true,
                                    'queueIds' => $idSet,
                                    'object_type' => $type
                                )
                            );
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
                } else {
                    $break = true;
                }

            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("ERROR: %s not synced: %s", $type, $e->getMessage()));
            }
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
        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
        if (!$_syncType) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING: Integration Type is not set for the order object.');
            return false;
        }

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
     * fetch abandoned cart ids from local storage and sync products with sf
     *
     * @return bool
     */
    public function syncAbandoned()
    {

        if (!Mage::helper('tnw_salesforce/config_sales_abandoned')->isEnabled()) {
            return false;
        }

        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getAbandonedObject());
        if (!$_syncType) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING: Integration Type is not set for the abandoned object.');
            return false;
        }

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
}