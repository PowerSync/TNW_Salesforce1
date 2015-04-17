<?php

/**
 * Class TNW_Salesforce_Helper_Bulk_Opportunity
 */
class TNW_Salesforce_Helper_Bulk_Abandoned_Opportunity extends TNW_Salesforce_Helper_Salesforce_Abandoned_Opportunity
{
    /**
     * @var array
     */
    protected $_allResults = array(
        'opportunities' => array(),
        'opportunity_products' => array(),
        'opportunity_contact_roles' => array(),
    );

    /**
     * @param array $ids
     * @param bool $_isCron
     * @return bool
     */
    public function massAdd($ids = array(), $_isCron = false)
    {
        try {
            $this->_isCron = $_isCron;

            $_quoteNumbers = array();
            $_websites = $_emails = array();
            $sql = '';


            foreach ($ids as $_id) {
                // Clear Opportunity ID
                $sql .= "UPDATE `" . Mage::getResourceSingleton('sales/quote')->getMainTable() . "` SET sf_sync_force = 0, sf_insync = 0, created_at = created_at WHERE entity_id = " . $_id . ";";
            }
            if (!empty($sql)) {
                $this->_write->query($sql);
                Mage::helper('tnw_salesforce')->log("Opportunity ID and Sync Status for quote (#" . join(',', $ids) . ") were reset.");
            }
            $_guestCount = 0;

            foreach ($ids as $_count => $_id) {
                $_quote = $this->_loadQuote($_id);
                // Add to cache
                if (!Mage::registry('abandoned_cached_' . $_quote->getId())) {
                    Mage::register('abandoned_cached_' . $_quote->getId(), $_quote);
                }

                if (!$_quote->getId() || !$_quote->getId()) {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Sync for quote #' . $_id . ', quote could not be loaded!');
                    }
                    Mage::helper("tnw_salesforce")->log("SKIPPING: Sync for quote #" . $_id . ", quote could not be loaded!", 1, "sf-errors");
                    continue;
                }

                $this->_cache['abandonedCustomers'][$_quote->getId()] = $this->_getCustomer($_quote);
                $this->_cache['abandonedToCustomerId'][$_quote->getId()] = ($this->_cache['abandonedCustomers'][$_quote->getId()]->getId()) ? $this->_cache['abandonedCustomers'][$_quote->getId()]->getId() : 'guest-' . $_guestCount;
                if (!$this->_cache['abandonedCustomers'][$_quote->getId()]->getId()) {
                    $_guestCount++;
                }
                $this->_cache['abandonedToEmail'][$_quote->getId()] = strtolower($_quote->getCustomerEmail());

                if (empty($this->_cache['abandonedToEmail'][$_quote->getId()])) {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::helper("tnw_salesforce")->log("SKIPPED: Sync for quote #' . $_quote->getId() . ' failed, quote is missing an email address!");
                        Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for quote #' . $_quote->getId() . ' failed, quote is missing an email address!');
                    }
                    continue;
                }

                $_customerGroup = $_quote->getCustomerGroupId();
                if ($_customerGroup === NULL && !$this->isFromCLI()) {
                    $_customerGroup = Mage::getSingleton('customer/session')->getCustomerGroupId();
                }
                if (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($_customerGroup)) {
                    Mage::helper("tnw_salesforce")->log("SKIPPING: Sync for customer group #" . $_customerGroup . " is disabled!", 1, "sf-errors");
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for quote #' . $_quote->getId() . ', sync for customer group #' . $_customerGroup . ' is disabled!');
                    }
                    continue;
                }
                $_customerId = ($this->_cache['abandonedCustomers'][$_quote->getId()]->getId()) ? $this->_cache['abandonedCustomers'][$_quote->getId()]->getId() : 'guest-' . $_count;
                $_emails[$_customerId] = strtolower($_quote->getCustomerEmail());
                $_quoteNumbers[$_id] = $_quote->getId();

