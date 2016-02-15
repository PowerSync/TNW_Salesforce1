<?php

class TNW_Salesforce_Helper_Bulk_Shipment extends TNW_Salesforce_Helper_Salesforce_Shipment
{
    protected function _pushEntity()
    {
        if (empty($this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))])) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace(sprintf('No %s were queued for synchronization!', ucwords($this->getManyParentEntityType())));

            return;
        }

        if (!$this->_cache['bulkJobs'][$this->_magentoEntityName]['Id']) {
            // Create Job
            $this->_cache['bulkJobs'][$this->_magentoEntityName]['Id']
                = $this->_createJob(TNW_Salesforce_Model_Config_Objects::ORDER_SHIPMENT_OBJECT, 'upsert', 'Id');

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace(sprintf('Syncronizing %s, created job: %s',
                    ucwords($this->getManyParentEntityType()),
                    $this->_cache['bulkJobs'][$this->_magentoEntityName]['Id']));
        }

        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_send_before', $this->_magentoEntityName),
            array("data" => $this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))]));

        $this->_pushChunked(
            $this->_cache['bulkJobs'][$this->_magentoEntityName]['Id'],
            $this->getManyParentEntityType(),
            $this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))]);

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf('Checking if %s were successfully synced...', ucwords($this->_magentoEntityName)));

        $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs'][$this->_magentoEntityName]['Id']);
        $_attempt = 1;
        while (strval($_result) != 'exception' && !$_result) {
            set_time_limit(1800);
            sleep(5);
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs'][$this->_magentoEntityName]['Id']);

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace(sprintf('Still checking %sToUpsert (job: %s)...',
                    strtolower($this->getManyParentEntityType()), $this->_cache['bulkJobs'][$this->_magentoEntityName]['Id']));

            $_result = $this->_whenToStopWaiting($_result, $_attempt++,
                $this->_cache['bulkJobs'][$this->_magentoEntityName]['Id']);
        }

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf('%s sync is complete! Moving on...', $this->getManyParentEntityType()));

        if (strval($_result) != 'exception') {
            $this->_assignIds();
        }
    }

    protected function _assignIds()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());
        $_entityArray = array_flip($this->_cache['entitiesUpdating']);

        foreach ($this->_cache['batchCache'][$this->getManyParentEntityType()]['Id'] as $_key => $_batchId) {
            $this->_client->setUri(sprintf('%s/services/async/%s/job/%s/batch/%s/result',
                $this->getSalesforceServerDomain(), $this->_salesforceApiVersion,
                $this->_cache['bulkJobs'][$this->_magentoEntityName]['Id'], $_batchId));

            try {
                $response = $this->_client->request()->getBody();
                $response = simplexml_load_string($response);
                $_i = 0;
                $_batch = array_keys($this->_cache['batch'][$this->getManyParentEntityType()]['Id'][$_key]);
                foreach ($response as $_item) {
                    $_oid = $_batch[$_i++];

                    //Report Transaction
                    $this->_cache['responses'][strtolower($this->getManyParentEntityType())][$_oid] = json_decode(json_encode($_item), TRUE);
                    if ($_item->success == "true") {
                        $this->_cache[sprintf('upserted%s', $this->getManyParentEntityType())][$_oid] = (string)$_item->id;

                        $sql = sprintf('UPDATE `%s` SET salesforce_id = "%s" WHERE entity_id = %d;',
                            $this->_modelEntity()->getResource()->getMainTable(), (string)$_item->id, $_entityArray[$_oid]);
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                        Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveTrace(ucwords($this->_magentoEntityName) . ' Upserted: ' . (string)$_item->id);

                        if ($_record = $this->_loadEntityByCache(null, $_oid)) {
                            $_record->setData('salesforce_id', (string)$_item->id);
                            $_record->setData('sf_insync', 1);
                        }
                    }
                    else {
                        $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())][] = $_oid;
                        $this->_processErrors($_item, $this->_magentoEntityName,
                            $this->_cache['batch'][$this->getManyParentEntityType()]['Id'][$_key][$_oid]);
                    }
                }
            } catch (Exception $e) {
                // TODO:  Log error, quit
                $response = $e->getMessage();
            }
        }

        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_send_after', $this->_magentoEntityName), array(
            "data" => $this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))],
            "result" => $this->_cache['responses'][strtolower($this->getManyParentEntityType())]
        ));
    }

    /**
     * Push cart items, notes
     */
    protected function _pushRemainingEntityData()
    {
        set_time_limit(1000);
        $itemKey            = lcfirst($this->getItemsField());
        $itemToUpsertKey    = sprintf('%sToUpsert', $itemKey);
        if (!empty($this->_cache[$itemToUpsertKey])) {
            if (!$this->_cache['bulkJobs'][$itemKey]['Id']) {
                // Create Job
                $this->_cache['bulkJobs'][$itemKey]['Id']
                    = $this->_createJob(TNW_Salesforce_Model_Config_Objects::ORDER_SHIPMENT_ITEM_OBJECT, 'upsert', 'Id');

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('Syncronizing %s Items, created job: %s',
                        ucwords($this->_magentoEntityName), $this->_cache['bulkJobs'][$itemKey]['Id']));
            }

            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_products_send_before', $this->_magentoEntityName), array("data" => $this->_cache[$itemToUpsertKey]));

            $this->_pushChunked($this->_cache['bulkJobs'][$itemKey]['Id'], $itemKey, $this->_cache[$itemToUpsertKey]);

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace(sprintf('Checking if %s Items were successfully synced...', ucwords($this->_magentoEntityName)));

            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs'][$itemKey]['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                set_time_limit(1800);
                sleep(5);

                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs'][$itemKey]['Id']);
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('Still checking %s (job: %s)...', $itemToUpsertKey, $this->_cache['bulkJobs'][$itemKey]['Id']));

                $_result = $this->_whenToStopWaiting($_result, $_attempt++, $this->_cache['bulkJobs'][$itemKey]['Id']);
            }

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace(sprintf('%s Items sync is complete! Moving on...', ucwords($this->_magentoEntityName)));

            if (strval($_result) != 'exception') {
                $this->_checkItemData();
            }
        }

        set_time_limit(1000);
        if (!empty($this->_cache['notesToUpsert'])) {
            if (!$this->_cache['bulkJobs']['notes']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['notes']['Id'] = $this->_createJob('Note', 'upsert', 'Id');
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Syncronizing Notes, created job: ' . $this->_cache['bulkJobs']['notes']['Id']);
            }

            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_notes_send_before', $this->_magentoEntityName), array("data" => $this->_cache['notesToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['notes']['Id'], 'notes', $this->_cache['notesToUpsert']);

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Checking if Notes were successfully synced...');

            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['notes']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                set_time_limit(1800);
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['notes']['Id']);
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Still checking notesToUpsert (job: ' . $this->_cache['bulkJobs']['notes']['Id'] . ')...');

                $_result = $this->_whenToStopWaiting($_result, $_attempt++, $this->_cache['bulkJobs']['notes']['Id']);
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Notes sync is complete! Moving on...');

            if (strval($_result) != 'exception') {
                $this->_checkNotesData();
            }
        }

        set_time_limit(1000);
        if (!empty($this->_cache['orderShipmentTrackToUpsert'])) {
            if (!$this->_cache['bulkJobs']['orderShipmentTrack']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['orderShipmentTrack']['Id']
                    = $this->_createJob(TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'OrderShipmentTracking__c', 'upsert', 'Id');

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Syncronizing Shipment Track, created job: ' . $this->_cache['bulkJobs']['orderShipmentTrack']['Id']);
            }

            $this->_pushChunked($this->_cache['bulkJobs']['orderShipmentTrack']['Id'], 'orderShipmentTrack', $this->_cache['orderShipmentTrackToUpsert']);

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Checking if Shipment Track were successfully synced...');

            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['orderShipmentTrack']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                set_time_limit(1800);
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['orderShipmentTrack']['Id']);
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Still checking orderShipmentTrackToUpsert (job: ' . $this->_cache['bulkJobs']['orderShipmentTrack']['Id'] . ')...');

                $_result = $this->_whenToStopWaiting($_result, $_attempt++, $this->_cache['bulkJobs']['orderShipmentTrack']['Id']);
            }

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Shipment Track sync is complete! Moving on...');

            if (strval($_result) != 'exception') {
                $this->_checkTracksData();
            }
        }

        // Mark shipment as failed or successful
        $this->_updateRecords();
    }

    protected function _checkItemData()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        $itemKey = lcfirst($this->getItemsField());
        if (!array_key_exists($itemKey, $this->_cache['batchCache'])) {
            return;
        }

        foreach ($this->_cache['batchCache'][$itemKey]['Id'] as $_key => $_batchId) {
            $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs'][$itemKey]['Id'] . '/batch/' . $_batchId . '/result');
            try {
                $response = $this->_client->request()->getBody();
                $response = simplexml_load_string($response);
                $_i = 0;
                $_batch = $this->_cache['batch'][$itemKey]['Id'][$_key];
                $_batchKeys = array_keys($_batch);
                foreach ($response as $_item) {
                    $_recordItemId = $_batchKeys[$_i++];
                    $_orderId = (string)$_batch[$_recordItemId]
                        ->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Shipment__c'};
                    $_oid = array_search($_orderId, $this->_cache['upserted' . $this->getManyParentEntityType()]);

                    //Report Transaction
                    $this->_cache['responses'][$itemKey][$_oid]['subObj'][] = json_decode(json_encode($_item), TRUE);
                    if ($_item->success == "false") {
                        $this->_processErrors($_item, $itemKey, $_batch[$_recordItemId]);
                        if (!in_array($_oid, $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())])) {
                            $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())][] = $_oid;
                        }
                    }
                    else {
                        if ($_recordItemId && strrpos($_recordItemId, 'cart_', -strlen($_recordItemId)) !== FALSE) {
                            $_sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_shipment_item') . "` SET salesforce_id = '" . $_item->id . "' WHERE entity_id = '" . str_replace('cart_','',$_recordItemId) . "';";
                            Mage::helper('tnw_salesforce')->getDbConnection()->query($_sql);
                        }
                    }
                }
            } catch (Exception $e) {
                // TODO:  Log error, quit
                $response = $e->getMessage();
            }
        }

        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_products_send_after', $this->_magentoEntityName), array(
            "data" => $this->_cache[sprintf('%sToUpsert', $itemKey)],
            "result" => $this->_cache['responses'][$itemKey]
        ));
    }

    protected function _checkNotesData()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        if (!array_key_exists('notes', $this->_cache['batchCache'])) {
            return;
        }

        foreach ($this->_cache['batchCache']['notes']['Id'] as $_key => $_batchId) {
            $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['notes']['Id'] . '/batch/' . $_batchId . '/result');
            try {
                $response = $this->_client->request()->getBody();
                $response = simplexml_load_string($response);
                $_i = 0;

                $_batch = $this->_cache['batch']['notes']['Id'][$_key];
                $_batchKeys = array_keys($_batch);

                foreach ($response as $_item) {
                    $_noteId = $_batchKeys[$_i++];
                    $_orderId = (string)$_batch[$_noteId]->ParentId;
                    $_oid = array_search($_orderId, $this->_cache['upserted' . $this->getManyParentEntityType()]);

                    //Report Transaction
                    $this->_cache['responses']['notes'][$_oid]['subObj'][] = json_decode(json_encode($_item), TRUE);
                    if ($_item->success == "false") {
                        $this->_processErrors($_item, 'notes', $_batch[$_noteId]);
                        if (!in_array($_oid, $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())])) {
                            $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())][] = $_oid;
                        }
                    }
                    else {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveTrace('Note (id: ' . $_noteId . ') upserted for ' . $this->_magentoEntityName . ' #' . $_orderId . ')');

                        $sql = "UPDATE `" . $this->_notesTableName() . "` SET salesforce_id = '" . (string)$_item->id . "' WHERE entity_id = '" . $_noteId . "';";
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                        Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
                    }
                }
            } catch (Exception $e) {
                // TODO:  Log error, quit
                $response = $e->getMessage();
            }
        }

        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_notes_send_after', $this->_magentoEntityName), array(
            "data" => $this->_cache['notesToUpsert'],
            "result" => $this->_cache['responses']['notes']
        ));
    }

    protected function _checkTracksData()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        if (!array_key_exists('orderShipmentTrack', $this->_cache['batchCache'])) {
            return;
        }

        foreach ($this->_cache['batchCache']['orderShipmentTrack']['Id'] as $_key => $_batchId) {
            $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['orderShipmentTrack']['Id'] . '/batch/' . $_batchId . '/result');
            try {
                $response = $this->_client->request()->getBody();
                $response = simplexml_load_string($response);
                $_i = 0;

                $_batch = $this->_cache['batch']['orderShipmentTrack']['Id'][$_key];
                $_batchKeys = array_keys($_batch);

                foreach ($response as $_item) {
                    $_noteId = $_batchKeys[$_i++];
                    $_orderId = (string)$_batch[$_noteId]
                        ->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Shipment__c'};
                    $_oid = array_search($_orderId, $this->_cache['upserted' . $this->getManyParentEntityType()]);

                    //Report Transaction
                    $this->_cache['responses']['orderShipmentTrack'][$_oid]['subObj'][] = json_decode(json_encode($_item), TRUE);
                    if ($_item->success == "false") {
                        $this->_processErrors($_item, 'OrderShipmentTrack', $_batch[$_noteId]);
                        if (!in_array($_oid, $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())])) {
                            $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())][] = $_oid;
                        }
                    }
                    else {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveTrace('Shipment Track (id: ' . $_noteId . ') upserted for ' . $this->_magentoEntityName . ' #' . $_orderId . ')');
                    }
                }
            } catch (Exception $e) {
                // TODO:  Log error, quit
                $response = $e->getMessage();
            }
        }
    }

    protected function _updateRecords()
    {
        $_recordNumbers = array_keys(array_diff(
            $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING],
            $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())]
        ));

        if (!empty($_recordNumbers)) {
            $sql = sprintf('UPDATE `%s` SET sf_insync = 1 WHERE entity_id IN("%s");',
                $this->_modelEntity()->getResource()->getMainTable(), implode('", "', $_recordNumbers));
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
            Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
        }
    }

    /**
     * @return bool
     */
    public function reset()
    {
        parent::reset();

        $this->_cache['bulkJobs'] = array(
            $this->_magentoEntityName       => array('Id' => NULL),
            lcfirst($this->getItemsField()) => array('Id' => NULL),
            'notes'                         => array('Id' => NULL),
            'orderShipmentTrack'            => array('Id' => NULL),
        );

        $this->_cache['batch'] = array();
        $this->_cache['batchCache'] = array();

        return $this->check();
    }

    protected function _onComplete()
    {
        // Close Jobs
        if ($this->_cache['bulkJobs'][$this->_magentoEntityName]['Id']) {
            $this->_closeJob($this->_cache['bulkJobs'][$this->_magentoEntityName]['Id']);

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("Closing job: " . $this->_cache['bulkJobs'][$this->_magentoEntityName]['Id']);
        }

        if ($this->_cache['bulkJobs'][lcfirst($this->getItemsField())]['Id']) {
            $this->_closeJob($this->_cache['bulkJobs'][lcfirst($this->getItemsField())]['Id']);

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("Closing job: " . $this->_cache['bulkJobs'][lcfirst($this->getItemsField())]['Id']);
        }

        if ($this->_cache['bulkJobs']['notes']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['notes']['Id']);

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("Closing job: " . $this->_cache['bulkJobs']['notes']['Id']);
        }

        if ($this->_cache['bulkJobs']['orderShipmentTrack']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['orderShipmentTrack']['Id']);

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("Closing job: " . $this->_cache['bulkJobs']['orderShipmentTrack']['Id']);
        }

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace('Clearing bulk sync cache...');

        $this->_cache['bulkJobs'] = array(
            $this->_magentoEntityName       => array('Id' => NULL),
            lcfirst($this->getItemsField()) => array('Id' => NULL),
            'notes'                         => array('Id' => NULL),
            'orderShipmentTrack'            => array('Id' => NULL),
        );

        parent::_onComplete();
    }
}