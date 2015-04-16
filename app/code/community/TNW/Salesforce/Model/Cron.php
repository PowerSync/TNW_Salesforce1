<?php

/**
 * Class TNW_Salesforce_Model_Cron
 */
class TNW_Salesforce_Model_Cron extends TNW_Salesforce_Helper_Abstract
{
    const ORDER_BATCH_SIZE = 500;
    const ABANDONED_BATCH_SIZE = 500;
    const INVOICE_BATCH_SIZE = 500;
    const PRODUCT_BATCH_SIZE = 500;
    const CUSTOMER_BATCH_SIZE = 1000;
    const WEBSITE_BATCH_SIZE = 500;

    /**
     *
     */
    const QUEUE_DELETE_LIMIT = 50;
    /**
     * @var array
     */
    protected $_data = array();

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
     * @var null
     */
    protected $_write = NULL;

    /**
     * we check sf sync type settings and decide if it's time to run cron
     *
     * @return bool
     */
    public function _isTimeToRun()
    {
        Mage::helper('tnw_salesforce')->log('========================= cron method _isTimeToRun() started =========================');
        Mage::helper('tnw_salesforce')->log('cron time (it differs from php timezone) ' . Mage::helper('tnw_salesforce')->getDate(NULL, false));

        $syncType = Mage::helper('tnw_salesforce')->getObjectSyncType();
        $lastRunTime = ($this->_useCache && unserialize($this->_mageCache->load('tnw_salesforce_cron_timestamp'))) ? unserialize($this->_mageCache->load('tnw_salesforce_cron_timestamp')) : 0;
        switch ($syncType) {
            case 'sync_type_queue_interval':
                $configIntervalSeconds = (int)Mage::helper('tnw_salesforce')->getObjectSyncIntervalValue();
                if ((Mage::helper('tnw_salesforce')->getTime() - $lastRunTime) >= ($configIntervalSeconds - 60)) {
                    return true;
                }

                return false;
                break;
            case 'sync_type_spectime':
                /**
                 * here we check if Frequency period passed,
                 * then if time hour == current hour,
                 * then if module diff between time minute and current minute less then 5 mins
                 * and then start cron.
                 * the cron start time inaccuracy is between 1 - 5 minutes
                 */
                $configFrequencySeconds = Mage::helper('tnw_salesforce')->getObjectSyncSpectimeFreq();
                $configTimeHour = (int)Mage::helper('tnw_salesforce')->getObjectSpectimeHour();
                $configTimeMinute = (int)Mage::helper('tnw_salesforce')->getObjectSpectimeMinute();

                // log some help info in case we have claim from customer regarding cron job
                Mage::helper('tnw_salesforce')->log((Mage::helper('tnw_salesforce')->getTime() - $lastRunTime) >= $configFrequencySeconds);
                Mage::helper('tnw_salesforce')->log(intval(date("H")) == intval($configTimeHour));
                Mage::helper('tnw_salesforce')->log(abs(intval(date("i")) - intval($configTimeMinute)) < $this->_cronRunIntervalMinute);

                if ((Mage::helper('tnw_salesforce')->getTime() - $lastRunTime) >= $configFrequencySeconds
                    && intval(date("H")) == intval($configTimeHour)
                    && abs(intval(date("i")) - intval($configTimeMinute)) <= $this->_cronRunIntervalMinute
                ) {
                    // it's time for cron
                    if ($configFrequencySeconds <= 60 * 60 * 24) {
                        // daily
                        Mage::helper('tnw_salesforce')->log('daily cron started');

                        return true;
                    } elseif ($configFrequencySeconds <= 60 * 60 * 24 * 7) {
                        // weekly
                        Mage::helper('tnw_salesforce')->log('weekly cron started');
                        // check day of week
                        $curWeekDay = Mage::helper('tnw_salesforce')->getObjectSyncSpectimeFreqWeekday();
                        $isTime = date("l", time()) == $curWeekDay ? true : false;
                        Mage::helper('tnw_salesforce')->log("isTime = $isTime");

                        return $isTime;
                    } else {
                        // monthly
                        Mage::helper('tnw_salesforce')->log('monthly cron started');
                        // check date (we run cron on 1st day of month)
                        $curMonthDay = Mage::helper('tnw_salesforce')->getObjectSyncSpectimeFreqMonthday();
                        $isTime = intval(date("j", time())) == intval($curMonthDay) ? true : false;
                        Mage::helper('tnw_salesforce')->log("isTime = $isTime");

                        return $isTime;
                    }
                }

                return false;
                break;
            default:

                return true;
                break;
        }

        return false;
    }

