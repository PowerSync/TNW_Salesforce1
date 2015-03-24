<?php

/**
 * Class TNW_Salesforce_Helper_Bulk_Customer
 */
class TNW_Salesforce_Helper_Bulk_Customer extends TNW_Salesforce_Helper_Salesforce_Customer
{
    /**
     * @var null
     */
    protected $_client = NULL;

    /**
     * @var array
     */
    protected $_allResults = array(
        'leads' => array(),
        'accounts' => array(),
        'contacts' => array(),
    );

    /**
     * @var array
     */
    protected $_orderCustomers = array();

    /**
     * @var array
     */
    protected $_allOrderCustomers = array();

    /**
     * @var array
     */
    protected $_toSyncOrderCustomers = array();

    /**
     * @param bool $_return
     * @return array|bool|mixed
     */
    public function process($_return = false)
    {
        if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
            Mage::helper('tnw_salesforce')->log("CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addWarning('WARNING: SKIPPING synchronization, could not establish Salesforce connection.');
            }

            /**
             * @comment restore server settings
             */
            $this->getServerHelper()->apply();

            return false;
        }
        Mage::helper('tnw_salesforce')->log("================ MASS SYNC: START ================");
        if (!is_array($this->_cache) || empty($this->_cache['entitiesUpdating'])) {
            Mage::helper('tnw_salesforce')->log("WARNING: Sync customers, cache is empty!");
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: SKIPPING synchronization, could not locate customer data to synchronize.');
            }

            /**
             * @comment restore server settings
             */
            $this->getServerHelper()->apply();

