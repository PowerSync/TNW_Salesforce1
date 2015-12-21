<?php

/**
 * Class TNW_Salesforce_Model_Cron
 */
class TNW_Salesforce_Model_Cron
{
    /**
     * @var array
     */
    protected $_productIds = array();

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

        $lastRunTime = 0;
        if ($_helperData->useCache()) {
            $cache = $_helperData->getCache()->load('tnw_salesforce_cron_timestamp');
            $cache = @unserialize($cache);

            if ($cache) {
                $lastRunTime = (int)$cache;
            }
        }

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
                return true;
        }
    }

    public function backgroundProcess()
    {
        set_time_limit(0);
        Mage::getModel('tnw_salesforce/feed')->checkUpdate();

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Check Salesforce to Magento queue ...");
        Mage::getModel('tnw_salesforce/imports_bulk')->process();
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Check Salesforce to Magento queue ... done");
    }

    /**
     * @param $_args
     */
    public function cartItemsCallback($_args)
    {
        /** @var Mage_Catalog_Model_Product $_product */
        $_product = Mage::getModel('catalog/product');
        $_product->setData($_args['row']);
        $_id = (int)$this->_getProductIdFromCart($_product);
        if (!in_array($_id, $this->_productIds)) {
            $this->_productIds[] = $_id;
        }
    }

    /**
     * @return mixed
     */
    public function getProductIds()
    {
        return $this->_productIds;
    }

    /**
     * @param $_item Mage_Sales_Model_Quote_Item|Mage_Catalog_Model_Product
     * @return int
     */
    protected function _getProductIdFromCart($_item)
    {
        $_options = unserialize($_item->getData('product_options'));
        if (
            $_item->getData('product_type') == 'bundle'
            || (is_array($_options) && array_key_exists('options', $_options))
        ) {
            $id = $_item->getData('product_id');
        } else {
            $id = (int)Mage::getModel('catalog/product')->getIdBySku($_item->getSku());
        }
        return $id;
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
        $collection->addFieldToSelect('sf_insync');
        $itemIds = $collection->getAllIds();

        if (empty($itemIds)) {
            return false;
        }

        $_collection = Mage::getResourceModel('sales/quote_item_collection');
        $_collection->getSelect()->reset(Zend_Db_Select::COLUMNS)
            ->columns(array('sku', 'quote_id', 'product_id', 'product_type'))
            ->where(new Zend_Db_Expr('quote_id IN (' . join(',', $itemIds) . ')'));

        /**
         * @var $controller TNW_Salesforce_Adminhtml_Salesforcesync_AbandonedsyncController
         */

        Mage::getSingleton('core/resource_iterator')->walk(
            $_collection->getSelect(),
            array(array($this, 'cartItemsCallback'))
        );

        $_productChunks = array_chunk($this->_productIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT);
        $localstorage = Mage::getModel('tnw_salesforce/localstorage');

        foreach ($_productChunks as $_chunk) {
            $localstorage->addObject($itemIds, 'Product', 'product');
        }

        $_chunks = array_chunk($itemIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT);
        unset($itemIds, $_chunk);
        foreach ($_chunks as $_chunk) {
            $localstorage->addObject($_chunk, 'Abandoned', 'abandoned');
            $bind = array(
                'sf_sync_force' => 0
            );

            $where = array(
                'entity_id IN (?)' => $_chunk
            );

            $this->getDbConnection('write')
                ->update(Mage::getResourceModel('sales/quote')->getMainTable(), $bind, $where);

        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(
            sprintf("Powersync background process for store (%s) and website id (%s): abandoned added to the queue",
                Mage::helper('tnw_salesforce')->getStoreId(), Mage::helper('tnw_salesforce')->getWebsiteId()));

        return true;
    }

    /**
     * this method is called instantly from cron script
     *
     * @return bool
     */
    public function processQueue()
    {
        /** @var TNW_Salesforce_Helper_Data $_helperData */
        $_helperData = Mage::helper('tnw_salesforce');

        set_time_limit(0);
        @define('PHP_SAPI', 'cli');

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf("PowerSync background process for store (%s) and website id (%s) ...",
            $_helperData->getStoreId(), $_helperData->getWebsiteId()));

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Queue updating ...");
        $this->_updateQueue();
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Queue updated!");

        // check if it's time to run cron
        if ($_helperData->isEnabled() && !$this->_isTimeToRun()) {
            return;
        }

        // cron is running now, thus save last cron run timestamp
        if ($_helperData->useCache()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('cron is using cache for last run timestamp');
            $_helperData->getCache()->save(serialize($_helperData->getTime()), "tnw_salesforce_cron_timestamp", array("TNW_SALESFORCE"));
        }

        // Force SF connection if session is expired or not found
        $_urlArray = explode('/', Mage::app()->getStore($_helperData->getStoreId())->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB));
        $this->_serverName = (array_key_exists('2', $_urlArray)) ? $_urlArray[2] : NULL;
        if ($this->_serverName) {
            if (
                !Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id')
                || !Mage::getSingleton('core/session')->getSalesforceUrl()
            ) {
                $_license = Mage::getSingleton('tnw_salesforce/license')->forceTest($this->_serverName);
                if ($_license) {
                    /** @var TNW_Salesforce_Model_Connection $_client */
                    $_client = Mage::getSingleton('tnw_salesforce/connection');

                    // try to connect
                    if (
                        !$_client->initConnection()
                    ) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: login to salesforce api failed, sync process skipped");
                        return;
                    }
                    else {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("INFO: login to salesforce api - OK");
                    }
                }
            }

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

            // Sync custom objects
            $this->_syncCustomObjects();

            $this->_deleteSuccessfulRecords();
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Server Name is undefined!");
        }
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
            default:
                throw new Exception('Incorrect entity type, no batch size for "' . $type . '" type');
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
        $list = Mage::getModel('tnw_salesforce/queue_storage')->getCollection()
            ->addSftypeToFilter($type)
            ->addSyncAttemptToFilter()
            ->addStatusNoToFilter('sync_running')
            ->addStatusNoToFilter('success')
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
                            $_entity = Mage::getModel($_entityModel)->load($item['object_id']);

                            //check dependencies
                            $_dependencies = Mage::getModel('tnw_salesforce/localstorage')->getAllDependencies();
                            if ($_entity->getCustomerId() && isset($_dependencies['Customer'])
                                && in_array($_entity->getCustomerId(), $_dependencies['Customer'])
                            ) {
                                $_skip = true;
                            }

                            if (!$_skip && isset($_dependencies['Product'])) {
                                foreach ($_entity->getAllVisibleItems() as $_item) {
                                    $id = $this->_getProductIdFromCart($_item);
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
                                    if (TNW_Salesforce_Model_Order_Invoice_Observer::OBJECT_TYPE == $_syncType) {
                                        // Skip native, only allow customization at the moment
                                        return;
                                    }

                                    $_prefix = 'invoice';
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
                                $testAuth = Mage::helper('tnw_salesforce/test_authentication');
                                $manualSync->setSalesforceServerDomain($testAuth->getStorage('salesforce_url'));
                                $manualSync->setSalesforceSessionId($testAuth->getStorage('salesforce_session_id'));

                                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("################################## synchronization $type started ##################################");
                                // sync products with sf
                                $manualSync->massAdd($objectIdSet);
                                $manualSync->setIsCron(true);
                                $manualSync->process();
                                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("################################## synchronization $type finished ##################################");

                                // Update Queue
                                $_results = $manualSync->getSyncResults();
                                Mage::getModel('tnw_salesforce/localstorage')->updateQueue($objectIdSet, $idSet, $_results);
                            } else {
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