    public function backgroundProcess()
    {
        set_time_limit(0);

        $this->_initCache();
        $this->_reset();

        Mage::getModel('tnw_salesforce/feed')->checkUpdate();

        Mage::helper('tnw_salesforce')->log("Check Salesforce to Magento queue ...", 1, 'sf-cron');
        Mage::getModel('tnw_salesforce/imports_bulk')->process();
        Mage::helper('tnw_salesforce')->log("Check Salesforce to Magento queue ... done", 1, 'sf-cron');
    }

    /**
     * @param $_args
     */
    public function cartItemsCallback($_args)
    {
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
     * @param $_item Mage_Sales_Model_Quote_Item
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
        if (!Mage::helper('tnw_salesforce/abandoned')->isEnabled()) {
            return false;
        }

        /** @var $collection Mage_Reports_Model_Resource_Quote_Collection */
        $collection = Mage::getModel('tnw_salesforce/abandoned')->getAbandonedCollection();
        $collection->addFieldToFilter('sf_sync_force', 1);
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

        foreach ($_productChunks as $_chunk) {
            Mage::helper('tnw_salesforce/queue')->prepareRecordsToBeAddedToQueue($_chunk, 'Product', 'product');
        }

        $_chunks = array_chunk($itemIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT);
        unset($itemIds, $_chunk);
        foreach ($_chunks as $_chunk) {
            Mage::helper('tnw_salesforce/queue')->prepareRecordsToBeAddedToQueue($_chunk, 'Abandoned', 'abandoned');
            $bind = array(
                'sf_sync_force' => 0
            );

            $where = array(
                'entity_id IN (?)' => $_chunk
            );

            $this->getDbConnection('write')
                ->update(Mage::getResourceModel('sales/quote')->getMainTable(), $bind, $where);

        }

        Mage::helper('tnw_salesforce')
            ->log("Powersync background process for store (" . Mage::helper('tnw_salesforce')->getStoreId() . ") and website id (" . Mage::helper('tnw_salesforce')->getWebsiteId() . "): abandoned added to the queue", 1, 'sf-cron');

        return true;
    }