            return false;
        }

        try {
            // Prepare Data
            $this->_prepareLeads();
            $this->_prepareContacts();
            $this->_prepareNew();

            // Clean up the data we are going to be pushing in (for guest orders if multiple orders placed by the same person and they happen to end up in the same batch)
            $this->_deDupeCustomers();
            $this->clearMemory();

            // Push Data
            $this->_pushToSalesforce($_return);
            $this->clearMemory();

            // Update Magento
            if ($this->_customerEntityTypeCode) {
                $this->_updateMagento();
            } else {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Failed to update Magento with Salesforce Ids');
                }
                Mage::helper('tnw_salesforce')->log("WARNING: Failed to update Magento with Salesforce Ids. Try manual synchronization.", 2);
            }

            if ($_return && $_return == 'bulk') {
                // Update guest data from de duped records before passing it back to Order object
                $this->_updateGuestCachedData();

                $_i = 0;
                foreach ($this->_toSyncOrderCustomers as $_orderNum => $_customer) {
                    if ($_customer->getId()) {
                        $this->_orderCustomers[$_orderNum] = Mage::registry('customer_cached_' . $_customer->getId());
                    } else {
                        $this->_orderCustomers[$_orderNum] = $this->_cache['guestsFromOrder']['guest_' . $_i];
                    }
                    $_i++;
                }
            }
            $this->_onComplete();

            Mage::helper('tnw_salesforce')->log("================= MASS SYNC: END =================");
            if ($_return && $_return == 'bulk') {
                /**
                 * @comment restore server settings
                 */
                $this->getServerHelper()->apply();

                return $this->_orderCustomers;
            }
        } catch (Eception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log("CRITICAL: " . $e->getMessage());
        }

        /**
         * @comment restore server settings
         */
        $this->getServerHelper()->apply();
    }

    /**
     * work with sf response
     *
     * @param string $_on
     */
    protected function _assignLeadIds($_on = 'Id')
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        foreach ($this->_cache['batchCache']['leads'][$_on] as $_key => $_batchId) {
            $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['lead'][$_on] . '/batch/' . $_batchId . '/result');
            try {
                $response = $this->_client->request()->getBody();
                $response = simplexml_load_string($response);
                $_i = 0;
                $_batch = array_keys($this->_cache['batch']['leads'][$_on][$_key]);
                foreach ($response as $_item) {
                    $_cid = $_batch[$_i];
                    $_websiteId = $this->_getWebsiteIdByCustomerId($_cid);

                    // report transaction
                    $this->_cache['responses']['leads'][$_cid] = json_decode(json_encode($_item), TRUE);

                    $_email = $this->_cache['entitiesUpdating'][$_cid];
                    $_i++;

                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = NULL;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = NULL;
                    if ((string)$_item->success == "false") {
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId = NULL;
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 0;
                        $this->_processErrors($_item, 'lead', $this->_cache['batch']['leads'][$_on][$_key][$_cid]);
                        continue;
                    }
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId = (string)$_item->id;
                    if (array_key_exists($_cid, $this->_cache['guestsFromOrder'])) {
                        $this->_cache['guestsFromOrder'][$_cid]->setSalesforceLeadId($this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId);
                    }
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 1;
                }
            } catch (Exception $e) {
                // TODO:  Log error, quit
                $response = $e->getMessage();
            }
        }

        Mage::dispatchEvent("tnw_salesforce_lead_send_after",array(
            "data" => $this->_cache['leadsToUpsert'][$_on],
            "result" => $this->_cache['responses']['leads']
        ));
    }

    /**
     * @param string $_on
     */
    protected function _assignContactIds($_on = 'Id')
    {
        if (array_key_exists('contacts', $this->_cache['batchCache'])) {
            $this->_client->setMethod('GET');
            $this->_client->setHeaders('Content-Type: application/xml');
            $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

            foreach ($this->_cache['batchCache']['contacts'][$_on] as $_key => $_batchId) {
                $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['contact'][$_on] . '/batch/' . $_batchId . '/result');
                try {
                    $response = $this->_client->request()->getBody();
                    $response = simplexml_load_string($response);
                    $_i = 0;
                    $_batch = array_keys($this->_cache['batch']['contacts'][$_on][$_key]);
                    foreach ($response as $_item) {
                        $_cid = $_batch[$_i];
                        $_websiteId = $this->_getWebsiteIdByCustomerId($_cid);

                        //Report Transaction
                        $this->_cache['responses']['contacts'][$_cid] = json_decode(json_encode($_item), TRUE);

                        $_email = $this->_cache['entitiesUpdating'][$_cid];
                        $_i++;
                        if ((string)$_item->success == "false") {
                            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = NULL;
                            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId = NULL;
                            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 0;
                            $this->_processErrors($_item, 'contact', $this->_cache['batch']['contacts'][$_on][$_key][$_cid]);
                            continue;
                        }
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = (string)$_item->id;
                        if (
                            !$this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId
                            && is_array($this->_cache['accountsToContactLink'])
                            && array_key_exists($_cid, $this->_cache['accountsToContactLink'])
                            && $this->_cache['accountsToContactLink'][$_cid]
                        ) {
                            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = $this->_cache['accountsToContactLink'][$_cid];
                        }
                        if (array_key_exists($_cid, $this->_cache['guestsFromOrder'])) {
                            $this->_cache['guestsFromOrder'][$_cid]->setSalesforceId($this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId);
                            $this->_cache['guestsFromOrder'][$_cid]->setSalesforceAccountId($this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId);
                            if (property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'IsPersonAccount') && $this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount) {
                                $this->_cache['guestsFromOrder'][$_cid]->setSalesforceIsPerson($this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount);
                            }
                        }
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId = NULL;
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 1;
                    }
                } catch (Exception $e) {
                    // TODO:  Log error, quit
                    $response = $e->getMessage();
                }
            }

            Mage::dispatchEvent("tnw_salesforce_contact_send_after",array(
                "data" => $this->_cache['contactsToUpsert'][$_on],
                "result" => $this->_cache['responses']['contacts']
            ));
        }
    }

    /**
     * @param string $_on
     */
    protected function _assignAccountIds()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        foreach ($this->_cache['batchCache']['accounts']['Id'] as $_key => $_batchId) {
            $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['account']['Id'] . '/batch/' . $_batchId . '/result');
            try {
                $response = $this->_client->request()->getBody();
                $response = simplexml_load_string($response);
                $_i = 0;
                $_batch = array_keys($this->_cache['batch']['accounts']['Id'][$_key]);
                foreach ($response as $_item) {
                    $_cid = $_batch[$_i];
                    $_websiteId = $this->_getWebsiteIdByCustomerId($_cid);

                    // report Transaction
                    $this->_cache['responses']['accounts'][$_cid] = json_decode(json_encode($_item), TRUE);

                    $_email = $this->_cache['entitiesUpdating'][$_cid];
                    $_i++;
                    if ((string)$_item->success == "false") {
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = NULL;
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = NULL;
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId = NULL;
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 0;

                        $this->_processErrors($_item, 'account', $this->_cache['batch']['accounts']['Id'][$_key][$_cid]);
                        if (
                            array_key_exists('Id', $this->_cache['contactsToUpsert'])
                            && array_key_exists($_cid, $this->_cache['contactsToUpsert']['Id'])
                        ) {
                            unset($this->_cache['contactsToUpsert']['Id'][$_cid]);
                        }
                        if (
                            array_key_exists($this->_magentoId, $this->_cache['contactsToUpsert'])
                            && array_key_exists($_cid, $this->_cache['contactsToUpsert'][$this->_magentoId])
                        ) {
                            unset($this->_cache['contactsToUpsert'][$this->_magentoId][$_cid]);
                        }
                        continue;
                    } else {
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = (string)$_item->id;
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = NULL;
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId = NULL;
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 0;
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount = 0;

                        if (
                            array_key_exists($_cid, $this->_cache['accountsToUpsert']['Id'])
                            && property_exists($this->_cache['accountsToUpsert']['Id'][$_cid], 'PersonEmail')
                        ) {
                            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = (string)$_item->id;
                            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount = 1;
                            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 1;
                        }
                        if (
                            array_key_exists('Id', $this->_cache['contactsToUpsert'])
                            && array_key_exists($_cid, $this->_cache['contactsToUpsert']['Id'])
                            && !$this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount
                        ) {
                            $this->_cache['contactsToUpsert']['Id'][$_cid]->AccountId = (string)$_item->id;
                        } else if (
                            array_key_exists($this->_magentoId, $this->_cache['contactsToUpsert'])
                            && array_key_exists($_cid, $this->_cache['contactsToUpsert'][$this->_magentoId])
                            && !$this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount
                        ) {
                            $this->_cache['contactsToUpsert'][$this->_magentoId][$_cid]->AccountId = (string)$_item->id;
                        } else if (
                            array_key_exists('Id', $this->_cache['accountsToUpsert'])
                            && array_key_exists($_cid, $this->_cache['accountsToUpsert']['Id'])
                        ) {
                            // This is a Person Account
                            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = (string)$_item->id;
                            if (!$this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount) {
                                $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = (string)$_item->id;
                            }

                            if (array_key_exists($_cid, $this->_cache['guestsFromOrder'])) {
                                $this->_cache['guestsFromOrder'][$_cid]->setSalesforceId($this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId);
                                if (!$this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount) {
                                    $this->_cache['guestsFromOrder'][$_cid]->setSalesforceAccountId($this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId);
                                    $this->_cache['guestsFromOrder'][$_cid]->setSalesforceIsPerson(true);
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // TODO:  Log error, quit
                $response = $e->getMessage();
            }
        }

        Mage::dispatchEvent("tnw_salesforce_account_send_after",array(
            "data" => $this->_cache['accountsToUpsert']['Id'],
            "result" => $this->_cache['responses']['accounts']
        ));
    }

    /**
     * @param $_isOrder
     * @return bool|void
     */
    protected function _pushToSalesforce($_isOrder)
    {
        // before we send data to sf - check if connection / login / wsdl is valid
        // related ticket https://trello.com/c/TNEu7Rk1/54-salesforce-maintenance-causes-bulk-sync-to-run-indefinately
        $sfClient = Mage::getSingleton('tnw_salesforce/connection');
        if (
            !$sfClient->tryWsdl()
            || !$sfClient->tryToConnect()
            || !$sfClient->tryToLogin()
        ) {
            Mage::helper('tnw_salesforce')->log("error on push contacts: logging to salesforce api failed, cannot push data to salesforce");
            return false;
        }

        // Push Accounts on Id
        if (array_key_exists('Id', $this->_cache['accountsToUpsert']) && !empty($this->_cache['accountsToUpsert']['Id'])) {
            if (!$this->_cache['bulkJobs']['account']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['account']['Id'] = $this->_createJob('Account', 'upsert', 'Id');
                Mage::helper('tnw_salesforce')->log('Syncronizing Accounts, created job: ' . $this->_cache['bulkJobs']['account']['Id']);
            }
            Mage::dispatchEvent("tnw_salesforce_account_send_before",array("data" => $this->_cache['accountsToUpsert']['Id']));
            // send to sf
            $this->_pushChunked($this->_cache['bulkJobs']['account']['Id'], 'accounts', $this->_cache['accountsToUpsert']['Id'], 'Id');

            // Check if all accounts got Updated
            Mage::helper('tnw_salesforce')->log('Checking if Accounts were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['account']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['account']['Id']);
                Mage::helper('tnw_salesforce')->log('Still checking [1] (job: ' . $this->_cache['bulkJobs']['account']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['account']['Id']);
            }
            if (strval($_result) != 'exception') {
                Mage::helper('tnw_salesforce')->log('Accounts sync is complete! Moving on...');
                // Update New Account ID's
                $this->_assignAccountIds();
            }
        }

        // Push Leads on Id
        if (array_key_exists('Id', $this->_cache['leadsToUpsert']) && !empty($this->_cache['leadsToUpsert']['Id'])) {
            if (!$this->_cache['bulkJobs']['lead']['Id']) {
                // Create Job
                // upsert on Id if Magento users are guests
                $this->_cache['bulkJobs']['lead']['Id'] = $this->_createJob('Lead', 'upsert', 'Id');

                Mage::helper('tnw_salesforce')->log('Syncronizing Leads, created job: ' . $this->_cache['bulkJobs']['lead']['Id']);
            }
            Mage::dispatchEvent("tnw_salesforce_lead_send_before",array("data" => $this->_cache['leadsToUpsert']['Id']));
            // send to sf
            $this->_pushChunked($this->_cache['bulkJobs']['lead']['Id'], 'leads', $this->_cache['leadsToUpsert']['Id'], 'Id');

            // work with sf response
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['lead']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['lead']['Id']);
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['lead']['Id']);
            }
            if (strval($_result) != 'exception') {
                $this->_assignLeadIds('Id');
            }
        }

        // Push Leads on Magento Id
        if (array_key_exists($this->_magentoId, $this->_cache['leadsToUpsert']) && !empty($this->_cache['leadsToUpsert'][$this->_magentoId])) {
            if (!$this->_cache['bulkJobs']['lead'][$this->_magentoId]) {
                $this->_cache['bulkJobs']['lead'][$this->_magentoId] = $this->_createJob('Lead', 'upsert', $this->_magentoId);
                foreach ($this->_cache['leadsToUpsert'][$this->_magentoId] as $_key => $_object) {
                    if (property_exists($_object, 'Id')) {
                        unset($this->_cache['leadsToUpsert'][$this->_magentoId][$_key]->Id);
                    }
                }

                Mage::helper('tnw_salesforce')->log('Syncronizing Leads, created job: ' . $this->_cache['bulkJobs']['lead'][$this->_magentoId]);
            }
            Mage::dispatchEvent("tnw_salesforce_lead_send_before",array("data" => $this->_cache['leadsToUpsert'][$this->_magentoId]));
            // send to sf
            $this->_pushChunked($this->_cache['bulkJobs']['lead'][$this->_magentoId], 'leads', $this->_cache['leadsToUpsert'][$this->_magentoId], $this->_magentoId);

            // work with sf reponse
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['lead'][$this->_magentoId]);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['lead'][$this->_magentoId]);
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['lead'][$this->_magentoId]);
            }
            if (strval($_result) != 'exception') {
                $this->_assignLeadIds($this->_magentoId);
            }
        }

        // Push Contact on Id
        if (array_key_exists('Id', $this->_cache['contactsToUpsert']) && !empty($this->_cache['contactsToUpsert']['Id'])) {
                if (!$this->_cache['bulkJobs']['contact']['Id']) {
                    // Create Job
                    $this->_cache['bulkJobs']['contact']['Id'] = $this->_createJob('Contact', 'upsert', 'Id');
                    Mage::helper('tnw_salesforce')->log('Syncronizing Contacts, created job: ' . $this->_cache['bulkJobs']['contact']['Id']);
                }

                Mage::dispatchEvent("tnw_salesforce_contact_send_before",array("data" => $this->_cache['contactsToUpsert']['Id']));
                // send to sf
                $this->_pushChunked($this->_cache['bulkJobs']['contact']['Id'], 'contacts', $this->_cache['contactsToUpsert']['Id'], 'Id');

                Mage::helper('tnw_salesforce')->log('Checking if Contacts were successfully synced...');
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['contact']['Id']);
                $_attempt = 1;
                while (strval($_result) != 'exception' && !$_result) {
                        sleep(5);
                    $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['contact']['Id']);
                    Mage::helper('tnw_salesforce')->log('Still checking [2] (job: ' . $this->_cache['bulkJobs']['contact']['Id'] . ')...');
                    $_attempt++;

                    $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['contact']['Id']);
                }
                if (strval($_result) != 'exception') {
                    Mage::helper('tnw_salesforce')->log('Contacts sync is complete! Moving on...');
                    $this->_assignContactIds('Id');
                }
        }

        // Push Contact on Magento Id
        if (array_key_exists($this->_magentoId, $this->_cache['contactsToUpsert']) && !empty($this->_cache['contactsToUpsert'][$this->_magentoId])) {
                if (!$this->_cache['bulkJobs']['contact'][$this->_magentoId]) {
                    // Create Job
                    $this->_cache['bulkJobs']['contact'][$this->_magentoId] = $this->_createJob('Contact', 'upsert', $this->_magentoId);
                    foreach ($this->_cache['contactsToUpsert'][$this->_magentoId] as $_key => $_object) {
                        if (property_exists($_object, 'Id')) {
                            unset($this->_cache['contactsToUpsert'][$this->_magentoId][$_key]->Id);
                        }
                    }

                    Mage::helper('tnw_salesforce')->log('Synchronizing Contacts, created job: ' . $this->_cache['bulkJobs']['contact'][$this->_magentoId]);
                }

                Mage::dispatchEvent("tnw_salesforce_contact_send_before",array("data" => $this->_cache['contactsToUpsert'][$this->_magentoId]));

                $this->_pushChunked($this->_cache['bulkJobs']['contact'][$this->_magentoId], 'contacts', $this->_cache['contactsToUpsert'][$this->_magentoId], $this->_magentoId);

                Mage::helper('tnw_salesforce')->log('Checking if Contacts were successfully synced...');
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['contact'][$this->_magentoId]);
                $_attempt = 1;
                while (strval($_result) != 'exception' && !$_result) {
                        sleep(5);
                    $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['contact'][$this->_magentoId]);
                    Mage::helper('tnw_salesforce')->log('Still checking [4] (job: ' . $this->_cache['bulkJobs']['contact'][$this->_magentoId] . ')...');
                    $_attempt++;

                    $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['contact'][$this->_magentoId]);
                }
                if (strval($_result) != 'exception') {
                    Mage::helper('tnw_salesforce')->log('Contacts sync is complete! Moving on...');
                    $this->_assignContactIds($this->_magentoId);
                }
        }

        // collect error message
        $errorCustomerList = array();
        foreach ($this->_cache['toSaveInMagento'] as $websiteId => $_mageCustomer) {
            foreach ($_mageCustomer as $key => $value) {
                if (isset($value->SfInSync) && intval($value->SfInSync) == 0) {
                    if (array_key_exists($websiteId, $errorCustomerList) && !is_array($errorCustomerList[$websiteId])) {
                        $errorCustomerList[$websiteId] = array();
                    }
                    $errorCustomerList[$websiteId][] = $value->Email;
                }
            }
        }

        // show which customer failed
        if (!empty($errorCustomerList)) {
            if (!$this->isFromCLI() && Mage::helper('tnw_salesforce')->displayErrors()) {
                foreach($errorCustomerList as $_websiteId => $_emails) {
                    $_website = Mage::app()->getWebsite($_websiteId);
                    $errorCustomerListF = implode(", ", $_emails);
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Following customers from "' . $_website->getName() . '" failed to be synchronized: ' . $errorCustomerListF);
                }
            }
        }

        $this->_convertLeads();
    }

    protected function _onComplete()
    {
        // Close Jobs
        if ($this->_cache['bulkJobs']['lead']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['lead']['Id']);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['lead']['Id']);
        }
        if ($this->_cache['bulkJobs']['lead'][$this->_magentoId]) {
            $this->_closeJob($this->_cache['bulkJobs']['lead'][$this->_magentoId]);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['lead'][$this->_magentoId]);
        }
        if ($this->_cache['bulkJobs']['account']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['account']['Id']);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['account']['Id']);
        }
        if ($this->_cache['bulkJobs']['contact']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['contact']['Id']);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['contact']['Id']);
        }
        if ($this->_cache['bulkJobs']['contact'][$this->_magentoId]) {
            $this->_closeJob($this->_cache['bulkJobs']['contact'][$this->_magentoId]);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['contact'][$this->_magentoId]);
        }

        // Clear Session variables
        Mage::helper('tnw_salesforce')->log('Clearing bulk sync cache...');
        $this->_cache['bulkJobs']['lead'] = array('Id' => NULL, $this->_magentoId => NULL);
        $this->_cache['bulkJobs']['account'] = array('Id' => NULL);
        $this->_cache['bulkJobs']['contact'] = array('Id' => NULL, $this->_magentoId => NULL);

        parent::_onComplete();
    }

    /**
     * @param array $_customers
     * @param array $_existmingCustomers
     */
    public function forceAdd($_customers = array(), $_existingCustomers = array())
    {
        // Save existing customers
        $this->_allOrderCustomers = $_existingCustomers;
        $this->_toSyncOrderCustomers = $_customers;

        // Lookup existing Contacts & Accounts
        $_emailsArray = array();
        $_websiteArray = array();

        $_i = 0;
        foreach ($this->_toSyncOrderCustomers as $_orderNum => $_customer) {
            $_email = $_customer->getEmail();
            $_websiteId = ($_customer->getData('website_id') != NULL) ? $_customer->getData('website_id') : Mage::app()->getWebsite()->getId();
            $tmp = new stdClass();
            if (!$_customer->getId()) {
                //$this->_isPushingGuestData = true;
                $tmp->MagentoId = 'guest_' . $_i;
                $this->_cache['guestsFromOrder']['guest_' . $_i] = $_customer;

                $_emailsArray['guest_' . $_i] = $_email;
                $_websites['guest_' . $_i] = $this->_websiteSfIds[$_websiteId];
            } else {
                $tmp->MagentoId = $_customer->getId();
                if (!Mage::registry('customer_cached_' . $_customer->getId())) {
                    Mage::register('customer_cached_' . $_customer->getId(), $_customer);
                }
                $_emailsArray[$_customer->getId()] = $_email;
                $_websites[$_customer->getId()] = $this->_websiteSfIds[$_websiteId];
            }

            /**
             * @comment try to find customer company name
             */
            $_companyName = $_customer->getCompany();

            if (!$_companyName) {
                $_companyName = (
                    $_customer->getDefaultBillingAddress() &&
                    $_customer->getDefaultBillingAddress()->getCompany() &&
                    strlen($_customer->getDefaultBillingAddress()->getCompany())
                ) ? $_customer->getDefaultBillingAddress()->getCompany() : NULL;
            }
            /* Check if Person Accounts are enabled, if not default the Company name to first and last name */
            if (!Mage::helper("tnw_salesforce")->createPersonAccount() && !$_companyName) {
                $_companyName = $_customer->getFirstname() . " " . $_customer->getLastname();
            }
            $_companies[$_email] = $_companyName;

            $this->_cache['customerToWebsite'] = $_websites;
            $this->_allOrderCustomers[$_orderNum] = $_customer;

            $_websiteId = ($_customer->getData('website_id') != NULL) ? $_customer->getData('website_id') : Mage::app()->getWebsite()->getId();
            $_websiteArray[$_email] = $this->_websiteSfIds[$_websiteId];

            $tmp->Email = $_email;
            $tmp->SfInSync = 0;
            $this->_cache['toSaveInMagento'][$_websiteId][$_email] = $tmp;

            $_i++;
        }

        $_salesforceDataAccount = Mage::helper('tnw_salesforce/salesforce_data_account');
        $_companies = $_salesforceDataAccount->lookupByCompanies($_companies, 'CustomIndex');

        $this->_cache['customerToWebsite'] = $_websiteArray;
        $this->_cache['entitiesUpdating'] = $_emailsArray;
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emailsArray, $_websites);
        $this->_customerAccountId = Mage::helper('tnw_salesforce/salesforce_data')->accountLookupByEmailDomain($_emailsArray);

        $foundCustomers = array();

        foreach ($_emailsArray as $_key => $_email) {
            if (
                $this->_cache['contactsLookup'] &&
                array_key_exists($_websites[$_key], $this->_cache['contactsLookup']) &&
                array_key_exists($_email, $this->_cache['contactsLookup'][$_websites[$_key]]) &&
                ($this->_cache['contactsLookup'][$_websites[$_key]][$_email]->MagentoId == $_key || !$this->_cache['contactsLookup'][$_websites[$_key]][$_email]->MagentoId)
            ) {
                $foundCustomers[$_key] = array(
                    'contactId' => $this->_cache['contactsLookup'][$_websites[$_key]][$_email]->Id
                );

                $foundCustomers[$_key]['email'] = $_email;

                if ($this->_cache['contactsLookup'][$_websites[$_key]][$_email]->AccountId) {
                    $foundCustomers[$_key]['AccountId'] = $this->_cache['contactsLookup'][$_websites[$_key]][$_email]->AccountId;
                }

                if (array_key_exists($_key, $this->_cache['contactsLookup'][$_websites[$_key]])) {
                    $this->_cache['contactsLookup'][$_websites[$_key]][$_email] = $this->_cache['contactsLookup'][$_websites[$_key]][$_key];
                    unset($this->_cache['contactsLookup'][$_websites[$_key]][$_key]);
                }

                unset($_websites[$_key]);
                unset($_emailsArray[$_key]);
            }
        }
        // Lookup existing Leads
        if (!empty($_emailsArray) || !empty($foundCustomers)) {
            $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($this->_cache['entitiesUpdating'], $_websites);
            foreach ($this->_cache['leadLookup'] as $_websiteId => $leads) {
                foreach ($leads as $email => $lead) {
                    if (!$this->_cache['leadLookup'][$_websiteId][$email]->MagentoId) {

                        foreach ($this->_cache['entitiesUpdating'] as $customerId => $customerEmail) {
                            if ($customerEmail == $email) {
                                $this->_cache['leadLookup'][$_websiteId][$email]->MagentoId = $customerId;
                                $foundCustomers[$customerId] = (array)$this->_cache['leadLookup'][$_websiteId][$email];
                                $foundCustomers[$customerId]['email'] = $email;
                            }
                        }
                    }
                }

            }
        }

        foreach ($_emailsArray as $_key => $_email) {
            if (
                !Mage::helper('tnw_salesforce')->isCustomerAsLead()
                && array_key_exists($_websites[$_key], $this->_cache['leadLookup'])
                && array_key_exists($_email, $this->_cache['leadLookup'][$_websites[$_key]])
            ) {
                $foundCustomers[$_key]['email'] = $_email;
            }
        }

        if (Mage::helper('tnw_salesforce')->isCustomerAsLead()) {
            foreach ($_emailsArray as $_key => $_email) {
                if (
                    $this->_cache['leadLookup'] &&
                    array_key_exists($_websites[$_key], $this->_cache['leadLookup']) &&
                    array_key_exists(strtolower($_email), $this->_cache['leadLookup'][$_websites[$_key]]) &&
                    ($this->_cache['leadLookup'][$_websites[$_key]][$_email]->MagentoId == $_key || !$this->_cache['leadLookup'][$_websites[$_key]][$_email]->MagentoId) &&
                    !$this->_cache['leadLookup'][$_websites[$_key]][$_email]->IsConverted
                ) {
                    unset($_emailsArray[$_key]);
                }
            }
        } else {
            foreach ($foundCustomers as $_key => $data) {
                $_data = $this->_cache['leadLookup'][$_websites[$_key]][$data['email']];

                if ($_data->IsConverted) {
                    // TODO: if no contacts found, confirm that new contact and account should be created.
                    continue;
                }

                $leadData = new stdClass();

                if (array_key_exists('AccountId', $data) && !empty($data['AccountId'])) {
                    $leadData->accountId = $data['AccountId'];
                }

                if (array_key_exists($data['email'], $_companies) && !empty($_companies[$data['email']]) && empty($leadData->accountId)) {
                    $leadData->accountId = $_companies[$data['email']]->Id;

                    $leadData->OwnerId = $_companies[$data['email']]->OwnerId;
                    // Check if user is inactive, then overwrite from configuration
                    if (!$this->_isUserActive($leadData->OwnerId)) {
                        $this->_obj->OwnerId = Mage::helper('tnw_salesforce')->getDefaultOwner();
                    }
                } elseif (!empty($this->_customerAccounts[$data['email']])) {
                    $leadData->accountId = $this->_customerAccounts[$data['email']];
                }

                if (array_key_exists('contactId', $data) && $data['contactId'] && (!empty($leadData->accountId))) {
                    if ($data['contactId']) {
                        $leadData->contactId = $data['contactId'];
                    }
                }

                $leadData = $this->_prepareLeadConversionObject($leadData, $_data->Id);

                $this->_cache['leadsToConvert'][$_data->Id] = $leadData;

                unset($_emailsArray[$_key]);
            }
        }

        $this->_cache['notFoundCustomers'] = $_emailsArray;
    }

    public function reset()
    {
        parent::reset();

        $this->_cache['batch'] = array();
        $this->_cache['batchCache'] = array();
        $this->_cache['guestDuplicates'] = array();

        $this->_cache['bulkJobs'] = array(
            'lead' => array('Id' => NULL, $this->_magentoId => NULL),
            'contact' => array('Id' => NULL, $this->_magentoId => NULL),
            'account' => array('Id' => NULL),
        );

        $this->_client = new Zend_Http_Client();
        $this->_client->setConfig(
            array(
                'maxredirects' => 0,
                'timeout' => 10,
                'keepalive' => true,
                'storeresponse' => true,
            )
        );

        $valid = $this->check();

        if ($valid) {
            $this->getServerHelper()->apply(TNW_Salesforce_Helper_Config_Server::BULK);
        }

        return $valid;
    }

    /**
     * @return array|mixed
     */
    public function getAllAccounts()
    {
        $_useCache = Mage::app()->useCache('tnw_salesforce');
        $cache = Mage::app()->getCache();

        if (!$_useCache || ($_useCache && !$cache->load("tnw_salesforce_all_accounts"))) {
            $_jobId = $this->_createJobQuery('Account');
            $_batchId = $this->_query('SELECT Id, Name FROM Account', $_jobId);

            Mage::helper('tnw_salesforce')->log('Checking query completion...');
            $_isComplete = $this->_checkBatchCompletion($_jobId);
            $_attempt = 1;
            while (strval($_isComplete) != 'exception' && !$_isComplete && $_attempt < 51) {
                sleep(5);
                $_isComplete = $this->_checkBatchCompletion($_jobId);
                Mage::helper('tnw_salesforce')->log('Still checking [5] (job: ' . $_jobId . ')...');
                $_attempt++;
                // Break infinite loop after 50 attempts.
                if(!$_isComplete && $_attempt == 50) {$_isComplete = 'exception';}
            }
            $this->_closeJob($_jobId);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $_jobId);

            $_resultBatch = array();
            if ($_attempt != 50) {
                $resultIds = $this->getBatch($_jobId, $_batchId);
                foreach ($resultIds as $_resultId) {
                    $_tmp = $this->getBatchResult($_jobId, $_batchId, $_resultId);
                    foreach ($_tmp->records as $_record) {
                        $_resultBatch[(string)$_record->Name] = (string)$_record->Id[0];
                    }
                }

                ksort($_resultBatch);
                $_result = array_flip($_resultBatch);

                if ($_useCache) {
                    $cache->save(serialize($_result), 'tnw_salesforce_all_accounts', array("TNW_SALESFORCE"), 60 * 60 * 24);
                }
            }
        } elseif ($cache->load("tnw_salesforce_all_accounts")) {
            $_result = unserialize($cache->load("tnw_salesforce_all_accounts"));
        }

        return $_result;
    }

    /**
     * For Guest orders, if same person found in SF, don't push it multiple times
     */
    protected function _deDupeCustomers() {
        $_collections = array('leadsToUpsert', 'contactsToUpsert', 'accountsToUpsert');
        foreach($_collections as $_collection) {
            if (array_key_exists('Id',$this->_cache[$_collection]) && is_array($this->_cache[$_collection]['Id']) && !empty($this->_cache[$_collection]['Id'])) {
                $_salesforceIds = array();
                foreach ($this->_cache[$_collection]['Id'] as $_magentoId => $_object) {
                    if ($_collection == 'accountsToUpsert') {
                        if (property_exists($_object, 'Name')) {
                            $_compiledKey = $_object->Name;
                        } else {
                            // B2C account
                            $_compiledKey = $_object->PersonEmail;
                            if (property_exists($_object, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject('_pc'))) {
                                $_compiledKey .= ':::' . $_object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject('_pc')};
                            }
                        }

                        if (!in_array($_compiledKey, $_salesforceIds)) {
                            $_salesforceIds[$_magentoId] = $_compiledKey;
                        } else {
                            unset($this->_cache[$_collection]['Id'][$_magentoId]);
                        }
                    } else {
                        $_compiledKey = $_object->Email;
                        if (property_exists($_object, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject())) {
                            $_compiledKey .= ':::' . $_object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()};
                        }

                        if (!in_array($_compiledKey, $_salesforceIds)) {
                            $_salesforceIds[$_magentoId] = $_compiledKey;
                        } else {
                            $_key = array_search($_compiledKey, $_salesforceIds);
                            $this->_cache['guestDuplicates'][$_magentoId] = $_key;
                            unset($this->_cache[$_collection]['Id'][$_magentoId]);
                        }
                    }
                }
            }
        }

        // Additional de duplication logic for PersonAccounts
        if (
            Mage::helper('tnw_salesforce')->usePersonAccount()
            && Mage::helper('tnw_salesforce')->isCustomerSingleRecordType() != TNW_Salesforce_Model_Config_Account_Recordtypes::B2B_ACCOUNT
        ) {
            $_salesforceIds = array();
            foreach ($this->_cache['accountsToUpsert']['Id'] as $_magentoId => $_object) {
                if (!property_exists($_object, 'Name')) {
                    // Only applies to  Person Accounts
                    $_compiledKey = $_object->PersonEmail;
                    if (property_exists($_object, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject())) {
                        $_compiledKey .= ':::' . $_object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()};
                    }
                    if (!in_array($_compiledKey, $_salesforceIds)) {
                        $_salesforceIds[$_magentoId] = $_compiledKey;
                    } else {
                        $_key = array_search($_compiledKey, $_salesforceIds);
                        if (array_key_exists($_magentoId, $this->_cache['guestDuplicates'])) {
                            $this->_cache['guestDuplicates'][$_magentoId] = $_key;
                        }
                        unset($this->_cache['accountsToUpsert']['Id'][$_magentoId]);
                    }
                }
            }
        }
    }

    protected function _updateGuestCachedData() {
        if (!empty($this->_cache['guestDuplicates'])) {
            foreach($this->_cache['guestDuplicates'] as $_who => $_source) {
                $this->_cache['guestsFromOrder'][$_who] = $this->_cache['guestsFromOrder'][$_source];
            }
        }
    }


}