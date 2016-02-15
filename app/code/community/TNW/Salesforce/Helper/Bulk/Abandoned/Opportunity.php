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
     * @comment call leads convertation method
     */
    protected function _convertLeads()
    {
        return Mage::helper('tnw_salesforce/salesforce_data_lead')->setParent($this)->convertLeadsBulk('quote');
    }

    /**
     * @param null $_quoteNumber
     * @return null
     */
    protected function _getCustomerAccountId($_quoteNumber = NULL)
    {
        $_accountId = NULL;
        // Get email from the quote object in Magento
        $_quoteEmail = $this->_cache['quoteToEmail'][$_quoteNumber];
        // Get email from customer object in Magento
        $_customerEmail = (
            is_array($this->_cache['quoteCustomers'])
            && array_key_exists($_quoteNumber, $this->_cache['quoteCustomers'])
            && is_object($this->_cache['quoteCustomers'][$_quoteNumber])
            && $this->_cache['quoteCustomers'][$_quoteNumber]->getData('email')
        ) ? strtolower($this->_cache['quoteCustomers'][$_quoteNumber]->getData('email')) : NULL;

        if (is_array($this->_cache['accountsLookup']) && array_key_exists($_quoteEmail, $this->_cache['accountsLookup'][0])) {
            $_accountId = $this->_cache['accountsLookup'][0][$_quoteEmail]->Id;
        } elseif ($_customerEmail && $_quoteEmail != $_customerEmail && is_array($this->_cache['accountsLookup']) && array_key_exists($_customerEmail, $this->_cache['accountsLookup'][0])) {
            $_accountId = $this->_cache['accountsLookup'][$_customerEmail][0]->Id;
        }

        return $_accountId;
    }

    /**
     * create opportunity object
     *
     * @param $quote Mage_Sales_Model_Quote
     */
    protected function _setEntityInfo($quote)
    {
        $_websiteId = Mage::getModel('core/store')->load($quote->getStoreId())->getWebsiteId();

        $this->_updateQuoteStageName($quote);
        $_quoteNumber = $quote->getId();
        $_email = $this->_cache['quoteToEmail'][$_quoteNumber];

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

        $magentoQuoteNumber = TNW_Salesforce_Helper_Config_Sales_Abandoned::ABANDONED_CART_ID_PREFIX . $_quoteNumber;
        // Magento Quote ID
        $this->_obj->{$this->_magentoId} = $magentoQuoteNumber;

        // Force configured pricebook
        $this->_assignPricebookToOrder($quote);

        // Close Date
        if ($quote->getUpdatedAt()) {

            $closeDate = new Zend_Date();
            $closeDate->setDate($quote->getUpdatedAt(), Varien_Date::DATETIME_INTERNAL_FORMAT);

            $closeDate->addDay(Mage::helper('tnw_salesforce/config_sales_abandoned')->getAbandonedCloseTimeAfter($quote));

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
        Mage::getSingleton('tnw_salesforce/sync_mapping_quote_opportunity')
            ->setSync($this)
            ->processMapping($quote);

        //Get Account Name from Salesforce
        $_accountName = (
            $this->_cache['accountsLookup']
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
            && array_key_exists($_email, $this->_cache['accountsLookup'][0])
            && $this->_cache['accountsLookup'][0][$_email]->AccountName
        ) ? $this->_cache['accountsLookup'][0][$_email]->AccountName : NULL;
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

    protected function _pushRemainingEntityData()
    {
        if (!empty($this->_cache['opportunityLineItemsToUpsert'])) {
            if (!$this->_cache['bulkJobs']['opportunityProducts']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['opportunityProducts']['Id'] = $this->_createJob('OpportunityLineItem', 'upsert', 'Id');
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Syncronizing Opportunity Products, created job: ' . $this->_cache['bulkJobs']['opportunityProducts']['Id']);
            }
            $this->_pushChunked($this->_cache['bulkJobs']['opportunityProducts']['Id'], 'opportunityProducts', $this->_cache['opportunityLineItemsToUpsert']);

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Checking if Opportunity Products were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunityProducts']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunityProducts']['Id']);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Still checking opportunityLineItemsToUpsert (job: ' . $this->_cache['bulkJobs']['opportunityProducts']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['opportunityProducts']['Id']);
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Opportunities Products sync is complete! Moving on...');
        }

        if (!empty($this->_cache['contactRolesToUpsert'])) {
            if (!$this->_cache['bulkJobs']['customerRoles']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['customerRoles']['Id'] = $this->_createJob('OpportunityContactRole', 'upsert', 'Id');
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Syncronizing Opportunity Contact Roles, created job: ' . $this->_cache['bulkJobs']['customerRoles']['Id']);
            }
            $this->_pushChunked($this->_cache['bulkJobs']['customerRoles']['Id'], 'opportunityContactRoles', $this->_cache['contactRolesToUpsert']);

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Checking if Opportunity Contact Roles were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['customerRoles']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['customerRoles']['Id']);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Still checking contactRolesToUpsert (job: ' . $this->_cache['bulkJobs']['customerRoles']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['customerRoles']['Id']);
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Opportunities Contact Roles sync is complete! Moving on...');
        }

        if (strval($_result) != 'exception') {
            $this->_checkRemainingData();
        }

        if (!empty($this->_cache['notesToUpsert'])) {
            if (!$this->_cache['bulkJobs']['notes']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['notes']['Id'] = $this->_createJob('Note', 'upsert', 'Id');
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Syncronizing Notes, created job: ' . $this->_cache['bulkJobs']['notes']['Id']);
            }
            $this->_pushChunked($this->_cache['bulkJobs']['notes']['Id'], 'notes', $this->_cache['notesToUpsert']);

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Checking if Notes were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['notes']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['notes']['Id']);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Still checking notesToUpsert (job: ' . $this->_cache['bulkJobs']['notes']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['notes']['Id']);
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Notes sync is complete! Moving on...');

        }
    }

    protected function _pushEntity()
    {
        if (!empty($this->_cache['opportunitiesToUpsert'])) {
            // assign owner id to opportunity
            $this->_assignOwnerIdToOpp();

            if (!$this->_cache['bulkJobs']['opportunity'][$this->_magentoId]) {
                // Create Job
                $this->_cache['bulkJobs']['opportunity'][$this->_magentoId] = $this->_createJob('Opportunity', 'upsert', $this->_magentoId);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Syncronizing Opportunities, created job: ' . $this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            }
            $this->_pushChunked($this->_cache['bulkJobs']['opportunity'][$this->_magentoId], 'opportunities', $this->_cache['opportunitiesToUpsert'], $this->_magentoId);

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Checking if Opportunities were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Still checking opportunitiesToUpsert (job: ' . $this->_cache['bulkJobs']['opportunity'][$this->_magentoId] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Opportunities sync is complete! Moving on...');

            if (strval($_result) != 'exception') {
                $this->_assignOpportunityIds();
            }
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('No Opportunities found queued for the synchronization!');
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
                    $_batch = array_values($this->_cache['batch']['opportunityProducts']['Id'][$_key]);
                    foreach ($response as $_item) {
                        $_opportunityId = (string)$_batch[$_i]->OpportunityId;
                        $_oid = array_search($_opportunityId, $this->_cache  ['upserted' . $this->getManyParentEntityType()]);
                        //Report Transaction
                        $this->_cache['responses']['opportunityLineItems'][$_oid]['subObj'][] = json_decode(json_encode($_item), TRUE);
                        if ($_item->success == "false") {
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
                        $_opportunityId = (string)$_batch[$_i]->OpportunityId;
                        $_oid = array_search($_opportunityId, $this->_cache['upserted'.$this->getManyParentEntityType()]);

                        //Report Transaction
                        $this->_cache['responses']['opportunityCustomerRoles'][$_oid]['subObj'][] = json_decode(json_encode($_item), TRUE);
                        if ($_item->success == "false") {
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

        $sql = '';
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_quoteNumber) {
            if (!in_array($_quoteNumber, $this->_cache['failedOpportunities'])) {
                $sql .= "UPDATE `" . Mage::getResourceSingleton('sales/quote')->getMainTable() . "` SET sf_insync = 1, created_at = created_at WHERE entity_id = " . $_key . ";";
            }
        }
        if ($sql != '') {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
            Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
        }
    }

    protected function _assignOpportunityIds()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());
        $_entityArray = array_flip($this->_cache['entitiesUpdating']);
        $sql = '';
        $helper = Mage::helper('tnw_salesforce');
        $quoteTable = Mage::getResourceSingleton('sales/quote')->getMainTable();
        $connection = $helper->getDbConnection();

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
                        $this->_cache['upserted' . $this->getManyParentEntityType()][$_oid] = (string)$_item->id;
                        $updateFields = array(
                            'sf_insync = 1',
                            $connection->quoteInto('salesforce_id = ?', $_item->id),
                        );

                        $customer = $this->_cache  ['quoteCustomers'][$_oid];
                        $updateFields[] = $connection->quoteInto('contact_salesforce_id = ?',
                            $customer->getData('salesforce_id') ? : null);
                        $updateFields[] = $connection->quoteInto('account_salesforce_id = ?',
                            $customer->getData('salesforce_account_id') ? : null);
                        $sql .= "UPDATE `" . $quoteTable
                            . "` SET " . implode(', ', $updateFields)
                            . " WHERE entity_id = " . $_entityArray[$_oid] . ";";

                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Opportunity Upserted: ' . $_item->id);
                    } else {
                        $this->_cache['failedOpportunities'][] = $_oid;
                        $this->_processErrors($_item, 'opportunity',
                            $this->_cache['batch']['opportunities'][$this->_magentoId][$_key][$_oid]);
                    }
                    ++$_i;
                }
            } catch (Exception $e) {
                // TODO:  Log error, quit
                Mage::logException($e);
            }
        }
        if (!empty($sql)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
            $connection->query($sql);
        }
    }

    protected function _prepareContactRoles()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Opportunity Contact Role: Start----------');
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_quoteNumber) {
            if (in_array($_quoteNumber, $this->_cache['failedOpportunities'])) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('QUOTE (' . $_quoteNumber . '): Skipping, issues with upserting an opportunity!');
                continue;
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('******** QUOTE (' . $_quoteNumber . ') ********');
            $this->_obj = new stdClass();

            $_quote = $this->_loadEntityByCache($_key, $_quoteNumber);
            $_customer = $this->getQuoteCustomer($_quote);

            $_email = strtolower($this->_cache['quoteToEmail'][$_quoteNumber]);
            $_websiteId = Mage::getModel('core/store')->load($_quote->getData('store_id'))->getWebsiteId();
            if (
                array_key_exists('accountsLookup', $this->_cache)
                && is_array($this->_cache['accountsLookup'])
                && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
                && array_key_exists($_email, $this->_cache['accountsLookup'][0])
                && is_object($this->_cache['accountsLookup'][0][$_email])
                && property_exists($this->_cache['accountsLookup'][0][$_email], 'Id')
            ) {
                $this->_obj->ContactId = $this->_cache['accountsLookup'][0][$_email]->Id;
            } elseif (array_key_exists($_quoteNumber, $this->_cache['convertedLeads'])) {
                $this->_obj->ContactId = $this->_cache['convertedLeads'][$_quoteNumber]->contactId;
            } else {
                $this->_obj->ContactId = $_customer->getSalesforceId();
            }

            // Check if already exists
            $_skip = false;

            $magentoQuoteNumber = TNW_Salesforce_Helper_Config_Sales_Abandoned::ABANDONED_CART_ID_PREFIX . $_quoteNumber;

            if ($this->_cache['opportunityLookup'] && array_key_exists($magentoQuoteNumber, $this->_cache['opportunityLookup']) && $this->_cache['opportunityLookup'][$magentoQuoteNumber]->OpportunityContactRoles) {
                foreach ($this->_cache['opportunityLookup'][$magentoQuoteNumber]->OpportunityContactRoles->records as $_role) {
                    if (property_exists($this->_obj, 'ContactId') && property_exists($_role, 'ContactId') && $_role->ContactId == $this->_obj->ContactId) {
                        if ($_role->Role == Mage::helper('tnw_salesforce/config_sales_abandoned')->getDefaultCustomerRole()) {
                            // No update required
                            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Contact Role information is the same, no update required!');
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
                $this->_obj->OpportunityId = $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_quoteNumber];

                $this->_obj->Role = Mage::helper('tnw_salesforce/config_sales_abandoned')->getDefaultCustomerRole();

                foreach ($this->_obj as $key => $_item) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("OpportunityContactRole Object: " . $key . " = '" . $_item . "'");
                }

                if (property_exists($this->_obj, 'ContactId') && $this->_obj->ContactId) {
                    $this->_cache['contactRolesToUpsert'][] = $this->_obj;
                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Was not able to convert customer Lead, skipping Opportunity Contact Role assignment. Please synchronize customer (email: ' . $_email . ')');
                }
            }
        }
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Opportunity Contact Role: End----------');
    }

    protected function _onComplete()
    {
        // Close Jobs
        if ($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]) {
            $this->_closeJob($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
        }
        if ($this->_cache['bulkJobs']['opportunityProducts']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['opportunityProducts']['Id']);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['opportunityProducts']['Id']);
        }
        if ($this->_cache['bulkJobs']['customerRoles']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['customerRoles']['Id']);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['customerRoles']['Id']);
        }
        if ($this->_cache['bulkJobs']['notes']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['notes']['Id']);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['notes']['Id']);
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Clearing bulk sync cache...');

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