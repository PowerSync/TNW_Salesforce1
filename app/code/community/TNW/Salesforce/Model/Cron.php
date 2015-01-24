<?php

/**
 * Class TNW_Salesforce_Model_Cron
 */
class TNW_Salesforce_Model_Cron extends TNW_Salesforce_Helper_Abstract
{
    const ORDER_BATCH_SIZE = 500;
    const PRODUCT_BATCH_SIZE = 500;
    const CUSTOMER_BATCH_SIZE = 1000;
    const WEBSITE_BATCH_SIZE = 500;
    /**
     * @var array
     */
    protected $_data = array();

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
    private function _isTimeToRun()
    {
        Mage::helper('tnw_salesforce')->log('========================= cron method _isTimeToRun() started =========================');
        Mage::helper('tnw_salesforce')->log('cron time (it differs from php timezone) '.date("Y-m-d H:i:s"));

        $syncType = Mage::helper('tnw_salesforce')->getObjectSyncType();
        $lastRunTime = ($this->_useCache && unserialize($this->_mageCache->load('tnw_salesforce_cron_timestamp'))) ? unserialize($this->_mageCache->load('tnw_salesforce_cron_timestamp')) : 0;
        switch ($syncType) {
            case 'sync_type_queue_interval':
                $configIntervalSeconds = (int)Mage::helper('tnw_salesforce')->getObjectSyncIntervalValue();
                if ((Mage::getModel('core/date')->timestamp(time()) - $lastRunTime) >= ($configIntervalSeconds - 60)) {

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
                Mage::helper('tnw_salesforce')->log((Mage::getModel('core/date')->timestamp(time()) - $lastRunTime) >= $configFrequencySeconds);
                Mage::helper('tnw_salesforce')->log(intval(date("H")) == intval($configTimeHour));
                Mage::helper('tnw_salesforce')->log(abs(intval(date("i")) - intval($configTimeMinute)) < $this->_cronRunIntervalMinute);

                if ((Mage::getModel('core/date')->timestamp(time()) - $lastRunTime) >= $configFrequencySeconds
                    && intval(date("H")) == intval($configTimeHour)
                    && abs(intval(date("i")) - intval($configTimeMinute)) <= $this->_cronRunIntervalMinute) {
                    // it's time for cron
                    if ($configFrequencySeconds <= 60 * 60 * 24) {
                        // daily
                        Mage::helper('tnw_salesforce')->log('daily cron started');

                        return true;
                    }
                    elseif ($configFrequencySeconds <= 60 * 60 * 24 * 7) {
                        // weekly
                        Mage::helper('tnw_salesforce')->log('weekly cron started');
                        // check day of week
                        $curWeekDay = Mage::helper('tnw_salesforce')->getObjectSyncSpectimeFreqWeekday();
                        $isTime = date("l", time()) == $curWeekDay ? true : false;
                        Mage::helper('tnw_salesforce')->log("isTime = $isTime");

                        return $isTime;
                    }
                    else {
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

    /**
     * this method is called instantly from cron script
     *
     * @return bool
     */
    public function backgroundProcess()
    {
        $this->_initCache();
        $this->_reset();

        Mage::getModel('tnw_salesforce/feed')->checkUpdate();
        Mage::getModel('tnw_salesforce/imports_bulk')->process();


        Mage::helper('tnw_salesforce')->log("Powersync background process for store (" . Mage::helper('tnw_salesforce')->getStoreId() . ") and website id (" . Mage::helper('tnw_salesforce')->getWebsiteId() . ") ...", 1, 'sf-cron');

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
            $this->_mageCache->save(serialize(Mage::getModel('core/date')->timestamp(time())), "tnw_salesforce_cron_timestamp", array("TNW_SALESFORCE"));
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

            // check if all products and customers synced with sf
            // TODO: Add check to only sync orders and quotes if customer and all products from the order are in Sync
            $total = Mage::getModel('tnw_salesforce/localstorage')->countObjectBySfType(array(
                'Product',
                'Customer',
            ));
            // we still have products or customers in localstorage, thus skip below
            if ($total == 0) {
                // Sync orders
                $this->syncOrder();

                $this->_syncCustomObjects();
            }
        } else {
            Mage::helper('tnw_salesforce')->log("ERROR: Server Name is undefined!", 1, 'sf-cron');
        }

        return;
    }

    public function _syncCustomObjects() {
        // Implemented for customization
    }

    public function _syncWebsites()
    {
        try {
            // get customer id list from local storage
            $list = Mage::getModel('tnw_salesforce/queue_storage')->getCollection()
                ->addSftypeToFilter('Website')
                ->addStatusNoToFilter('sync_running')
                ->addStatusNoToFilter('sync_error');
            $list->getSelect()->limit(self::WEBSITE_BATCH_SIZE); // set limit to avoid memory leak

            if (count($list) > 0) {
                $manualSync = Mage::helper('tnw_salesforce/salesforce_website');
                if ($manualSync->reset()) {
                    $manualSync->setIsCron(true);
                    $manualSync->setSalesforceServerDomain(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_url'));
                    $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));

                    $idSet = array();
                    $objectIdSet = array();
                    foreach ($list->getData() as $item) {
                        $idSet[] = $item['id'];
                        $objectIdSet[] = $item['object_id'];
                    }

                    if (!empty($objectIdSet)) {
                        // set status to 'sync_running'
                        Mage::getModel('tnw_salesforce/localstorage')->updateObjectStatusById($idSet);

                        Mage::helper('tnw_salesforce')->log("################################## website synchronization started ##################################", 1, 'sf-cron');
                        // sync products with sf
                        $manualSync->massAdd($objectIdSet);
                        $manualSync->process();
                        Mage::helper('tnw_salesforce')->log("################################## website synchronization finished ##################################", 1, 'sf-cron');
                    }
                } else {
                    Mage::helper('tnw_salesforce')->log("error: salesforce connection failed", 1, 'sf-cron');
                    return false;
                }
            }
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("error: website not synced: " . $e->getMessage(), 1, 'sf-cron');
            return false;
        }

        // customer successfully synced
        if (!empty($idSet)) {
            Mage::helper('tnw_salesforce')->log("info: website total synced: " . count($idSet), 1, 'sf-cron');
            Mage::helper('tnw_salesforce')->log("info: removing synced rows from mysql table...", 1, 'sf-cron');
            // TODO: Need to only remove records that successfully synchronized
            Mage::getModel('tnw_salesforce/localstorage')->deleteObject($idSet);
        }

        return true;
    }

    /**
     * fetch customer ids from local storage and sync products with sf
     *
     * @return bool
     */
    public function syncCustomer()
    {
        try {
            // get customer id list from local storage
            $list = Mage::getModel('tnw_salesforce/queue_storage')->getCollection()
                ->addSftypeToFilter('Customer')
                ->addStatusNoToFilter('sync_running')
                ->addStatusNoToFilter('sync_error');
            $list->getSelect()->limit(self::CUSTOMER_BATCH_SIZE); // set limit to avoid memory leak

            if (count($list) > 0) {
                $manualSync = Mage::helper('tnw_salesforce/bulk_customer');
                if ($manualSync->reset()) {
                    $manualSync->setIsCron(true);
                    $manualSync->setSalesforceServerDomain(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_url'));
                    $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));

                    $idSet = array();
                    $objectIdSet = array();
                    foreach ($list->getData() as $item) {
                        $idSet[] = $item['id'];
                        $objectIdSet[] = $item['object_id'];
                    }

                    if (!empty($objectIdSet)) {
                        // set status to 'sync_running'
                        Mage::getModel('tnw_salesforce/localstorage')->updateObjectStatusById($idSet);

                        Mage::helper('tnw_salesforce')->log("################################## synchronization customer started ##################################", 1, 'sf-cron');
                        // sync products with sf
                        $manualSync->massAdd($objectIdSet);
                        $manualSync->process();
                        Mage::helper('tnw_salesforce')->log("################################## synchronization customer finished ##################################", 1, 'sf-cron');
                    }
                } else {
                    Mage::helper('tnw_salesforce')->log("error: salesforce connection failed", 1, 'sf-cron');
                    return false;
                }
            }
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("error: product not synced: " . $e->getMessage(), 1, 'sf-cron');
            return false;
        }

        // customer successfully synced
        if (!empty($idSet)) {
            Mage::helper('tnw_salesforce')->log("info: customer total synced: " . count($idSet), 1, 'sf-cron');
            Mage::helper('tnw_salesforce')->log("info: removing synced rows from mysql table...", 1, 'sf-cron');
            // TODO: Need to only remove records that successfully synchronized
            Mage::getModel('tnw_salesforce/localstorage')->deleteObject($idSet);
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
            // get order id list from local storage
            $list = Mage::getModel('tnw_salesforce/queue_storage')->getCollection()
                ->addSftypeToFilter('Order')
                ->addStatusNoToFilter('sync_running')
                ->addStatusNoToFilter('sync_error');
            $list->getSelect()->limit(self::ORDER_BATCH_SIZE); // set limit to avoid memory leak

            if (count($list) > 0) {
                $idSet = array();
                $objectIdSet = array();
                foreach ($list as $item) {
                    $idSet[] = $item->getData('id');
                    $objectIdSet[] = $item->getData('object_id');
                }

                if (!empty($objectIdSet)) {
                    // set status to 'sync_running'
                    Mage::getModel('tnw_salesforce/localstorage')->updateObjectStatusById($idSet);

                    Mage::dispatchEvent(
                        'tnw_sales_process_' . $_syncType,
                        array(
                            'orderIds'      => $objectIdSet,
                            'message'       => NULL,
                            'type'   => 'bulk'
                        )
                    );
                } else {
                    Mage::helper('tnw_salesforce')->log("could not get any Magneto entity Id's", 1, 'sf-cron');
                }
            }
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("ERROR: " . $e->getMessage(), 1, 'sf-cron');

            return false;
        }

        // order successfully synced
        if (!empty($idSet)) {
            Mage::helper('tnw_salesforce')->log("INFO: total orders synced: " . count($idSet), 1, 'sf-cron');
            Mage::helper('tnw_salesforce')->log("INFO: removing synced records from the queue...", 1, 'sf-cron');
            // TODO: Need to only remove records that successfully synchronized
            Mage::getModel('tnw_salesforce/localstorage')->deleteObject($idSet);
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
            $syncResult = false;
            // get product id list from local storage
            $productList = Mage::getModel('tnw_salesforce/queue_storage')->getCollection()
                ->addSftypeToFilter('Product')
                ->addStatusNoToFilter('sync_running')
                ->addStatusNoToFilter('sync_error');
            $productList->getSelect()->limit(self::PRODUCT_BATCH_SIZE); // set limit to avoid memory leak

            if (count($productList) > 0) {
                $manualSync = Mage::helper('tnw_salesforce/bulk_product');
                if ($manualSync->reset()) {
                    $manualSync->setIsCron(true);
                    $manualSync->setSalesforceServerDomain(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_url'));
                    $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));

                    $idSet = array();
                    $objectIdSet = array();
                    foreach ($productList->getData() as $item) {
                        $idSet[] = $item['id'];
                        $objectIdSet[] = $item['object_id'];
                    }

                    if (!empty($objectIdSet)) {

                        // set status to 'sync_running'
                        Mage::getModel('tnw_salesforce/localstorage')->updateObjectStatusById($idSet);

                        Mage::helper('tnw_salesforce')->log("################################## synchronization product started ##################################", 1, 'sf-cron');
                        // sync products with sf
                        $manualSync->massAdd($objectIdSet);
                        $syncResult = $manualSync->process();
                        Mage::helper('tnw_salesforce')->log("################################## synchronization product finished ##################################", 1, 'sf-cron');
                    }
                } else {
                    Mage::helper('tnw_salesforce')->log("error: salesforce connection failed", 1, 'sf-cron');
                    return false;
                }
            }
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("error: product not synced: " . $e->getMessage(), 1, 'sf-cron');
            return false;
        }

        // product successfully synced
        if (!empty($idSet)) {
            if ($syncResult) {
                Mage::helper('tnw_salesforce')->log("info: products total processed: " . count($idSet), 1, 'sf-cron');
                Mage::helper('tnw_salesforce')->log("info: removing synced rows from mysql table...", 1, 'sf-cron');
                // TODO: Need to only remove records that successfully synchronized
                Mage::getModel('tnw_salesforce/localstorage')->deleteObject($idSet);
            } else {
                Mage::getModel('tnw_salesforce/localstorage')->updateObjectStatusById($idSet, 'new');
                Mage::helper('tnw_salesforce')->log("ERROR: product sync process failed");
            }
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
            $_foundAccounts = $manualSync->findCustomerAccountsForGuests($_customerArray);

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

            if ($leadConvert->leadId) {
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