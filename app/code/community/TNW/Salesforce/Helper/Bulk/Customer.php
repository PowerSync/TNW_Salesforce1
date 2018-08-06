<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
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
     * @param string $type
     * @return array|bool|mixed
     */
    public function process($type = 'soft')
    {
        /**
         * @comment apply bulk server settings
         */
        $this->getServerHelper()->apply(TNW_Salesforce_Helper_Config_Server::BULK);

        $result = parent::process();
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
        if (empty($this->_cache['subscriberToUpsert'])) {
            return;
        }

        $campaignMember = Mage::helper('tnw_salesforce/bulk_campaign_member');
        if ($campaignMember->reset() && $campaignMember->memberAdd($this->_cache['subscriberToUpsert'])) {
            $campaignMember->process();
        }
    }

    public function reset()
    {
        parent::reset();

        $this->_cache['batch'] = array();
        $this->_cache['batchCache'] = array();
        $this->_cache['guestDuplicates'] = array();
        $this->_cache['contactsToUpsertBackup'] = array(
            'Id' => array(),
            $this->_magentoId => array()
        );
        $this->_cache['contactsToUpsert'] = array(
            'Id' => array(),
            $this->_magentoId => array()
        );

        $this->_cache['bulkJobs'] = array(
            'lead' => array('Id' => NULL, $this->_magentoId => NULL),
            'contact' => array('Id' => NULL, $this->_magentoId => NULL),
            'account' => array('Id' => NULL),
        );

        return $this->check();
    }

    /**
     * @return bool|void
     */
    protected function _pushToSalesforce()
    {

        // Clean up the data we are going to be pushing in (for guest orders if multiple orders placed by the same person and they happen to end up in the same batch)
        $this->_deDupeCustomers();

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
        foreach ($this->_cache['batchCache']['accounts']['Id'] as $_key => $_batchId) {
            $_batch = &$this->_cache['batch']['accounts']['Id'][$_key];

            try {
                $response = $this->getBatch($this->_cache['bulkJobs']['account']['Id'], $_batchId);
            } catch (Exception $e) {
                $response = array_fill(0, count($_batch), $this->_buildErrorResponse($e->getMessage()));

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Prepare batch #'. $_batchId .' Error: ' . $e->getMessage());
            }

            $_i = 0;
            $_batchKeys = array_keys($_batch);
            foreach ($response as $_item) {
                $_cid = $_batchKeys[$_i++];
                $this->_prepareAccountId($_cid, $_item, $_key);

                if (!empty($this->_cache['accountsToUpsertDuplicates'][$_cid])) {
                    foreach ($this->_cache['accountsToUpsertDuplicates'][$_cid] as $customerId) {
                        $this->_prepareAccountId($customerId, $_item, $_key);
                    }
                }
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
        if ((string)$_item->success == "true") {
            $accountToUpsert = $this->_getAccountToUpsert($_cid);
            if (empty($accountToUpsert)) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Account upsert not found!');

                return false;
            }

            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = (string)$_item->id;

            if (!empty($accountToUpsert->OwnerId)) {
                $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountOwnerId
                    = $this->_prepareOwnerId($accountToUpsert->OwnerId);
            } elseif (!empty($this->_cache['accountLookup'][0][$_email]->OwnerId)) {
                $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountOwnerId
                    = $this->_prepareOwnerId($this->_cache['accountLookup'][0][$_email]->OwnerId);
            }

            if (!property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'LeadId')) {
                $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = NULL;
            }

            if (!property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'LeadId')) {
                $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId = NULL;
            }

            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 0;
            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount = 0;

            if (property_exists($accountToUpsert, 'PersonEmail')) {
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
            }
            else {
                /**
                 * If lead has not Company name - set Account name for correct converting
                 */
                foreach ($this->_cache['leadsToUpsert'] as $_upsertOn => $_objects) {
                    if (array_key_exists($_cid, $_objects)) {
                        if (!property_exists($_objects[$_cid], 'Company')) {
                            $this->_cache['leadsToUpsert'][$_upsertOn][$_cid]->Company = $accountToUpsert->Name;
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
            }
            else {
                // This is a Person Account
                $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = (string)$_item->id;
                if (!$this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount) {
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = (string)$_item->id;
                }
            }

            $_customer = $this->getEntityCache($_cid);
            $_customer->setSalesforceId($this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId);
            $_customer->setSalesforceAccountOwnerId($this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountOwnerId);
            if (!$this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount) {
                $_customer->setSalesforceAccountId($this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId);
                $_customer->setSalesforceIsPerson(true);
            }

            /**
             * Update lookup for lead convertation
             */
            $accountToUpsert->Id = (string)$_item->id;
            $this->_cache['accountLookup'][0][$_email] = $accountToUpsert;
            if (property_exists($this->_cache['accountLookup'][0][$_email], $this->_magentoId)) {
                $this->_cache['accountLookup'][0][$_email]->MagentoId = $this->_cache['accountLookup'][0][$_email]->{$this->_magentoId};
            } else {
                $this->_cache['accountLookup'][0][$_email]->MagentoId = $_cid;
            }

            if (property_exists($accountToUpsert, 'PersonEmail')) {
                if (!isset($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email])) {
                    $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email] = new stdClass();
                }
                $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->Id = (string)$_item->id;
                $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->IsPersonAccount = true;

            }

            return true;
        }

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
    }

    /**
     * work with sf response
     *
     * @param string $_on
     */
    protected function _assignLeadIds($_on = 'Id')
    {
        foreach ($this->_cache['batchCache']['leads'][$_on] as $_key => $_batchId) {
            $_batch = &$this->_cache['batch']['leads'][$_on][$_key];

            try {
                $response = $this->getBatch($this->_cache['bulkJobs']['lead'][$_on], $_batchId);
            } catch (Exception $e) {
                $response = array_fill(0, count($_batch), $this->_buildErrorResponse($e->getMessage()));

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Prepare batch #'. $_batchId .' Error: ' . $e->getMessage());
            }

            $_i = 0;
            $_batchKeys = array_keys($_batch);
            foreach ($response as $_item) {
                $_cid = $_batchKeys[$_i++];
                $_websiteId = $this->_getWebsiteIdByCustomerId($_cid);

                // report transaction
                $this->_cache['responses']['leads'][$_cid] = json_decode(json_encode($_item), TRUE);

                $_email = $this->_cache['entitiesUpdating'][$_cid];
                if (!property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'AccountId')) {
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = NULL;
                }

                if (!property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'SalesforceId')) {
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = NULL;
                }

                if ((string)$_item->success == "true") {
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId = (string)$_item->id;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 1;

                    if (!empty($this->_cache['leadsToUpsert'][$_on][$_cid]->OwnerId)) {
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadOwnerId
                            = $this->_prepareOwnerId($this->_cache['leadsToUpsert'][$_on][$_cid]->OwnerId);
                    } elseif (!empty($this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->OwnerId)) {
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadOwnerId
                            = $this->_prepareOwnerId($this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->OwnerId);
                    }

                    $_customer = $this->getEntityCache($_cid);
                    $_customer->setSalesforceLeadId($this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId);

                    /**
                     * Update lookup for lead convertation
                     */
                    if (isset($this->_cache['leadsToUpsert'][$_on][$_cid])) {
                        $this->_cache['leadsToUpsert'][$_on][$_cid]->Id = (string)$_item->id;

                        if (isset($this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email])) {
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
                    continue;
                }

                $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId = NULL;
                $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 0;
                $this->_processErrors($_item, 'lead', $_batch[$_cid]);
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
            foreach ($this->_cache['batchCache']['contacts'][$_on] as $_key => $_batchId) {
                $_batch = &$this->_cache['batch']['contacts'][$_on][$_key];
                try {
                    $response = $this->getBatch($this->_cache['bulkJobs']['contact'][$_on], $_batchId);
                } catch (Exception $e) {
                    $response = array_fill(0, count($_batch), $this->_buildErrorResponse($e->getMessage()));

                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError('Prepare batch #'. $_batchId .' Error: ' . $e->getMessage());
                }

                $_i = 0;
                $_batchKeys = array_keys($_batch);
                foreach ($response as $_item) {
                    $_cid = $_batchKeys[$_i++];
                    $_websiteId = $this->_getWebsiteIdByCustomerId($_cid);

                    //Report Transaction
                    $this->_cache['responses']['contacts'][$_cid] = json_decode(json_encode($_item), TRUE);

                    $_email = $this->_cache['entitiesUpdating'][$_cid];
                    if ((string)$_item->success == "true") {
                        $contactId = (string)$_item->id;

                        if (
                            $_on == 'Id'
                            && property_exists($_batch[$_cid], 'Id')
                            && $_batch[$_cid]->Id != $contactId
                        ) {
                            $contactId = $_batch[$_cid]->Id;
                        }

                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = $contactId;

                        if (!empty($this->_cache['contactsToUpsert'][$_on][$_cid]->OwnerId)) {
                            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->ContactOwnerId
                                = $this->_prepareOwnerId($this->_cache['contactsToUpsert'][$_on][$_cid]->OwnerId);
                        } elseif (!empty($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->OwnerId)) {
                            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->ContactOwnerId
                                = $this->_prepareOwnerId($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->OwnerId);
                        }

                        if (
                            !(property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'AccountId')
                              && $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId)
                            && is_array($this->_cache['accountsToContactLink'])
                            && array_key_exists($_cid, $this->_cache['accountsToContactLink'])
                            && $this->_cache['accountsToContactLink'][$_cid]
                        ) {
                            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = $this->_cache['accountsToContactLink'][$_cid];
                        }

                        $_customer = $this->getEntityCache($_cid);
                        $_customer->setSalesforceId($this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId);
                        if (property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'AccountId')) {
                            $_customer->setSalesforceAccountId($this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId);
                        }

                        if (property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'IsPersonAccount') && $this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount) {
                            $_customer->setSalesforceIsPerson($this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount);
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

                        continue;
                    }

                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = NULL;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId = NULL;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 0;
                    $this->_processErrors($_item, 'contact', $_batch[$_cid]);
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
                            } else if (array_key_exists($_magentoId, $this->_cache['contactsToUpsertBackup'][$cacheUpsertKey])) {
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
            if (
                Mage::helper('tnw_salesforce')->customerTypeRecordType() != TNW_Salesforce_Model_Config_Account_Recordtypes::B2B_ACCOUNT
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

    /**
     * @param $customerId
     * @return stdClass
     */
    protected function _getAccountToUpsert($customerId)
    {
        $accountToUpsert = isset($this->_cache['accountsToUpsert']['Id'][$customerId])
            ? $this->_cache['accountsToUpsert']['Id'][$customerId]
            : null;

        if (empty($accountToUpsert)) {
            foreach ($this->_cache['accountsToUpsertDuplicates'] as $_customerId=>$_customerIds) {
                if (!in_array($customerId, $_customerIds)) {
                    continue;
                }

                $accountToUpsert = $this->_cache['accountsToUpsert']['Id'][$_customerId];
            }
        }

        return $accountToUpsert;
    }
}