                $_websiteId = Mage::getModel('core/store')->load($_quote->getData('store_id'))->getWebsiteId();
                $_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];
            }

            if (empty($_quoteNumbers)) {
                Mage::helper("tnw_salesforce")->log("SKIPPING: Skipping syncronization, quotes array is empty!", 1, "sf-errors");

                return true;
            }
            $this->_cache['entitiesUpdating'] = $_quoteNumbers;

            $_quotes = array();
            foreach ($_quoteNumbers as $_id => $_quoteNumber) {
                $_quotes[$_id] = TNW_Salesforce_Helper_Abandoned::ABANDONED_CART_ID_PREFIX . $_quoteNumber;
            }

            // Force sync of the customer if Account Rename is turned on
            if (Mage::helper('tnw_salesforce')->canRenameAccount()) {
                $_customerToSync = array();
                foreach ($this->_cache['entitiesUpdating'] as $_key => $_quoteNumber) {
                    // here may be potential bug where we lost some quotes
                    $_customerToSync[$_quoteNumber] = $this->_getCustomer(Mage::registry('abandoned_cached_' . $_quoteNumber));
                }

                Mage::helper("tnw_salesforce")->log('Syncronizing Guest accounts...');
                $manualSync = Mage::helper('tnw_salesforce/bulk_customer');
                if ($manualSync->reset()) {
                    $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
                    $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());
                    $manualSync->forceAdd($_customerToSync, $this->_cache['abandonedCustomers']);

                    // here we use $this->_cache['abandonedCustomers']
                    $this->_cache['abandonedCustomers'] = $manualSync->process('bulk'); // and in process() we use $this->_toSyncQuoteCustomers which is not equal to $this->_cache['abandonedCustomers']
                }
            }


            $this->_cache['opportunityLookup'] = Mage::helper('tnw_salesforce/salesforce_data')->opportunityLookup($_quotes);
            $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emails, $_websites);
            $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($_emails, $_websites);

            $_customersToSync = array();
            $_leadsToLookup = array();

            $_count = 0;
            foreach ($this->_cache['entitiesUpdating'] as $_key => $_quoteNumber) {
                $_quote = $this->_loadQuote($_key);
                $_email = $this->_cache['abandonedToEmail'][$_quoteNumber];
                $_websiteId = Mage::getModel('core/store')->load($_quote->getData('store_id'))->getWebsiteId();
                // here may be potential bug where we lost some quotes
                if (!is_array($this->_cache['accountsLookup']) || !array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup']) || !array_key_exists($_email, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])) {
                    $_customerId = ($this->_cache['abandonedCustomers'][$_quote->getId()]->getId()) ? $this->_cache['abandonedCustomers'][$_quote->getId()]->getId() : 'guest-' . $_count;
                    $_leadsToLookup[$_customerId] = $_email;
                    $_leadsToLookupWebsites[$_customerId] = $this->_websiteSfIds[$_websiteId];

                    $this->_cache['abandonedCustomersToSync'][] = $_quoteNumber;
                    $_customersToSync[$_quoteNumber] = $this->_getCustomer($_quote);
                    $_count++;
                }
            }

            // Lookup Leads we may need to convert
            if (!empty($_leadsToLookup)) {
                $_customersToSync = $this->_updateAccountLookupData($_customersToSync);
            }

            if (!empty($_customersToSync)) {
                Mage::helper("tnw_salesforce")->log('Syncronizing Guest accounts...');
                $manualSync = Mage::helper('tnw_salesforce/bulk_customer');
                if ($manualSync->reset()) {
                    $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
                    $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());
                    $manualSync->forceAdd($_customersToSync, $this->_cache['abandonedCustomers']);

                    // here we use $this->_cache['abandonedCustomers']
                    $this->_cache['abandonedCustomers'] = $manualSync->process('bulk'); // and in process() we use $this->_toSyncQuoteCustomers which is not equal to $this->_cache['abandonedCustomers']
                }
                Mage::helper("tnw_salesforce")->log('Updating lookup cache...');
                // update Lookup values
                $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emails, $_websites);
                $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($_leadsToLookup, $_leadsToLookupWebsites);
                $_customersToSync = $this->_updateAccountLookupData($_customersToSync);
            }

            $_tmpArray = $this->_cache['abandonedCustomersToSync'];
            foreach ($_tmpArray as $_key => $_quoteNum) {
                $_quote = (Mage::registry('abandoned_cached_' . $_quoteNum)) ? Mage::registry('abandoned_cached_' . $_quoteNum) : $this->_loadQuote($_key);
                $_email = $this->_cache['abandonedToEmail'][$_quoteNum];
                $_websiteId = Mage::getModel('core/store')->load($_quote->getData('store_id'))->getWebsiteId();

                if (
                    (
                        is_array($this->_cache['leadLookup'])
                        && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['leadLookup'])
                        && array_key_exists($_email, $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]])
                    ) || (
                        is_array($this->_cache['accountsLookup'])
                        && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
                        && array_key_exists($_email, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])
                    ) || (
                        array_key_exists($_quoteNum, $this->_cache['abandonedCustomers'])
                        && is_object($this->_cache['abandonedCustomers'][$_quoteNum])
                        && $this->_cache['abandonedCustomers'][$_quoteNum]->getData('salesforce_id')
                        && $this->_cache['abandonedCustomers'][$_quoteNum]->getData('salesforce_account_id')
                    )
                ) {
                    unset($this->_cache['abandonedCustomersToSync'][$_key]);
                    unset($_customersToSync[$_quoteNum]);
                }
            }

            // Check if any customers for quotes have not been successfully synced
            if (!empty($_customersToSync)) {
                foreach ($_customersToSync as $_customer) {
                    $_email = $_customer->getEmail();
                    // Find matching quotes
                    $_oIds = array_keys($this->_cache['abandonedToEmail'], $_email);
                    if (!empty($_oIds)) {
                        foreach ($_oIds as $_quoteId) {
                            $_oIdsToRemove = array_keys($this->_cache['entitiesUpdating'], $_quoteId);
                            foreach ($_oIdsToRemove as $_idToRemove) {
                                unset($this->_cache['entitiesUpdating'][$_idToRemove]);
                            }
                        }
                    }
                }
            }

            if (!isset($manualSync)) {
                $manualSync = Mage::helper('tnw_salesforce/bulk_customer');
            }
            if (is_array($this->_cache['leadLookup'])) {
                $manualSync->reset();
                $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
                $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());
                $_foundAccounts = array();
                foreach ($this->_cache['leadLookup'] as $websiteleads) {
                    $_foundAccounts = array_merge($_foundAccounts, $manualSync->findCustomerAccounts(array_keys($websiteleads)));
                }
            } else {
                $_foundAccounts = array();
            }

            foreach ($this->_cache['abandonedToEmail'] as $_quoteNum => $_email) {
                Mage::helper('tnw_salesforce/salesforce_data_lead')->setParent($this)->prepareLeadConversionObject($_quoteNum, $_foundAccounts, 'abandoned');
            }

            return true;
        } catch (Exception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log("CRITICAL: " . $e->getMessage());
        }
    }

    /**
     * @comment call leads convertation method
     */
    protected function _convertLeads()
    {
        return Mage::helper('tnw_salesforce/salesforce_data_lead')->setParent($this)->convertLeadsBulk('abandoned');
    }

    /**
     * @param null $_quoteNumber
     * @return null
     */
    protected function _getCustomerAccountId($_quoteNumber = NULL)
    {
        $_accountId = NULL;
        // Get email from the quote object in Magento
        $_quoteEmail = $this->_cache['abandonedToEmail'][$_quoteNumber];
        // Get email from customer object in Magento
        $_customerEmail = (
            is_array($this->_cache['abandonedCustomers'])
            && array_key_exists($_quoteNumber, $this->_cache['abandonedCustomers'])
            && is_object($this->_cache['abandonedCustomers'][$_quoteNumber])
            && $this->_cache['abandonedCustomers'][$_quoteNumber]->getData('email')
        ) ? strtolower($this->_cache['abandonedCustomers'][$_quoteNumber]->getData('email')) : NULL;

        $_quote = $this->_loadQuote($_quoteNumber);;
        $_websiteId = Mage::getModel('core/store')->load($_quote->getData('store_id'))->getWebsiteId();

        if (is_array($this->_cache['accountsLookup']) && array_key_exists($_quoteEmail, $this->_cache['accountsLookup'])) {
            $_accountId = $this->_cache['accountsLookup'][$_quoteEmail]->AccountId;
        } elseif ($_customerEmail && $_quoteEmail != $_customerEmail && is_array($this->_cache['accountsLookup']) && array_key_exists($_customerEmail, $this->_cache['accountsLookup'])) {
            $_accountId = $this->_cache['accountsLookup'][$_customerEmail]->AccountId;
        } elseif (is_array($this->_cache['convertedLeads']) && array_key_exists($_quoteNumber, $this->_cache['convertedLeads'])) {
            $_accountId = $this->_cache['convertedLeads'][$_quoteNumber]->accountId;
        }

        if (is_array($this->_cache['accountsLookup']) && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup']) && array_key_exists($_quoteEmail, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])) {
            $_accountId = $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_quoteEmail]->AccountId;
        } elseif (
            $_customerEmail
            && $_quoteEmail != $_customerEmail
            && is_array($this->_cache['accountsLookup'])
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
            && array_key_exists($_customerEmail, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])
        ) {
            $_accountId = $this->_cache['accountsLookup'][$_customerEmail]->AccountId;
        } elseif (is_array($this->_cache['convertedLeads']) && array_key_exists($_quoteNumber, $this->_cache['convertedLeads'])) {
            $_accountId = $this->_cache['convertedLeads'][$_quoteNumber]->accountId;
        }
        return $_accountId;
    }

    /**
     * create opportunity object
     *
     * @param $quote Mage_Sales_Model_Quote
     */
    protected function _setOpportunityInfo($quote)
    {
        $_websiteId = Mage::getModel('core/store')->load($quote->getStoreId())->getWebsiteId();

        $this->_updateQuoteStageName($quote);
        $_quoteNumber = $quote->getId();
        $_email = $this->_cache['abandonedToEmail'][$_quoteNumber];

        // Link to a Website
        if (
            $_websiteId != NULL
            && array_key_exists($_websiteId, $this->_websiteSfIds)
            && $this->_websiteSfIds[$_websiteId]
        ) {
            $this->_obj->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()} = $this->_websiteSfIds[$_websiteId];
        }

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $quote->getData('quote_currency_code');
        }

        $magentoQuoteNumber = TNW_Salesforce_Helper_Abandoned::ABANDONED_CART_ID_PREFIX . $_quoteNumber;
        // Magento Quote ID
        $this->_obj->{$this->_magentoId} = $magentoQuoteNumber;

        // Force configured pricebook
        $this->_assignPricebookToQuote($quote);

        // Close Date
        if ($quote->getUpdatedAt()) {

            $closeDate = new Zend_Date();
            $closeDate->setDate($quote->getUpdatedAt(), Varien_Date::DATETIME_INTERNAL_FORMAT);

            $closeDate->addDay(Mage::helper('tnw_salesforce/abandoned')->getAbandonedCloseTimeAfter($quote));

            // Always use quote date as closing date if quote already exists
            $this->_obj->CloseDate = gmdate(DATE_ATOM, $closeDate->getTimestamp());

        } else {
            // this should never happen
            $this->_obj->CloseDate = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
        }

        // Account ID
        $this->_obj->AccountId = $this->_getCustomerAccountId($_quoteNumber);
        // For guest, extract converted Account Id
        if (!$this->_obj->AccountId) {
            $this->_obj->AccountId = (
                array_key_exists($_quoteNumber, $this->_cache['convertedLeads'])
                && property_exists($this->_cache['convertedLeads'][$_quoteNumber], 'accountId')
            ) ? $this->_cache['convertedLeads'][$_quoteNumber]->accountId : NULL;
        }

        //Process mapping
        Mage::getSingleton('tnw_salesforce/sync_mapping_abandoned_opportunity')
            ->setSync($this)
            ->processMapping($quote);

        //Get Account Name from Salesforce
        $_accountName = (
            $this->_cache['accountsLookup']
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
            && array_key_exists($_email, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])
            && $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->AccountName
        ) ? $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->AccountName : NULL;
        if (!$_accountName) {
            $_accountName = ($quote->getBillingAddress()->getCompany()) ? $quote->getBillingAddress()->getCompany() : NULL;
            if (!$_accountName) {
                $_accountName = ($_accountName && !$quote->getShippingAddress()->getCompany()) ? $_accountName && !$quote->getShippingAddress()->getCompany() : NULL;
                if (!$_accountName) {
                    $_accountName = $quote->getCustomerFirstname() . " " . $quote->getCustomerLastname();
                }
            }
        }

        $this->_setOpportunityName($_quoteNumber, $_accountName);
        unset($quote);
    }

    protected function _pushRemainingOpportunityData()
    {
        if (!empty($this->_cache['opportunityLineItemsToUpsert'])) {
            if (!$this->_cache['bulkJobs']['opportunityProducts']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['opportunityProducts']['Id'] = $this->_createJob('OpportunityLineItem', 'upsert', 'Id');
                Mage::helper('tnw_salesforce')->log('Syncronizing Opportunity Products, created job: ' . $this->_cache['bulkJobs']['opportunityProducts']['Id']);
            }
            $this->_pushChunked($this->_cache['bulkJobs']['opportunityProducts']['Id'], 'opportunityProducts', $this->_cache['opportunityLineItemsToUpsert']);

            Mage::helper('tnw_salesforce')->log('Checking if Opportunity Products were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunityProducts']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunityProducts']['Id']);
                Mage::helper('tnw_salesforce')->log('Still checking opportunityLineItemsToUpsert (job: ' . $this->_cache['bulkJobs']['opportunityProducts']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['opportunityProducts']['Id']);
            }
            Mage::helper('tnw_salesforce')->log('Opportunities Products sync is complete! Moving on...');
        }

        if (!empty($this->_cache['contactRolesToUpsert'])) {
            if (!$this->_cache['bulkJobs']['customerRoles']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['customerRoles']['Id'] = $this->_createJob('OpportunityContactRole', 'upsert', 'Id');
                Mage::helper('tnw_salesforce')->log('Syncronizing Opportunity Contact Roles, created job: ' . $this->_cache['bulkJobs']['customerRoles']['Id']);
            }
            $this->_pushChunked($this->_cache['bulkJobs']['customerRoles']['Id'], 'opportunityContactRoles', $this->_cache['contactRolesToUpsert']);

            Mage::helper('tnw_salesforce')->log('Checking if Opportunity Contact Roles were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['customerRoles']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['customerRoles']['Id']);
                Mage::helper('tnw_salesforce')->log('Still checking contactRolesToUpsert (job: ' . $this->_cache['bulkJobs']['customerRoles']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['customerRoles']['Id']);
            }
            Mage::helper('tnw_salesforce')->log('Opportunities Contact Roles sync is complete! Moving on...');
        }

        if (strval($_result) != 'exception') {
            $this->_checkRemainingData();
        }

        if (!empty($this->_cache['notesToUpsert'])) {
            if (!$this->_cache['bulkJobs']['notes']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['notes']['Id'] = $this->_createJob('Note', 'upsert', 'Id');
                Mage::helper('tnw_salesforce')->log('Syncronizing Notes, created job: ' . $this->_cache['bulkJobs']['notes']['Id']);
            }
            $this->_pushChunked($this->_cache['bulkJobs']['notes']['Id'], 'notes', $this->_cache['notesToUpsert']);

            Mage::helper('tnw_salesforce')->log('Checking if Notes were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['notes']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['notes']['Id']);
                Mage::helper('tnw_salesforce')->log('Still checking notesToUpsert (job: ' . $this->_cache['bulkJobs']['notes']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['notes']['Id']);
            }
            Mage::helper('tnw_salesforce')->log('Notes sync is complete! Moving on...');

        }
    }

    protected function _pushOpportunitiesToSalesforce()
    {
        if (!empty($this->_cache['opportunitiesToUpsert'])) {
            // assign owner id to opportunity
            $this->_assignOwnerIdToOpp();

            if (!$this->_cache['bulkJobs']['opportunity'][$this->_magentoId]) {
                // Create Job
                $this->_cache['bulkJobs']['opportunity'][$this->_magentoId] = $this->_createJob('Opportunity', 'upsert', $this->_magentoId);
                Mage::helper('tnw_salesforce')->log('Syncronizing Opportunities, created job: ' . $this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            }
            $this->_pushChunked($this->_cache['bulkJobs']['opportunity'][$this->_magentoId], 'opportunities', $this->_cache['opportunitiesToUpsert'], $this->_magentoId);

            Mage::helper('tnw_salesforce')->log('Checking if Opportunities were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
                Mage::helper('tnw_salesforce')->log('Still checking opportunitiesToUpsert (job: ' . $this->_cache['bulkJobs']['opportunity'][$this->_magentoId] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            }
            Mage::helper('tnw_salesforce')->log('Opportunities sync is complete! Moving on...');

            if (strval($_result) != 'exception') {
                $this->_assignOpportunityIds();
            }
        } else {
            Mage::helper('tnw_salesforce')->log('No Opportunities found queued for the synchronization!');
        }
    }

    protected function _checkRemainingData()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        if (array_key_exists('opportunityProducts', $this->_cache['batchCache'])) {
            foreach ($this->_cache['batchCache']['opportunityProducts']['Id'] as $_key => $_batchId) {
                $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['opportunityProducts']['Id'] . '/batch/' . $_batchId . '/result');
                try {
                    $response = $this->_client->request()->getBody();
                    $response = simplexml_load_string($response);
                    $_i = 0;
                    $_batch = $this->_cache['batch']['opportunityProducts']['Id'][$_key];
                    foreach ($response as $_item) {
                        //Report Transaction
                        $this->_cache['responses']['opportunityLineItems'][] = json_decode(json_encode($_item), TRUE);
                        $_opportunityId = (string)$_batch[$_i]->OpportunityId;
                        if ($_item->success == "false") {
                            $_oid = array_search($_opportunityId, $this->_cache['upsertedOpportunities']);
                            $this->_processErrors($_item, 'opportunityProduct', $_batch[$_i]);
                            if (!in_array($_oid, $this->_cache['failedOpportunities'])) {
                                $this->_cache['failedOpportunities'][] = $_oid;
                            }
                        }
                        $_i++;
                    }
                } catch (Exception $e) {
                    // TODO:  Log error, quit
                }
            }
        }

        if (array_key_exists('opportunityContactRoles', $this->_cache['batchCache'])) {
            foreach ($this->_cache['batchCache']['opportunityContactRoles']['Id'] as $_key => $_batchId) {
                $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['customerRoles']['Id'] . '/batch/' . $_batchId . '/result');
                try {
                    $response = $this->_client->request()->getBody();
                    $response = simplexml_load_string($response);
                    $_i = 0;
                    $_batch = $this->_cache['batch']['opportunityContactRoles']['Id'][$_key];
                    foreach ($response as $_rKey => $_item) {
                        //Report Transaction
                        $this->_cache['responses']['opportunityCustomerRoles'][] = json_decode(json_encode($_item), TRUE);

                        $_opportunityId = (string)$_batch[$_i]->OpportunityId;
                        $_i++;
                        if ($_item->success == "false") {
                            $_oid = array_search($_opportunityId, $this->_cache['upsertedOpportunities']);
                            $this->_processErrors($_item, 'opportunityProduct', $_batch[$_i]);
                            if (!in_array($_oid, $this->_cache['failedOpportunities'])) {
                                $this->_cache['failedOpportunities'][] = $_oid;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // TODO:  Log error, quit
                }
            }
        }

        $sql = '';
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_quoteNumber) {
            if (!in_array($_quoteNumber, $this->_cache['failedOpportunities'])) {
                $sql .= "UPDATE `" . Mage::getResourceSingleton('sales/quote')->getMainTable() . "` SET sf_insync = 1, created_at = created_at WHERE entity_id = " . $_key . ";";
            }
        }
        if ($sql != '') {
            Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
            $this->_write->query($sql);
        }
    }

    protected function _assignOpportunityIds()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());
        $_entityArray = array_flip($this->_cache['entitiesUpdating']);
        $sql = '';

        foreach ($this->_cache['batchCache']['opportunities'][$this->_magentoId] as $_key => $_batchId) {
            $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['opportunity'][$this->_magentoId] . '/batch/' . $_batchId . '/result');
            try {
                $response = $this->_client->request()->getBody();
                $response = simplexml_load_string($response);
                $_i = 0;
                $_batch = array_keys($this->_cache['batch']['opportunities'][$this->_magentoId][$_key]);
                foreach ($response as $_item) {
                    $_oid = $_batch[$_i];

                    //Report Transaction
                    $this->_cache['responses']['opportunities'][$_oid] = json_decode(json_encode($_item), TRUE);

                    if ($_item->success == "true") {
                        $this->_cache['upsertedOpportunities'][$_oid] = (string)$_item->id;
                        $sql .= "UPDATE `" . Mage::getResourceSingleton('sales/quote')->getMainTable() . "` SET salesforce_id = '" . $this->_cache['upsertedOpportunities'][$_oid] . "', created_at = created_at WHERE entity_id = " . $_entityArray[$_oid] . ";";
                        Mage::helper('tnw_salesforce')->log('Opportunity Upserted: ' . $this->_cache['upsertedOpportunities'][$_oid]);

                        //unset($this->_cache['opportunitiesToUpsert'][$_oid]);
                    } else {
                        $this->_cache['failedOpportunities'][] = $_oid;
                        $this->_processErrors($_item, 'opportunity', $this->_cache['batch']['opportunities'][$this->_magentoId][$_key][$_oid]);
                    }
                    $_i++;
                }
            } catch (Exception $e) {
                // TODO:  Log error, quit
            }
        }
        if (!empty($sql)) {
            Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
            $this->_write->query($sql);
        }
    }

    /**
     * @param null $_jobId
     * @param $_batchType
     * @param array $_entities
     * @param string $_on
     * @return bool
     */
    protected function _pushChunked($_jobId = NULL, $_batchType, $_entities = array(), $_on = 'Id')
    {
        if (!empty($_entities) && $_jobId) {
            if (!array_key_exists($_batchType, $this->_cache['batch'])) {
                $this->_cache['batch'][$_batchType] = array();
            }
            if (!array_key_exists($_on, $this->_cache['batch'][$_batchType])) {
                $this->_cache['batch'][$_batchType][$_on] = array();
            }
            $_ttl = count($_entities); // 205
            $_success = true;
            if ($_ttl > $this->_maxBatchLimit) {
                $_steps = ceil($_ttl / $this->_maxBatchLimit);
                if ($_steps == 0) {
                    $_steps = 1;
                }
                for ($_i = 0; $_i < $_steps; $_i++) {
                    $_start = $_i * $this->_maxBatchLimit;
                    $_itemsToPush = array_slice($_entities, $_start, $this->_maxBatchLimit, true);
                    if (!array_key_exists($_i, $this->_cache['batch'][$_batchType][$_on])) {
                        $this->_cache['batch'][$_batchType][$_on][$_i] = array();
                    }
                    $_success = $this->_pushSegment($_jobId, $_batchType, $_itemsToPush, $_i, $_on);
                }
            } else {
                if (!array_key_exists(0, $this->_cache['batch'][$_batchType][$_on])) {
                    $this->_cache['batch'][$_batchType][$_on][0] = array();
                }
                $_success = $this->_pushSegment($_jobId, $_batchType, $_entities, 0, $_on);

            }
            if (!$_success) {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . uc_words($_batchType) . ' upsert failed!');
                }
                Mage::helper('tnw_salesforce')->log('ERROR: ' . uc_words($_batchType) . ' upsert failed!');

                return false;
            }
        }

        return true;
    }

    protected function _prepareContactRoles()
    {
        Mage::helper('tnw_salesforce')->log('----------Prepare Opportunity Contact Role: Start----------');
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_quoteNumber) {
            if (in_array($_quoteNumber, $this->_cache['failedOpportunities'])) {
                Mage::helper('tnw_salesforce')->log('QUOTE (' . $_quoteNumber . '): Skipping, issues with upserting an opportunity!');
                continue;
            }
            Mage::helper('tnw_salesforce')->log('******** QUOTE (' . $_quoteNumber . ') ********');
            $this->_obj = new stdClass();

            $_customerId = $this->_cache['abandonedToCustomerId'][$_quoteNumber];
            if (!Mage::registry('customer_cached_' . $_customerId)) {
                $_customer = $this->_cache['abandonedCustomers'][$_quoteNumber];
            } else {
                $_customer = Mage::registry('customer_cached_' . $_customerId);
            }

            $_quote = $this->_loadQuote($_key);
            $_email = strtolower($this->_cache['abandonedToEmail'][$_quoteNumber]);
            $_websiteId = Mage::getModel('core/store')->load($_quote->getData('store_id'))->getWebsiteId();
            if (
                array_key_exists('accountsLookup', $this->_cache)
                && is_array($this->_cache['accountsLookup'])
                && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
                && array_key_exists($_email, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])
                && is_object($this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_email])
                && property_exists($this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_email], 'Id')
            ) {
                $this->_obj->ContactId = $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->Id;
            } elseif (array_key_exists($_quoteNumber, $this->_cache['convertedLeads'])) {
                $this->_obj->ContactId = $this->_cache['convertedLeads'][$_quoteNumber]->contactId;
            } else {
                $this->_obj->ContactId = $_customer->getSalesforceId();
            }

            // Check if already exists
            $_skip = false;

            $magentoQuoteNumber = TNW_Salesforce_Helper_Abandoned::ABANDONED_CART_ID_PREFIX . $_quoteNumber;

            if ($this->_cache['opportunityLookup'] && array_key_exists($magentoQuoteNumber, $this->_cache['opportunityLookup']) && $this->_cache['opportunityLookup'][$magentoQuoteNumber]->OpportunityContactRoles) {
                foreach ($this->_cache['opportunityLookup'][$magentoQuoteNumber]->OpportunityContactRoles->records as $_role) {
                    if (property_exists($this->_obj, 'ContactId') && property_exists($_role, 'ContactId') && $_role->ContactId == $this->_obj->ContactId) {
                        if ($_role->Role == Mage::helper('tnw_salesforce/abandoned')->getDefaultCustomerRole()) {
                            // No update required
                            Mage::helper('tnw_salesforce')->log('Contact Role information is the same, no update required!');
                            $_skip = true;
                            break;
                        }
                        $this->_obj->Id = $_role->Id;
                        $this->_obj->ContactId = $_role->ContactId;
                        break;
                    }
                }
            }

            if (!$_skip) {
                $this->_obj->IsPrimary = true;
                $this->_obj->OpportunityId = $this->_cache['upsertedOpportunities'][$_quoteNumber];

                $this->_obj->Role = Mage::helper('tnw_salesforce/abandoned')->getDefaultCustomerRole();

                foreach ($this->_obj as $key => $_item) {
                    Mage::helper('tnw_salesforce')->log("OpportunityContactRole Object: " . $key . " = '" . $_item . "'");
                }

                if (property_exists($this->_obj, 'ContactId') && $this->_obj->ContactId) {
                    $this->_cache['contactRolesToUpsert'][] = $this->_obj;
                } else {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('Was not able to convert customer Lead, skipping Opportunity Contact Role assignment. Please synchronize customer (email: ' . $_email . ')');
                    }
                    Mage::helper('tnw_salesforce')->log('Was not able to convert customer Lead, skipping Opportunity Contact Role assignment. Please synchronize customer (email: ' . $_email . ')');
                }
            }
        }
        Mage::helper('tnw_salesforce')->log('----------Prepare Opportunity Contact Role: End----------');
    }

    protected function _onComplete()
    {
        // Close Jobs
        if ($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]) {
            $this->_closeJob($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
        }
        if ($this->_cache['bulkJobs']['opportunityProducts']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['opportunityProducts']['Id']);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['opportunityProducts']['Id']);
        }
        if ($this->_cache['bulkJobs']['customerRoles']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['customerRoles']['Id']);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['customerRoles']['Id']);
        }
        if ($this->_cache['bulkJobs']['notes']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['notes']['Id']);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['notes']['Id']);
        }

        Mage::helper('tnw_salesforce')->log('Clearing bulk sync cache...');

        $this->_cache['bulkJobs'] = array(
            'opportunity' => array($this->_magentoId => NULL),
            'opportunityProducts' => array('Id' => NULL),
            'customerRoles' => array('Id' => NULL),
            'notes' => array('Id' => NULL),
        );

        parent::_onComplete();
    }

    /**
     * @return bool
     */
    public function reset()
    {
        parent::reset();

        $this->_cache['bulkJobs'] = array(
            'opportunity' => array($this->_magentoId => NULL),
            'opportunityProducts' => array('Id' => NULL),
            'customerRoles' => array('Id' => NULL),
            'notes' => array('Id' => NULL),
        );

        $this->_cache['batch'] = array();
        $this->_cache['batchCache'] = array();
        $this->_cache['duplicateLeadConversions'] = array();

        $valid = $this->check();

        return $valid;
    }

    public function process($type = 'soft')
    {

        /**
         * @comment apply bulk server settings
         */
        $this->getServerHelper()->apply(TNW_Salesforce_Helper_Config_Server::BULK);

        $result = parent::process($type);

        /**
         * @comment restore server settings
         */
        $this->getServerHelper()->apply();

        return $result;
    }
}