    /**
     * this method is called instantly from cron script
     *
     * @return bool
     */
    public function processQueue()
    {
        set_time_limit(0);

        $this->_initCache();
        $this->_reset();

        Mage::helper('tnw_salesforce')->log("PowerSync background process for store (" . Mage::helper('tnw_salesforce')->getStoreId() . ") and website id (" . Mage::helper('tnw_salesforce')->getWebsiteId() . ") ...", 1, 'sf-cron');

        Mage::helper('tnw_salesforce')->log("Queue updating ...", 1, 'sf-cron');
        $this->_updateQueue();
        Mage::helper('tnw_salesforce')->log("Queue updated!", 1, 'sf-cron');

        // check if it's time to run cron
        $isTime = $this->_isTimeToRun();
        if (
            Mage::helper('tnw_salesforce')->isEnabled()
            && !$isTime
        ) {
            return;
        }

        // cron is running now, thus save last cron run timestamp
        if ($this->_useCache) {
            Mage::helper('tnw_salesforce')->log('cron is using cache for last run timestamp');
            $this->_mageCache->save(serialize(Mage::helper('tnw_salesforce')->getTime()), "tnw_salesforce_cron_timestamp", array("TNW_SALESFORCE"));
        }

        // Force SF connection if session is expired or not found
        $_urlArray = explode('/', Mage::app()->getStore(Mage::helper('tnw_salesforce')->getStoreId())->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB));
        $this->_serverName = (array_key_exists('2', $_urlArray)) ? $_urlArray[2] : NULL;
        if ($this->_serverName) {
            if (
                !Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id')
                || !Mage::getSingleton('core/session')->getSalesforceUrl()
            ) {
                $_license = Mage::getSingleton('tnw_salesforce/license')->forceTest($this->_serverName);
                if ($_license) {
                    $_client = Mage::getSingleton('tnw_salesforce/connection');

                    // try to connect
                    if (
                        !$_client->tryWsdl()
                        || !$_client->tryToConnect()
                        || !$_client->tryToLogin()
                    ) {
                        Mage::helper('tnw_salesforce')->log("ERROR: login to salesforce api failed, sync process skipped");
                        return;
                    } else {
                        Mage::helper('tnw_salesforce')->log("INFO: login to salesforce api - OK");
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

            $this->syncInvoices();

            $this->_syncCustomObjects();

            $this->_deleteSuccessfulRecords();
        } else {
            Mage::helper('tnw_salesforce')->log("ERROR: Server Name is undefined!", 1, 'sf-cron');
        }

        return;
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
            Mage::helper('tnw_salesforce')->log("error: website not synced: " . $e->getMessage(), 1, 'sf-cron');
            return false;
        }

        return true;
    }

    public function getBatchSize($type)
    {
        $batchSize = 0;
        switch ($type) {
            case 'customer':
                $batchSize = self::CUSTOMER_BATCH_SIZE;
                break;
            case 'product':
                $batchSize = self::PRODUCT_BATCH_SIZE;
                break;
            case 'website':
                $batchSize = self::WEBSITE_BATCH_SIZE;
                break;
            case 'order':
                $batchSize = self::ORDER_BATCH_SIZE;
                break;
            case 'abandoned':
                $batchSize = self::ABANDONED_BATCH_SIZE;
                break;
            case 'invoice':
                $batchSize = self::INVOICE_BATCH_SIZE;
                break;
            default:
                throw new Exception('Incorrect entity type, no batch size for "' . $type . '" type');
                break;
        }

        return $batchSize;

    }

    /**
     * @param $_item
     * @return int
     * Get product Id from the cart
     */
    public function getProductIdFromCart($_item)
    {
        $_options = unserialize($_item->getData('product_options'));
        if (
            $_item->getData('product_type') == 'bundle'
            || array_key_exists('options', $_options)
        ) {
            $id = $_item->getData('product_id');
        } else {
            $id = (int)Mage::getModel('catalog/product')->getIdBySku($_item->getSku());
        }
        return $id;
    }

    /**
     * Delete synced records from the queue
     */
    protected function _deleteSuccessfulRecords()
    {
        $sql = "DELETE FROM `" . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . "` WHERE status = 'success';";
        Mage::helper('tnw_salesforce')->getDbConnection('delete')->query($sql);
        Mage::helper('tnw_salesforce')->log("Synchronized records removed from the queue ...", 1, 'sf-cron');
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
        Mage::helper('tnw_salesforce')->log("Trying to reset any stuck records ...", 1, 'sf-cron');
    }

    /**
     * Delete successful records, add new, reset stuck items
     */
    protected function _updateQueue()
    {
        // Add pending items into the queue
        $_collection = Mage::getModel('tnw_salesforce/queue')->getCollection();
        foreach ($_collection as $_pendingItem) {
            if ($_pendingItem->getData('mage_object_type') == 'product') {
                $_method = 'addObjectProduct';
            } else {
                $_method = 'addObject';
            }
            $_result = Mage::getModel('tnw_salesforce/localstorage')->{$_method}(unserialize($_pendingItem->getData('record_ids')), $_pendingItem->getData('sf_object_type'), $_pendingItem->getData('mage_object_type'));

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
        $list = Mage::getModel('tnw_salesforce/queue_storage')->getCollection()
            ->addSftypeToFilter($type)
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
                                    $id = $this->getProductIdFromCart($_item);
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

                            Mage::dispatchEvent(
                                'tnw_sales_process_' . $_syncType,
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

                                $this->log("################################## synchronization $type started ##################################", 1, 'sf-cron');
                                // sync products with sf
                                $manualSync->massAdd($objectIdSet);
                                $manualSync->setIsCron(true);
                                $manualSync->process();
                                $this->log("################################## synchronization $type finished ##################################", 1, 'sf-cron');

                                // Update Queue
                                $_results = $manualSync->getSyncResults();
                                Mage::getModel('tnw_salesforce/localstorage')->updateQueue($objectIdSet, $idSet, $_results);
                            } else {
                                $this->log("error: salesforce connection failed", 1, 'sf-cron');
                                return;
                            }
                        }
                    }
                } else {
                    $break = true;
                }

            } catch (Exception $e) {
                Mage::helper('tnw_salesforce')->log("error: $type not synced: " . $e->getMessage(), 1, 'sf-cron');
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
            Mage::helper('tnw_salesforce')->log("error: customer not synced: " . $e->getMessage(), 1, 'sf-cron');
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
            Mage::helper('tnw_salesforce')->log('SKIPPING: Integration Type is not set for the order object.');
            return false;
        }

        try {
            $this->syncEntity('order');
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("error: order not synced: " . $e->getMessage(), 1, 'sf-cron');
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
            Mage::helper('tnw_salesforce')->log("error: order not synced: " . $e->getMessage(), 1, 'sf-cron');
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

        if (!Mage::helper('tnw_salesforce/abandoned')->isEnabled()) {
            return false;
        }

        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getAbandonedObject());
        if (!$_syncType) {
            Mage::helper('tnw_salesforce')->log('SKIPPING: Integration Type is not set for the abandoned object.');
            return false;
        }

        try {
            $this->syncEntity('abandoned');
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("error: order not synced: " . $e->getMessage(), 1, 'sf-cron');
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
            Mage::helper('tnw_salesforce')->log("error: product not synced: " . $e->getMessage(), 1, 'sf-cron');
            return false;
        }

        return true;
    }

    /**
     * @param array $_customerArray
     */
    protected function _prepareConvertedLeads($_customerArray = array())
    {
        $manualSync = Mage::helper('tnw_salesforce/salesforce_customer');
        if ($manualSync->reset()) {
            $manualSync->setIsFromCLI(true);
            $manualSync->setSalesforceServerDomain(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_url'));
            $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));
            $_foundAccounts = $manualSync->findCustomerAccounts($_customerArray);

            $this->_prepareLeadConversionObject($_customerArray, $_foundAccounts);
        }
    }

    /**
     * @param $_toConvertCustomerIds
     * @param array $_accounts
     * @return bool
     */
    protected function _prepareLeadConversionObject($_toConvertCustomerIds, $_accounts = array())
    {
        if (!Mage::helper("tnw_salesforce")->getLeadConvertedStatus()) {
            Mage::helper("tnw_salesforce")->log('Converted Lead status is not set in the configuration, cannot proceed!', 1, "sf-errors");

            return false;
        }
        foreach ($_toConvertCustomerIds as $_id => $_email) {

            if ($this->_mageCache->load("tnw_salesforce_customer_" . $_id)) {
                $_customer = unserialize($this->_mageCache->load("tnw_salesforce_customer_" . $_id));
            } elseif (Mage::registry('customer_cached_' . $_id)) {
                $_customer = Mage::registry('customer_cached_' . $_id);
            } else {
                $_customer = Mage::getModel('customer/customer')->load($_id);
            }

            $_websiteId = $_customer->getData('website_id');

            $leadConvert = new stdClass;
            $leadConvert->convertedStatus = Mage::helper("tnw_salesforce")->getLeadConvertedStatus();
            $leadConvert->doNotCreateOpportunity = 'true';
            $leadConvert->leadId = $_customer->getData('salesforce_lead_id');
            $leadConvert->overwriteLeadSource = 'false';
            $leadConvert->sendNotificationEmail = 'false';

            if (Mage::helper('tnw_salesforce')->getLeadDefaultOwner()) {
                $leadConvert->ownerId = Mage::helper('tnw_salesforce')->getLeadDefaultOwner();
            }
            if (array_key_exists($_email, $_accounts) && $_accounts[$_email]) {
                $leadConvert->accountId = $_accounts[$_email];
            }

            // logs
            foreach ($leadConvert as $key => $value) {
                Mage::helper('tnw_salesforce')->log("Lead Conversion: " . $key . " = '" . $value . "'", 1, 'sf-cron');
            }

            if ($leadConvert->leadId && !$this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->IsConverted) {
                if (!array_key_exists('leadsToConvert', $this->_data)) {
                    $this->_data['leadsToConvert'] = array();
                }
                Mage::helper('tnw_salesforce')->log('lead conversion prepared for customer id: ' . $_id, 1, "sf-cron");
                $this->_data['leadsToConvert'][$_id] = $leadConvert;
            } else {
                Mage::helper("tnw_salesforce")->log('Customer (email: ' . $_email . ') could not be converted, Lead Id is missing in cached customer object!', 1, "sf-cron");
            }
        }
        return true;
    }

    /**
     * Used in TNW_Quote module
     *
     * @param $_toConvertCustomerIds
     */
    protected function _convertLeads($_toConvertCustomerIds)
    {
        //TODO: if more than 200 id's passed this call will fail. Add batching.
        if (!$this->_mySforceConnection) {
            Mage::helper("tnw_salesforce")->log('Salesforce connection cannot be established.', 1, "sf-cron");
        }
        $results = $this->_mySforceConnection->convertLead(array_values($this->_data['leadsToConvert']));
        $_keys = array_keys($this->_data['leadsToConvert']);
        foreach ($results->result as $_key => $_result) {
            $_customerId = $_keys[$_key];
            $_customerEmail = $_toConvertCustomerIds[$_customerId];
            if (!$_result->success) {
                Mage::helper('tnw_salesforce')->log('Convert Failed: (email: ' . $_customerEmail . ')', 1, "sf-cron");
                if ($_customerId) {
                    // Update Sync Status
                    Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, 0, 'sf_insync', 'customer_entity_int');
                }
                $this->_processErrors($_result, 'quote', $this->_data['leadsToConvert'][$_customerId]);
            } else {
                // Logs
                Mage::helper('tnw_salesforce')->log('Converted: (account: ' . $_result->accountId . ') and (contact: ' . $_result->contactId . ')', 1, 'sf-cron');

                // Update Magento
                // Update Salesforce Id
                $this->updateMagentoEntityValue($_customerId, $_result->contactId, 'salesforce_id', 'customer');
                // Update Account Id
                $this->updateMagentoEntityValue($_customerId, $_result->accountId, 'salesforce_account_id', 'customer');
                // Reset Lead Value
                $this->updateMagentoEntityValue($_customerId, NULL, 'salesforce_lead_id', 'customer');
                // Update Sync Status
                $this->updateMagentoEntityValue($_customerId, 1, 'sf_insync', 'customer', '_entity_int');

                Mage::helper('tnw_salesforce')->log('Magento upadated!');

                // Update Cache
                if ($this->_useCache) {
                    $_customer = unserialize($this->_mageCache->load("tnw_salesforce_customer_" . $_customerId));
                } else {
                    $_customer = Mage::registry('customer_cached_' . $_customerId);
                }
                $_customer
                    ->setSalesforceLeadId(NULL)
                    ->setSalesforceId($_result->contactId)
                    ->setSalesforceAccountId($_result->accountId)
                    ->setSfInsync(1);

                if ($this->_useCache) {
                    $this->_mageCache->save(serialize($_customer), "tnw_salesforce_customer_" . $_customerId, array("TNW_SALESFORCE"));
                } else {
                    if (Mage::registry('customer_cached_' . $_customerId)) {
                        Mage::unregister('customer_cached_' . $_customerId);
                    }
                    Mage::register('customer_cached_' . $_customerId, $_customer);
                }
                Mage::helper('tnw_salesforce')->log('Cache upadated!');
            }
        }
    }
}