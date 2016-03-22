<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
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

            if (!$this->_cache['bulkJobs']['opportunity']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['opportunity']['Id'] = $this->_createJob('Opportunity', 'upsert', 'Id');
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Syncronizing Opportunities, created job: ' . $this->_cache['bulkJobs']['opportunity']['Id']);
            }
            $this->_pushChunked($this->_cache['bulkJobs']['opportunity']['Id'], 'opportunities', $this->_cache['opportunitiesToUpsert'], 'Id');

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Checking if Opportunities were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunity']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunity']['Id']);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Still checking opportunitiesToUpsert (job: ' . $this->_cache['bulkJobs']['opportunity']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['opportunity']['Id']);
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

        foreach ($this->_cache['batchCache']['opportunities']['Id'] as $_key => $_batchId) {
            $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['opportunity']['Id'] . '/batch/' . $_batchId . '/result');
            try {
                $response = $this->_client->request()->getBody();
                $response = simplexml_load_string($response);
                $_i = 0;
                $_batch = array_keys($this->_cache['batch']['opportunities']['Id'][$_key]);
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
                            $this->_cache['batch']['opportunities']['Id'][$_key][$_oid]);
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

    protected function _onComplete()
    {
        // Close Jobs
        if ($this->_cache['bulkJobs']['opportunity']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['opportunity']['Id']);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['opportunity']['Id']);
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
            'opportunity' => array('Id' => NULL),
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
            'opportunity' => array('Id' => NULL),
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