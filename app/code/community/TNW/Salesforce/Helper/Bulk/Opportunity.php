<?php

/**
 * Class TNW_Salesforce_Helper_Bulk_Opportunity
 */
class TNW_Salesforce_Helper_Bulk_Opportunity extends TNW_Salesforce_Helper_Salesforce_Opportunity
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
       return Mage::helper('tnw_salesforce/salesforce_data_lead')->setParent($this)->convertLeadsBulk('order');
    }

    /**
     * create opportunity object
     *
     * @param $order
     */
    protected function _setOpportunityInfo($order)
    {
        $_websiteId = Mage::getModel('core/store')->load($order->getStoreId())->getWebsiteId();

        $this->_updateOrderStageName($order);
        $_orderNumber = $order->getRealOrderId();
        $_email = $this->_cache['orderToEmail'][$_orderNumber];

        // Link to a Website
        if (
            $_websiteId != NULL
            && array_key_exists($_websiteId, $this->_websiteSfIds)
            && $this->_websiteSfIds[$_websiteId]
        ) {
            $this->_obj->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()} = $this->_websiteSfIds[$_websiteId];
        }

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $order->getData('order_currency_code');
        }

        // Magento Order ID
        $this->_obj->{$this->_magentoId} = $_orderNumber;
        // Force configured pricebook
        $this->_assignPricebookToOrder($order);

        // Close Date
        if ($order->getCreatedAt()) {
            // Always use order date as closing date if order already exists
            $this->_obj->CloseDate = gmdate(DATE_ATOM, strtotime($order->getCreatedAt()));
        } else {
            // this should never happen
            $this->_obj->CloseDate = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
        }

        // Account ID
        $this->_obj->AccountId = $this->_getCustomerAccountId($_orderNumber);
        // For guest, extract converted Account Id
        if (!$this->_obj->AccountId) {
            $this->_obj->AccountId = (
                array_key_exists($_orderNumber, $this->_cache['convertedLeads'])
                && property_exists($this->_cache['convertedLeads'][$_orderNumber], 'accountId')
            ) ? $this->_cache['convertedLeads'][$_orderNumber]->accountId : NULL;
        }

        //Process mapping
        Mage::getSingleton('tnw_salesforce/sync_mapping_order_opportunity')
            ->setSync($this)
            ->processMapping($order);

        //Get Account Name from Salesforce
        $_accountName = (
            $this->_cache['accountsLookup']
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
            && array_key_exists($_email, $this->_cache['accountsLookup'][0])
            && $this->_cache['accountsLookup'][0][$_email]->AccountName
        ) ? $this->_cache['accountsLookup'][0][$_email]->AccountName : NULL;
        if (!$_accountName) {
            $_accountName = ($order->getBillingAddress()->getCompany()) ? $order->getBillingAddress()->getCompany() : NULL;
            if (!$_accountName) {
                $_accountName = ($_accountName && !$order->getShippingAddress()->getCompany()) ? $_accountName && !$order->getShippingAddress()->getCompany() : NULL;
                if (!$_accountName) {
                    $_accountName = $order->getCustomerFirstname() . " " . $order->getCustomerLastname();
                }
            }
        }

        $this->_setOpportunityName($_orderNumber, $_accountName);
        unset($order);
    }

    protected function _pushRemainingOpportunityData()
    {
        $_resultRoles = $_resultProducts = null;
        if (!empty($this->_cache['opportunityLineItemsToUpsert'])) {
            if (!$this->_cache['bulkJobs']['opportunityProducts']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['opportunityProducts']['Id'] = $this->_createJob('OpportunityLineItem', 'upsert', 'Id');
                Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Syncronizing Opportunity Products, created job: ' . $this->_cache['bulkJobs']['opportunityProducts']['Id']);
            }

            Mage::dispatchEvent("tnw_salesforce_order_products_send_before",array("data" => $this->_cache['opportunityLineItemsToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['opportunityProducts']['Id'], 'opportunityProducts', $this->_cache['opportunityLineItemsToUpsert']);

            Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Checking if Opportunity Products were successfully synced...');
            $_resultProducts = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunityProducts']['Id']);
            $_attempt = 1;
            while (strval($_resultProducts) != 'exception' && !$_resultProducts) {
                sleep(5);
                $_resultProducts = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunityProducts']['Id']);
                Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Still checking opportunityLineItemsToUpsert (job: ' . $this->_cache['bulkJobs']['opportunityProducts']['Id'] . ')...');
                $_attempt++;

                $_resultProducts = $this->_whenToStopWaiting($_resultProducts, $_attempt, $this->_cache['bulkJobs']['opportunityProducts']['Id']);
            }
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Opportunities Products sync is complete! Moving on...');
        }

        if (!empty($this->_cache['contactRolesToUpsert'])) {
            if (!$this->_cache['bulkJobs']['customerRoles']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['customerRoles']['Id'] = $this->_createJob('OpportunityContactRole', 'upsert', 'Id');
                Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Syncronizing Opportunity Contact Roles, created job: ' . $this->_cache['bulkJobs']['customerRoles']['Id']);
            }

            Mage::dispatchEvent("tnw_salesforce_order_contact_roles_send_before",array("data" => $this->_cache['contactRolesToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['customerRoles']['Id'], 'opportunityContactRoles', $this->_cache['contactRolesToUpsert']);

            Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Checking if Opportunity Contact Roles were successfully synced...');
            $_resultRoles = $this->_checkBatchCompletion($this->_cache['bulkJobs']['customerRoles']['Id']);
            $_attempt = 1;
            while (strval($_resultRoles) != 'exception' && !$_resultRoles) {
                sleep(5);
                $_resultRoles = $this->_checkBatchCompletion($this->_cache['bulkJobs']['customerRoles']['Id']);
                Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Still checking contactRolesToUpsert (job: ' . $this->_cache['bulkJobs']['customerRoles']['Id'] . ')...');
                $_attempt++;

                $_resultRoles = $this->_whenToStopWaiting($_resultRoles, $_attempt, $this->_cache['bulkJobs']['customerRoles']['Id']);
            }

            Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Opportunities Contact Roles sync is complete! Moving on...');
        }

        if (strval($_resultProducts) != 'exception' || strval($_resultRoles) != 'exception') {
            $this->_checkRemainingData();
        }

        if (strval($_resultProducts) != 'exception') {
            Mage::dispatchEvent("tnw_salesforce_order_products_send_after",array(
                "data" => $this->_cache['opportunityLineItemsToUpsert'],
                "result" => $this->_cache['responses']['opportunityLineItems'],
                'mode' => 'bulk'
            ));
        }

        if (strval($_resultRoles) != 'exception') {
            Mage::dispatchEvent("tnw_salesforce_order_contact_roles_send_after",array(
                "data" => $this->_cache['contactRolesToUpsert'],
                "result" => isset($this->_cache['responses']['customerRoles'])? $this->_cache['responses']['customerRoles']: array()
            ));
        }

        if (!empty($this->_cache['notesToUpsert'])) {
            if (!$this->_cache['bulkJobs']['notes']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['notes']['Id'] = $this->_createJob('Note', 'upsert', 'Id');
                Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Syncronizing Notes, created job: ' . $this->_cache['bulkJobs']['notes']['Id']);
            }

            Mage::dispatchEvent("tnw_salesforce_order_notes_send_before",array("data" => $this->_cache['notesToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['notes']['Id'], 'notes', $this->_cache['notesToUpsert']);

            Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Checking if Notes were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['notes']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['notes']['Id']);
                Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Still checking notesToUpsert (job: ' . $this->_cache['bulkJobs']['notes']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['notes']['Id']);
            }
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Notes sync is complete! Moving on...');

            if (strval($_result) != 'exception') {
                $this->_checkNotesData();

                Mage::dispatchEvent("tnw_salesforce_order_notes_send_after",array(
                    "data" => $this->_cache['notesToUpsert'],
                    "result" => $this->_cache['responses']['notes']
                ));
            }
        }

        // Kick off the event to allow additional data to be pushed into salesforce
        Mage::dispatchEvent("tnw_salesforce_order_sync_after_final",array(
            "all" => $this->_cache['entitiesUpdating'],
            "failed" => $this->_cache['failedOpportunities']
        ));
    }

    protected function _checkNotesData()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        if (array_key_exists('notes', $this->_cache['batchCache'])) {
            foreach ($this->_cache['batchCache']['notes']['Id'] as $_key => $_batchId) {
                $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['notes']['Id'] . '/batch/' . $_batchId . '/result');
                try {
                    $response = $this->_client->request()->getBody();
                    $response = simplexml_load_string($response);
                    $_i = 0;
                    $sql = "";
                    $_batch = $this->_cache['batch']['notes']['Id'][$_key];
                    $_batchKeys = array_keys($_batch);
                    foreach ($response as $_item) {
                        $_noteId = $_batchKeys[$_i];
                        //Report Transaction
                        $this->_cache['responses']['notes'][$_noteId] = json_decode(json_encode($_item), TRUE);
                        $_orderId = (string)$_batch[$_noteId]->ParentId;
                        if ($_item->success == "false") {
                            $_oid = array_search($_orderId, $this->_cache  ['upserted' . $this->getManyParentEntityType()]);
                            $this->_processErrors($_item, 'notes', $_batch[$_noteId]);
                            if (!in_array($_oid, $this->_cache['failedOpportunities'])) {
                                $this->_cache['failedOpportunities'][] = $_oid;
                            }
                        } else {
                            $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_status_history') . "` SET salesforce_id = '" . (string)$_item->id . "' WHERE entity_id = '" . $_noteId . "';";
                            Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Note (id: ' . $_noteId . ') upserted for order #' . $_orderId . ')');
                        }
                        $_i++;
                    }
                    if (!empty($sql)) {
                        Mage::getModel('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                        Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
                    }
                } catch (Exception $e) {
                    // TODO:  Log error, quit
                }
            }
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
                Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Syncronizing Opportunities, created job: ' . $this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            }

            Mage::dispatchEvent("tnw_salesforce_order_send_before",array("data" => $this->_cache['opportunitiesToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['opportunity'][$this->_magentoId], 'opportunities', $this->_cache['opportunitiesToUpsert'], $this->_magentoId);

            Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Checking if Opportunities were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
                Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Still checking opportunitiesToUpsert (job: ' . $this->_cache['bulkJobs']['opportunity'][$this->_magentoId] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            }
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Opportunities sync is complete! Moving on...');

            if (strval($_result) != 'exception') {
                $this->_assignOpportunityIds();
            }
        } else {
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace('No Opportunities found queued for the synchronization!');
        }
    }

    protected function _checkRemainingData()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        if (array_key_exists('opportunityProducts', $this->_cache['batchCache'])) {
            $_sql = "";
            foreach ($this->_cache['batchCache']['opportunityProducts']['Id'] as $_key => $_batchId) {
                $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['opportunityProducts']['Id'] . '/batch/' . $_batchId . '/result');
                try {
                    $response = $this->_client->request()->getBody();
                    $response = simplexml_load_string($response);
                    $_i = 0;
                    $_batch = $this->_cache['batch']['opportunityProducts']['Id'][$_key];
                    $_batchKeys = array_keys($_batch);
                    foreach ($response as $_item) {
                        //Report Transaction
                        $this->_cache['responses']['opportunityLineItems'][] = json_decode(json_encode($_item), TRUE);
                        $_opportunityId = (string)$_batch[$_batchKeys[$_i]]->OpportunityId;
                        if ($_item->success == "false") {
                            $_oid = array_search($_opportunityId, $this->_cache  ['upserted' . $this->getManyParentEntityType()]);
                            $this->_processErrors($_item, 'opportunityProduct', $_batch[$_batchKeys[$_i]]);
                            if (!in_array($_oid, $this->_cache['failedOpportunities'])) {
                                $this->_cache['failedOpportunities'][] = $_oid;
                            }
                        } else {
                            $_cartItemId = $_batchKeys[$_i];
                            if ($_cartItemId) {
                                $_sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_item') . "` SET salesforce_id = '" . $_item->id . "' WHERE item_id = '" . str_replace('cart_','',$_cartItemId) . "';";
                            }
                        }
                        $_i++;
                    }
                } catch (Exception $e) {
                    // TODO:  Log error, quit
                }
            }
            if (!empty($_sql)) {
                Mage::helper('tnw_salesforce')->getDbConnection()->query($_sql);
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
                            $_oid = array_search($_opportunityId, $this->_cache  ['upserted' . $this->getManyParentEntityType()]);
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
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (!in_array($_orderNumber, $this->_cache['failedOpportunities'])) {
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 1 WHERE entity_id = " . $_key . ";";
            }
        }
        if ($sql != '') {
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
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

        foreach ($this->_cache['batchCache']['opportunities'][$this->_magentoId] as $_key => $_batchId) {
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Opportunity Batch: ' . $_batchId);
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
                        $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_oid] = (string)$_item->id;

                        $_contactId = ($this->_cache['orderCustomers'][$_oid]->getData('salesforce_id')) ? "'" . $this->_cache['orderCustomers'][$_oid]->getData('salesforce_id') . "'" : 'NULL';
                        $_accountId = ($this->_cache['orderCustomers'][$_oid]->getData('salesforce_account_id')) ? "'" . $this->_cache['orderCustomers'][$_oid]->getData('salesforce_account_id') . "'" : 'NULL';
                        $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET contact_salesforce_id = " . $_contactId . ", account_salesforce_id = " . $_accountId . ", sf_insync = 1, salesforce_id = '" . $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_oid] . "' WHERE entity_id = " . $_entityArray[$_oid] . ";";

                        Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Opportunity Upserted: ' . $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_oid]);

                        //unset($this->_cache['opportunitiesToUpsert'][$_oid]);
                    } else {
                        $this->_cache['failedOpportunities'][] = $_oid;
                        $this->_processErrors($_item, 'opportunity', $this->_cache['batch']['opportunities'][$this->_magentoId][$_key][$_oid]);
                    }
                    $_i++;
                }
            } catch (Exception $e) {
                // TODO:  Log error, quit
                Mage::getModel('tnw_salesforce/tool_log')->saveError('ERROR: (batch: ' . $_batchId . ') - ' . $e->getMessage());
            }
        }

        Mage::dispatchEvent("tnw_salesforce_order_send_after",array(
            "data" => $this->_cache['opportunitiesToUpsert'],
            "result" => $this->_cache['responses']['opportunities']
        ));

        if (!empty($sql)) {
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
            Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
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
                Mage::getModel('tnw_salesforce/tool_log')->saveTrace('ERROR: ' . uc_words($_batchType) . ' upsert failed!');

                return false;
            }
        }

        return true;
    }

    protected function _prepareContactRoles()
    {
        Mage::getModel('tnw_salesforce/tool_log')->saveTrace('----------Prepare Opportunity Contact Role: Start----------');
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (in_array($_orderNumber, $this->_cache['failedOpportunities'])) {
                Mage::getModel('tnw_salesforce/tool_log')->saveTrace('ORDER (' . $_orderNumber . '): Skipping, issues with upserting an opportunity!');
                continue;
            }
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace('******** ORDER (' . $_orderNumber . ') ********');
            $this->_obj = new stdClass();

            $_order = Mage::getModel('sales/order')->load($_key);

            $contactId = $this->_cache['orderCustomers'][$_orderNumber]->getData('salesforce_id');
            if ($_order->getData('contact_salesforce_id')) {
                $this->_obj->ContactId = $contactId;
            }

            // Check if already exists
            $_skip = false;
            if ($this->_cache['opportunityLookup'] && array_key_exists($_orderNumber, $this->_cache['opportunityLookup']) && $this->_cache['opportunityLookup'][$_orderNumber]->OpportunityContactRoles) {
                foreach ($this->_cache['opportunityLookup'][$_orderNumber]->OpportunityContactRoles->records as $_role) {
                    if (property_exists($this->_obj, 'ContactId') && property_exists($_role, 'ContactId') && $_role->ContactId == $this->_obj->ContactId) {
                        if ($_role->Role == Mage::helper('tnw_salesforce')->getDefaultCustomerRole()) {
                            // No update required
                            Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Contact Role information is the same, no update required!');
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
                $this->_obj->OpportunityId = $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_orderNumber];

                $this->_obj->Role = Mage::helper('tnw_salesforce')->getDefaultCustomerRole();

                foreach ($this->_obj as $key => $_item) {
                    Mage::getModel('tnw_salesforce/tool_log')->saveTrace("OpportunityContactRole Object: " . $key . " = '" . $_item . "'");
                }

                if (property_exists($this->_obj, 'ContactId') && $this->_obj->ContactId) {
                    $this->_cache['contactRolesToUpsert'][] = $this->_obj;
                } else {
                    Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Was not able to convert customer Lead, skipping Opportunity Contact Role assignment. Please synchronize customer (contactId: ' . $contactId . ')');
                }
            }
        }
        Mage::getModel('tnw_salesforce/tool_log')->saveTrace('----------Prepare Opportunity Contact Role: End----------');
    }

    protected function _onComplete()
    {
        // Close Jobs
        if ($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]) {
            $this->_closeJob($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
        }
        if ($this->_cache['bulkJobs']['opportunityProducts']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['opportunityProducts']['Id']);
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['opportunityProducts']['Id']);
        }
        if ($this->_cache['bulkJobs']['customerRoles']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['customerRoles']['Id']);
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['customerRoles']['Id']);
        }
        if ($this->_cache['bulkJobs']['notes']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['notes']['Id']);
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['notes']['Id']);
        }

        Mage::getModel('tnw_salesforce/tool_log')->saveTrace('Clearing bulk sync cache...');

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