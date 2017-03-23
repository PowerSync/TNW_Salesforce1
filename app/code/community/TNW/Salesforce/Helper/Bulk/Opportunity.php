<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
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

    protected function _pushRemainingEntityData()
    {
        $_resultRoles = $_resultProducts = null;
        if (!empty($this->_cache['opportunityLineItemsToUpsert'])) {
            if (!$this->_cache['bulkJobs']['opportunityProducts']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['opportunityProducts']['Id'] = $this->_createJob('OpportunityLineItem', 'upsert', 'Id');
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Syncronizing Opportunity Products, created job: ' . $this->_cache['bulkJobs']['opportunityProducts']['Id']);
            }

            Mage::dispatchEvent("tnw_salesforce_order_products_send_before",array("data" => $this->_cache['opportunityLineItemsToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['opportunityProducts']['Id'], 'opportunityProducts', $this->_cache['opportunityLineItemsToUpsert']);

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Checking if Opportunity Products were successfully synced...');
            $_resultProducts = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunityProducts']['Id']);
            $_attempt = 1;
            while (strval($_resultProducts) != 'exception' && !$_resultProducts) {
                sleep(5);
                $_resultProducts = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunityProducts']['Id']);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Still checking opportunityLineItemsToUpsert (job: ' . $this->_cache['bulkJobs']['opportunityProducts']['Id'] . ')...');
                $_attempt++;

                $_resultProducts = $this->_whenToStopWaiting($_resultProducts, $_attempt, $this->_cache['bulkJobs']['opportunityProducts']['Id']);
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Opportunities Products sync is complete! Moving on...');
        }

        if (!empty($this->_cache['contactRolesToUpsert'])) {
            if (!$this->_cache['bulkJobs']['customerRoles']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['customerRoles']['Id'] = $this->_createJob('OpportunityContactRole', 'upsert', 'Id');
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Syncronizing Opportunity Contact Roles, created job: ' . $this->_cache['bulkJobs']['customerRoles']['Id']);
            }

            Mage::dispatchEvent("tnw_salesforce_opportunity_contact_roles_send_before",array("data" => $this->_cache['contactRolesToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['customerRoles']['Id'], 'opportunityContactRoles', $this->_cache['contactRolesToUpsert']);

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Checking if Opportunity Contact Roles were successfully synced...');
            $_resultRoles = $this->_checkBatchCompletion($this->_cache['bulkJobs']['customerRoles']['Id']);
            $_attempt = 1;
            while (strval($_resultRoles) != 'exception' && !$_resultRoles) {
                sleep(5);
                $_resultRoles = $this->_checkBatchCompletion($this->_cache['bulkJobs']['customerRoles']['Id']);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Still checking contactRolesToUpsert (job: ' . $this->_cache['bulkJobs']['customerRoles']['Id'] . ')...');
                $_attempt++;

                $_resultRoles = $this->_whenToStopWaiting($_resultRoles, $_attempt, $this->_cache['bulkJobs']['customerRoles']['Id']);
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Opportunities Contact Roles sync is complete! Moving on...');
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
            Mage::dispatchEvent("tnw_salesforce_opportunity_contact_roles_send_after",array(
                "data" => $this->_cache['contactRolesToUpsert'],
                "result" => isset($this->_cache['responses']['opportunityCustomerRoles'])? $this->_cache['responses']['opportunityCustomerRoles']: array()
            ));
        }

        if (!empty($this->_cache['notesToUpsert'])) {
            if (!$this->_cache['bulkJobs']['notes']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['notes']['Id'] = $this->_createJob('Note', 'upsert', 'Id');
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Syncronizing Notes, created job: ' . $this->_cache['bulkJobs']['notes']['Id']);
            }

            Mage::dispatchEvent("tnw_salesforce_order_notes_send_before",array("data" => $this->_cache['notesToUpsert']));

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
        if (!array_key_exists('notes', $this->_cache['batchCache'])) {
            return;
        }

        foreach ($this->_cache['batchCache']['notes']['Id'] as $_key => $_batchId) {
            $_batch = &$this->_cache['batch']['notes']['Id'][$_key];

            try {
                $response = $this->getBatch($this->_cache['bulkJobs']['notes']['Id'], $_batchId);
            } catch (Exception $e) {
                $response = array_fill(0, count($_batch), $this->_buildErrorResponse($e->getMessage()));

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Prepare batch #'. $_batchId .' Error: ' . $e->getMessage());
            }

            $_i = 0;
            $sql = "";
            $_batchKeys = array_keys($_batch);
            foreach ($response as $_item) {
                $_noteId  = $_batchKeys[$_i++];
                $_orderId = (string)$_batch[$_noteId]->ParentId;
                $_oid     = array_search($_orderId, $this->_cache['upserted'.$this->getManyParentEntityType()]);

                //Report Transaction
                $this->_cache['responses']['notes'][$_oid]['subObj'][] = json_decode(json_encode($_item), TRUE);
                if ($_item->success == "true") {
                    $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_status_history') . "` SET opportunity_id = '" . (string)$_item->id . "' WHERE entity_id = '" . $_noteId . "';";

                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveTrace('Note (id: ' . $_noteId . ') upserted for order #' . $_orderId . ')');
                    continue;
                }

                $this->_processErrors($_item, 'notes', $_batch[$_noteId]);
                if (!in_array($_oid, $this->_cache['failedOpportunities'])) {
                    $this->_cache['failedOpportunities'][] = $_oid;
                }
            }

            if (!empty($sql)) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
            }
        }
    }

    protected function _pushEntity()
    {
        if (!empty($this->_cache['opportunitiesToUpsert'])) {
            if (!$this->_cache['bulkJobs']['opportunity']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['opportunity']['Id'] = $this->_createJob('Opportunity', 'upsert', 'Id');
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Syncronizing Opportunities, created job: ' . $this->_cache['bulkJobs']['opportunity']['Id']);
            }

            Mage::dispatchEvent("tnw_salesforce_order_send_before",array("data" => $this->_cache['opportunitiesToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['opportunity']['Id'], 'opportunities', $this->_cache['opportunitiesToUpsert']);

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
        if (array_key_exists('opportunityProducts', $this->_cache['batchCache'])) {
            foreach ($this->_cache['batchCache']['opportunityProducts']['Id'] as $_key => $_batchId) {
                $_batch = &$this->_cache['batch']['opportunityProducts']['Id'][$_key];

                try {
                    $response = $this->getBatch($this->_cache['bulkJobs']['opportunityProducts']['Id'], $_batchId);
                } catch (Exception $e) {
                    $response = array_fill(0, count($_batch), $this->_buildErrorResponse($e->getMessage()));

                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError('Prepare batch #'. $_batchId .' Error: ' . $e->getMessage());
                }

                $_i = 0;
                $_batchKeys = array_keys($_batch);
                foreach ($response as $_item) {
                    $_cartItemId    = $_batchKeys[$_i++];
                    $_opportunityId = (string)$_batch[$_cartItemId]->OpportunityId;
                    $_oid           = array_search($_opportunityId, $this->_cache['upserted'.$this->getManyParentEntityType()]);

                    //Report Transaction
                    $this->_cache['responses']['opportunityLineItems'][$_oid]['subObj'][] = json_decode(json_encode($_item), TRUE);

                    if ($_item->success == "true") {
                        /** @var Mage_Sales_Model_Order_Item $_entityItem */
                        $_entityItem = $this->_loadEntityByCache(array_search($_oid, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_oid)
                            ->getItemById(str_replace('cart_','',$_cartItemId));

                        if ($_entityItem) {
                            $_entityItem->setData('opportunity_id', $_item->id);
                            $_entityItem->getResource()->save($_entityItem);
                        }

                        continue;
                    }

                    $this->_processErrors($_item, 'opportunityProduct', $_batch[$_cartItemId]);
                    if (!in_array($_oid, $this->_cache['failedOpportunities'])) {
                        $this->_cache['failedOpportunities'][] = $_oid;
                    }
                }
            }
        }

        if (array_key_exists('opportunityContactRoles', $this->_cache['batchCache'])) {
            foreach ($this->_cache['batchCache']['opportunityContactRoles']['Id'] as $_key => $_batchId) {
                $_batch = &$this->_cache['batch']['opportunityContactRoles']['Id'][$_key];

                try {
                    $response = $this->getBatch($this->_cache['bulkJobs']['customerRoles']['Id'], $_batchId);
                } catch (Exception $e) {
                    $response = array_fill(0, count($_batch), $this->_buildErrorResponse($e->getMessage()));

                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError('Prepare batch #'. $_batchId .' Error: ' . $e->getMessage());
                }

                $_i = 0;
                $_batchKeys = array_keys($_batch);
                foreach ($response as $_item) {
                    $_batchKey      = $_batchKeys[$_i++];
                    $_opportunityId = (string)$_batch[$_batchKey]->OpportunityId;
                    $_oid           = array_search($_opportunityId, $this->_cache['upserted'.$this->getManyParentEntityType()]);

                    //Report Transaction
                    $this->_cache['responses']['opportunityCustomerRoles'][$_oid]['subObj'][] = json_decode(json_encode($_item), TRUE);

                    if ($_item->success == "true") {
                        continue;
                    }

                    $this->_processErrors($_item, 'opportunityProduct', $_batch[$_batchKey]);
                    if (!in_array($_oid, $this->_cache['failedOpportunities'])) {
                        $this->_cache['failedOpportunities'][] = $_oid;
                    }
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
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
            Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
        }
    }

    protected function _assignOpportunityIds()
    {
        foreach ($this->_cache['batchCache']['opportunities']['Id'] as $_key => $_batchId) {
            $_batch = &$this->_cache['batch']['opportunities']['Id'][$_key];
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Opportunity Batch: ' . $_batchId);

            try {
                $response = $this->getBatch($this->_cache['bulkJobs']['opportunity']['Id'], $_batchId);
            } catch (Exception $e) {
                $response = array_fill(0, count($_batch), $this->_buildErrorResponse($e->getMessage()));

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR: (batch: ' . $_batchId . ') - ' . $e->getMessage());
            }

            $_i = 0;
            $_batchKeys = array_keys($_batch);
            foreach ($response as $_item) {
                $_oid = $_batchKeys[$_i++];

                //Report Transaction
                $this->_cache['responses']['opportunities'][$_oid] = json_decode(json_encode($_item), TRUE);

                if ($_item->success == "true") {
                    $_order    = $this->_loadEntityByCache(array_search($_oid, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_oid);
                    $_customer = $this->_getObjectByEntityType($_order, 'Customer');
                    $_order->addData(array(
                        'contact_salesforce_id' => $_customer->getData('salesforce_id'),
                        'account_salesforce_id' => $_customer->getData('salesforce_account_id'),
                        'opportunity_id'        => (string)$_item->id,
                        'sf_insync'             => 1,
                        'owner_salesforce_id'   => $_batch[$_oid]->OwnerId
                    ));
                    $_order->getResource()->save($_order);

                    $this->_cache['upserted'.$this->getManyParentEntityType()][$_oid] = (string)$_item->id;
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveTrace('Opportunity Upserted: ' . (string)$_item->id);
                    continue;
                }

                $this->_cache['failedOpportunities'][] = $_oid;
                $this->_processErrors($_item, 'opportunity', $_batch[$_oid]);
            }
        }

        Mage::dispatchEvent("tnw_salesforce_order_send_after",array(
            "data" => $this->_cache['opportunitiesToUpsert'],
            "result" => $this->_cache['responses']['opportunities']
        ));
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