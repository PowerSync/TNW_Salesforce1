<?php

/**
 * Class TNW_Salesforce_Helper_Bulk_Customer
 */
class TNW_Salesforce_Helper_Bulk_Customer extends TNW_Salesforce_Helper_Salesforce_Customer
{
    /**
     * @var null|Zend_Http_Client
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

        /**
         * @comment apply bulk server settings
         */
        $this->getServerHelper()->apply(TNW_Salesforce_Helper_Config_Server::BULK);

        $result = parent::process($_return);
        /**
         * @comment restore server settings
         */
        $this->getServerHelper()->apply();

        return $result;
    }

    /**
     * push data to Salesforce
     */
    protected function _updateCampaings()
    {
        Mage::helper('tnw_salesforce/salesforce_newslettersubscriber')->updateCampaingsBulk();
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
        $_companies = array();

        $_i = 0;
        foreach ($this->_toSyncOrderCustomers as $_orderNum => $_customer) {
            // Customer email has to be lowercased
            $_email = strtolower($_customer->getEmail());
            $_websiteId = ($_customer->getData('website_id') != NULL) ? $_customer->getData('website_id') : Mage::app()->getWebsite()->getId();
            $tmp = new stdClass();
            if (!$_customer->getId()) {
                //$this->_isPushingGuestData = true;
                $tmp->MagentoId = 'guest_' . $_orderNum;
                $this->_cache['guestsFromOrder']['guest_' . $_orderNum] = $_customer;

                $_emailsArray['guest_' . $_orderNum] = $_email;
                $_websites['guest_' . $_orderNum] = $this->_websiteSfIds[$_websiteId];
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
            // only add company name to array if it exists
            if ($_companyName) {
                $_companies[$_email] = $_companyName;
            }

            $this->_cache['customerToWebsite'] = $_websites;
            $this->_allOrderCustomers[$_orderNum] = $_customer;

            $_websiteId = ($_customer->getData('website_id') != NULL) ? $_customer->getData('website_id') : Mage::app()->getWebsite()->getId();
            $_websiteArray[$_email] = $this->_websiteSfIds[$_websiteId];

            $tmp->Email = $_email;
            $tmp->SfInSync = 0;
            $this->_cache['toSaveInMagento'][$_websiteId][$_email] = $tmp;

            $_i++;
        }

        if (!empty($_companies)) {
            $_salesforceDataAccount = Mage::helper('tnw_salesforce/salesforce_data_account');
            $_companies = $_salesforceDataAccount->lookupByCompanies($_companies, 'CustomIndex');
        }

        $foundCustomers = array();

        $this->_cache['entitiesUpdating'] = $_emailsArray;
        if (!empty($this->_allOrderCustomers)) {
            foreach ($this->_allOrderCustomers as $_orderNumber => $_customer) {

                $_email = strtolower($_customer->getEmail());
                if (isset($this->_cache['entitiesUpdating'][$_email])) {
                    continue;
                }

                $_websiteId = ($_customer->getData('website_id') != NULL) ? $_customer->getData('website_id') : Mage::app()->getWebsite()->getId();

                if (!$_customer->getId()) {
                    $key = 'guest_' . $_orderNumber;

                    $this->_cache['guestsFromOrder'][$key] = $_customer;

                    $_websites[$key] = $this->_websiteSfIds[$_websiteId];

                } else {
                    $key = $_customer->getId();
                    if (!Mage::registry('customer_cached_' . $_customer->getId())) {
                        Mage::register('customer_cached_' . $_customer->getId(), $_customer);
                    }
                }
                $this->_cache['entitiesUpdating'][$key] = $_email;
                $this->_toSyncOrderCustomers[$_orderNumber] = $_customer;

                $_i++;
            }
        }


        if (!empty($_emailsArray)) {
            $this->_cache['customerToWebsite'] = $_websites;
            $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($this->_cache['entitiesUpdating'], $_websites);
            $this->_cache['accountLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($this->_cache['entitiesUpdating'], $_websites);
            $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')
                ->lookup(
                    $this->_cache['entitiesUpdating'],
                    $_websites,
                    (Mage::helper('tnw_salesforce/data')->useLeadSourceFilter()) ? Mage::helper('tnw_salesforce/data')->getLeadSource() : null

                );
            $this->_customerAccountId = Mage::helper('tnw_salesforce/salesforce_data')->accountLookupByEmailDomain($_emailsArray);
        }

        $_converted = array();
        foreach ($_emailsArray as $_key => $_email) {
            if (
                $this->_cache['contactsLookup']
                && array_key_exists($_websites[$_key], $this->_cache['contactsLookup'])
                && (
                    array_key_exists($_email, $this->_cache['contactsLookup'][$_websites[$_key]])
                    || array_key_exists($_key, $this->_cache['contactsLookup'][$_websites[$_key]])
                )
            ) {
                $foundCustomers[$_key] = array(
                    'contactId' => $this->_cache['contactsLookup'][$_websites[$_key]][$_email]->Id
                );

                $foundCustomers[$_key]['email'] = $_email;

                if ($this->_cache['contactsLookup'][$_websites[$_key]][$_email]->AccountId) {
                    $foundCustomers[$_key]['accountId'] = $this->_cache['contactsLookup'][$_websites[$_key]][$_email]->AccountId;
                }

                if (array_key_exists($_key, $this->_cache['contactsLookup'][$_websites[$_key]])) {
                    $this->_cache['contactsLookup'][$_websites[$_key]][$_email] = $this->_cache['contactsLookup'][$_websites[$_key]][$_key];
                    unset($this->_cache['contactsLookup'][$_websites[$_key]][$_key]);
                }

                if (!array_key_exists('contactId', $foundCustomers[$_key])) {
                    $_converted[$_key] = $foundCustomers[$_key];
                }

                unset($_emailsArray[$_key]);
                unset($_websites[$_key]);
            }
        }

        // Lookup existing Leads
        if (!empty($_emailsArray) || !empty($_converted)) {
            $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')
                ->lookup(
                    $this->_cache['entitiesUpdating'],
                    $_websites,
                    (Mage::helper('tnw_salesforce/data')->useLeadSourceFilter()) ? Mage::helper('tnw_salesforce/data')->getLeadSource() : null
                );

            if (!empty($this->_cache['leadLookup'])) {
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
        }

        foreach ($_emailsArray as $_key => $_email) {
            if (
                !Mage::helper('tnw_salesforce')->isCustomerAsLead()
                && !empty($this->_cache['leadLookup'])
                && array_key_exists($_websites[$_key], $this->_cache['leadLookup'])
                && array_key_exists($_email, $this->_cache['leadLookup'][$_websites[$_key]])
            ) {
                $foundCustomers[$_key]['email'] = $_email;
            }
        }

        /**
         * forceAdd method used for order sync process
         * if lead sync enabled and order placed - we should convert lead to account + contact
         */
        if (Mage::helper('tnw_salesforce')->isCustomerAsLead()) {
            $this->setForceLeadConvertaton(true);
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


        $valid = $this->check();

        return $valid;
    }

    /**
     * @return array|mixed
     */
    public function getAllAccounts()
    {
        $jobId = $this->_createJobQuery('Account');
        $sql = 'SELECT Id, Name ';
        $recordTypes = Mage::helper('tnw_salesforce')->getBusinessAccountRecordIds();
        // BULK API v.34 does not support RecordTypeId
        //if (!empty($recordTypes) && !in_array(TNW_Salesforce_Helper_Salesforce_Data::PROFESSIONAL_SALESFORCE_RECORD_TYPE_LABEL, $recordTypes)) {
        //    $sql .= ', RecordType ';
        //}
        $sql .= 'FROM Account';

        // Don't return Person Accounts
        if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
            $sql .= " WHERE IsPersonAccount != true";
        }

        if (!empty($jobId)) {
            $batchId = $this->_query($sql, $jobId);
            $maxAttempts = 50;

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Checking query completion...');
            $isComplete = $this->_checkBatchCompletion($jobId);
            $attempt = 0;
            while (strval($isComplete) != 'exception' && !$isComplete && ++$attempt <= $maxAttempts) {
                sleep(5);
                $isComplete = $this->_checkBatchCompletion($jobId);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Still checking [5] (job: ' . $jobId . ')...');
                // Break infinite loop after 50 attempts.
                if (!$isComplete && $attempt == $maxAttempts) {
                    $isComplete = 'exception';
                }
            }
        }

        $result = array();
        if (!empty($jobId)) {
            $this->_closeJob($jobId);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $jobId);

            if ($attempt != $maxAttempts) {
                $resultIds = $this->getBatch($jobId, $batchId);
                foreach ($resultIds as $_resultId) {
                    $tmpResult = $this->getBatchResult($jobId, $batchId, $_resultId);
                    foreach ($tmpResult->records as $record) {
                        $result[(string)$record->Id[0]] = new stdClass();
                        $result[(string)$record->Id[0]]->Name = (string)$record->Name;
                        //if (!empty($recordTypes) && !in_array(TNW_Salesforce_Helper_Salesforce_Data::PROFESSIONAL_SALESFORCE_RECORD_TYPE_LABEL, $recordTypes)) {
                        //    $result[(string)$record->Id[0]]->RecordTypeId = (string)$record->RecordTypeId;
                        //}
                    }
                }
            }
        }

        return $result;
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
        if (!$sfClient->initConnection()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR on push contacts: logging to salesforce api failed, cannot push data to salesforce");
            return false;
        }

        // Push Accounts on Id
        if (array_key_exists('Id', $this->_cache['accountsToUpsert']) && !empty($this->_cache['accountsToUpsert']['Id'])) {
            if (!$this->_cache['bulkJobs']['account']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['account']['Id'] = $this->_createJob('Account', 'upsert', 'Id');
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Syncronizing Accounts, created job: ' . $this->_cache['bulkJobs']['account']['Id']);
            }
            Mage::dispatchEvent("tnw_salesforce_account_send_before", array("data" => $this->_cache['accountsToUpsert']['Id']));
            // send to sf
            $this->_pushChunked($this->_cache['bulkJobs']['account']['Id'], 'accounts', $this->_cache['accountsToUpsert']['Id'], 'Id');

            // Check if all accounts got Updated
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Checking if Accounts were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['account']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['account']['Id']);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Still checking [1] (job: ' . $this->_cache['bulkJobs']['account']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['account']['Id']);
            }
            if (strval($_result) != 'exception') {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Accounts sync is complete! Moving on...');
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

                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Syncronizing Leads, created job: ' . $this->_cache['bulkJobs']['lead']['Id']);
            }
            Mage::dispatchEvent("tnw_salesforce_lead_send_before", array("data" => $this->_cache['leadsToUpsert']['Id']));
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

                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Syncronizing Leads, created job: ' . $this->_cache['bulkJobs']['lead'][$this->_magentoId]);
            }
            Mage::dispatchEvent("tnw_salesforce_lead_send_before", array("data" => $this->_cache['leadsToUpsert'][$this->_magentoId]));
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
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Syncronizing Contacts, created job: ' . $this->_cache['bulkJobs']['contact']['Id']);
            }

            Mage::dispatchEvent("tnw_salesforce_contact_send_before", array("data" => $this->_cache['contactsToUpsert']['Id']));
            // send to sf
            $this->_pushChunked($this->_cache['bulkJobs']['contact']['Id'], 'contacts', $this->_cache['contactsToUpsert']['Id'], 'Id');

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Checking if Contacts were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['contact']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['contact']['Id']);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Still checking [2] (job: ' . $this->_cache['bulkJobs']['contact']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['contact']['Id']);
            }
            if (strval($_result) != 'exception') {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Contacts sync is complete! Moving on...');
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

                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Synchronizing Contacts, created job: ' . $this->_cache['bulkJobs']['contact'][$this->_magentoId]);
            }

            Mage::dispatchEvent("tnw_salesforce_contact_send_before", array("data" => $this->_cache['contactsToUpsert'][$this->_magentoId]));

            $this->_pushChunked($this->_cache['bulkJobs']['contact'][$this->_magentoId], 'contacts', $this->_cache['contactsToUpsert'][$this->_magentoId], $this->_magentoId);

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Checking if Contacts were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['contact'][$this->_magentoId]);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['contact'][$this->_magentoId]);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Still checking [4] (job: ' . $this->_cache['bulkJobs']['contact'][$this->_magentoId] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['contact'][$this->_magentoId]);
            }
            if (strval($_result) != 'exception') {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Contacts sync is complete! Moving on...');
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
                foreach ($errorCustomerList as $_websiteId => $_emails) {
                    if (empty($_websiteId)) {
                        $_websiteId = null;
                    }
                    $_website = Mage::app()->getWebsite($_websiteId);
                    $errorCustomerListF = implode(", ", $_emails);
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError('WARNING: Following customers from "' . $_website->getName() . '" failed to be synchronized: ' . $errorCustomerListF);
                }
            }
        }

        $this->findLeadsForConversion();
        $this->_convertLeads();
    }

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
                    $_i++;
                    $this->_prepareAccountId($_cid, $_item, $_key);
                    if (!empty($this->_cache['accountsToUpsertDuplicates'][$_cid])) {
                        foreach ($this->_cache['accountsToUpsertDuplicates'][$_cid] as $customerId) {
                            $this->_prepareAccountId($customerId, $_item, $_key);
                        }
                    }
                }
            } catch (Exception $e) {
                // TODO:  Log error, quit
            }
        }

        Mage::dispatchEvent("tnw_salesforce_account_send_after", array(
            "data" => $this->_cache['accountsToUpsert']['Id'],
            "result" => $this->_cache['responses']['accounts']
        ));
    }

    /**
     * @param string $_on
     */
    protected function _prepareAccountId($_cid, $_item, $_key)
    {

        $_websiteId = $this->_getWebsiteIdByCustomerId($_cid);

        // report Transaction
        $this->_cache['responses']['accounts'][$_cid] = json_decode(json_encode($_item), TRUE);

        $_email = $this->_cache['entitiesUpdating'][$_cid];
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
            return false;
        } else {
            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = (string)$_item->id;

            if (!property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'LeadId')) {
                $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = NULL;
            }

            if (!property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'LeadId')) {
                $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId = NULL;
            }

            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 0;
            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount = 0;

            if (
                array_key_exists($_cid, $this->_cache['accountsToUpsert']['Id'])
                && property_exists($this->_cache['accountsToUpsert']['Id'][$_cid], 'PersonEmail')
            ) {
                $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = (string)$_item->id;
                $this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount = 1;
                $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 1;

                /**
                 * If sync PersonAccount - set empty Lead's Company name for correct converting
                 */
                foreach ($this->_cache['leadsToUpsert'] as $_upsertOn => $_objects) {
                    if (array_key_exists($_cid, $_objects)) {
                        $this->_cache['leadsToUpsert'][$_upsertOn][$_cid]->Company = ' ';
                    }
                }
            } elseif (array_key_exists($_cid, $this->_cache['accountsToUpsert']['Id'])
                && !property_exists($this->_cache['accountsToUpsert']['Id'][$_cid], 'PersonEmail')
            ) {
                /**
                 * If lead has not Company name - set Account name for correct converting
                 */
                foreach ($this->_cache['leadsToUpsert'] as $_upsertOn => $_objects) {
                    if (array_key_exists($_cid, $_objects)) {
                        if (!property_exists($_objects, 'Company')) {
                            $this->_cache['leadsToUpsert'][$_upsertOn][$_cid]->Company = $this->_cache['accountsToUpsert']['Id'][$_cid]->Name;
                        }
                    }
                }
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

            /**
             * Update lookup for lead convertation
             */
            if (isset($this->_cache['accountsToUpsert']['Id'][$_cid])) {

                $this->_cache['accountsToUpsert']['Id'][$_cid]->Id = (string)$_item->id;
                $this->_cache['accountLookup'][0][$_email] = $this->_cache['accountsToUpsert']['Id'][$_cid];
                if (property_exists($this->_cache['accountLookup'][0][$_email], $this->_magentoId)) {
                    $this->_cache['accountLookup'][0][$_email]->MagentoId = $this->_cache['accountLookup'][0][$_email]->{$this->_magentoId};
                } else {
                    $this->_cache['accountLookup'][0][$_email]->MagentoId = $_cid;
                }

                if (property_exists($this->_cache['accountsToUpsert']['Id'][$_cid], 'PersonEmail')) {
                    if (!isset($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email])) {
                        $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email] = new stdClass();
                    }
                    $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->Id = (string)$_item->id;
                    $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->IsPersonAccount = true;

                }
            }
        }
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

                    if (!property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'AccountId')) {
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = NULL;
                    }

                    if (!property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'SalesforceId')) {
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = NULL;
                    }

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

                    /**
                     * Update lookup for lead convertation
                     */
                    if (isset($this->_cache['leadsToUpsert'][$_on][$_cid])) {

                        $this->_cache['leadsToUpsert'][$_on][$_cid]->Id = (string)$_item->id;

                        foreach ($this->_cache['leadsToUpsert'][$_on][$_cid] as $field => $value) {
                            $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->$field = $value;
                        }

                        if (property_exists($this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email], $this->_magentoId)) {
                            $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId = $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->{$this->_magentoId};
                        } else {
                            $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId = $_cid;
                        }
                    }
                }
            } catch (Exception $e) {
                // TODO:  Log error, quit
            }
        }

        Mage::dispatchEvent("tnw_salesforce_lead_send_after", array(
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

                        $contactId = (string)$_item->id;

                        if (
                            $_on == 'Id'
                            && property_exists($this->_cache['batch']['contacts'][$_on][$_key][$_cid], 'Id')
                            && $this->_cache['batch']['contacts'][$_on][$_key][$_cid]->Id != $contactId
                        ) {
                            $contactId = $this->_cache['batch']['contacts'][$_on][$_key][$_cid]->Id;
                        }

                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = $contactId;
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

                        if (!property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'LeadId')) {
                            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId = NULL;
                        }

                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 1;

                        /**
                         * Update lookup for lead convertation
                         */
                        if (isset($this->_cache['contactsToUpsert'][$_on][$_cid])) {

                            $this->_cache['contactsToUpsert'][$_on][$_cid]->Id = (string)$_item->id;
                            $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email] = $this->_cache['contactsToUpsert'][$_on][$_cid];
                            if (property_exists($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email], $this->_magentoId)) {
                                $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId = $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->{$this->_magentoId};
                            } else {
                                $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId = $_cid;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // TODO:  Log error, quit
                }
            }

            Mage::dispatchEvent("tnw_salesforce_contact_send_after", array(
                "data" => $this->_cache['contactsToUpsert'][$_on],
                "result" => $this->_cache['responses']['contacts']
            ));
        }
    }

    protected function _onComplete()
    {
        // Close Jobs
        if ($this->_cache['bulkJobs']['lead']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['lead']['Id']);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['lead']['Id']);
        }
        if ($this->_cache['bulkJobs']['lead'][$this->_magentoId]) {
            $this->_closeJob($this->_cache['bulkJobs']['lead'][$this->_magentoId]);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['lead'][$this->_magentoId]);
        }
        if ($this->_cache['bulkJobs']['account']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['account']['Id']);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['account']['Id']);
        }
        if ($this->_cache['bulkJobs']['contact']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['contact']['Id']);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['contact']['Id']);
        }
        if ($this->_cache['bulkJobs']['contact'][$this->_magentoId]) {
            $this->_closeJob($this->_cache['bulkJobs']['contact'][$this->_magentoId]);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['contact'][$this->_magentoId]);
        }

        // Clear Session variables
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Clearing bulk sync cache...');
        $this->_cache['bulkJobs']['lead'] = array('Id' => NULL, $this->_magentoId => NULL);
        $this->_cache['bulkJobs']['account'] = array('Id' => NULL);
        $this->_cache['bulkJobs']['contact'] = array('Id' => NULL, $this->_magentoId => NULL);

        parent::_onComplete();
    }

    /**
     * For Guest orders, if same person found in SF, don't push it multiple times
     */
    protected function _deDupeCustomers()
    {
        $_collections = array('leadsToUpsert', 'contactsToUpsert', 'accountsToUpsert');
        foreach ($_collections as $_collection) {
            $this->_cache[$_collection . 'Duplicates'] = array();
            $_compiledKey = null;
            if (array_key_exists('Id', $this->_cache[$_collection]) && is_array($this->_cache[$_collection]['Id']) && !empty($this->_cache[$_collection]['Id'])) {
                $_salesforceIds = array();
                foreach ($this->_cache[$_collection]['Id'] as $_magentoId => $_object) {
                    if ($_collection == 'accountsToUpsert') {

                        $contact = null;

                        foreach (array('Id', $this->_magentoId) as $cacheUpsertKey) {

                            if (array_key_exists($_magentoId, $this->_cache['contactsToUpsert'][$cacheUpsertKey])) {
                                $contact = $this->_cache['contactsToUpsert'][$cacheUpsertKey][$_magentoId];
                            } else {
                                $contact = $this->_cache['contactsToUpsertBackup'][$cacheUpsertKey][$_magentoId];
                            }

                            if (!empty($contact)) {
                                break;
                            }
                        }

                        /**
                         * PersonAccount for
                         */
                        if (!$contact) {
                            continue;
                        }

                        $_email = ($contact->Email) ? $contact->Email : $contact->PersonEmail;

                        if (property_exists($contact, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject('_pc'))) {
                            $sfWebsiteId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject('_pc');
                        } else {
                            $sfWebsiteId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();
                        }
                        $_sfWebsite = $contact->{$sfWebsiteId};

                        if (property_exists($_object, 'Id')) {
                            $_compiledKey = $_object->Id;
                        } elseif (property_exists($_object, 'Name')) {
                            $_compiledKey = $_object->Name;
                        } elseif ($this->_getAccountName(NULL, $_email, $_sfWebsite)) {
                            $_compiledKey = $this->_getAccountName(NULL, $_email, $_sfWebsite);
                        } else {
                            // B2C account
                            $_compiledKey = $_object->PersonEmail;
                            if (property_exists($_object, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject('_pc'))) {
                                $_compiledKey .= ':::' . $_object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject('_pc')};
                            }
                        }

                        if (!empty($_compiledKey)) {
                            if (!in_array($_compiledKey, $_salesforceIds)) {
                                $_salesforceIds[$_magentoId] = $_compiledKey;
                            } else {
                                $firstEntity = array_search($_compiledKey, $_salesforceIds);
                                $this->_cache[$_collection . 'Duplicates'][$firstEntity][] = $_magentoId;
                                unset($this->_cache[$_collection]['Id'][$_magentoId]);
                            }
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
                            $this->_cache[$_collection . 'Backup']['Id'][$_magentoId] = $this->_cache[$_collection]['Id'][$_magentoId];
                            unset($this->_cache[$_collection]['Id'][$_magentoId]);
                        }
                    }
                }
            }
        }

        // Additional de duplication logic for PersonAccounts
        $_salesforceIds = array();
        foreach ($this->_cache['accountsToUpsert']['Id'] as $_magentoId => $_object) {
            $_websiteId = $this->_getWebsiteIdByCustomerId($_magentoId);
            if (
                Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_PERSON_ACCOUNT)
                && Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_FORCE_RECORDTYPE) != TNW_Salesforce_Model_Config_Account_Recordtypes::B2B_ACCOUNT
                && !property_exists($_object, 'Name')
                && property_exists($_object, 'RecordTypeId')
                && Mage::helper('tnw_salesforce')->usePersonAccount()
                && array_key_exists($_object->RecordTypeId, Mage::helper('tnw_salesforce')->getPersonAccountRecordIds())
            ) {
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

    protected function _updateGuestCachedData()
    {
        if (!empty($this->_cache['guestDuplicates'])) {
            foreach ($this->_cache['guestDuplicates'] as $_who => $_source) {
                $this->_cache['guestsFromOrder'][$_who] = $this->_cache['guestsFromOrder'][$_source];
            }
        }
    }
}
