<?php

/**
 * Class TNW_Salesforce_Helper_Bulk_Order
 */
class TNW_Salesforce_Helper_Bulk_Order extends TNW_Salesforce_Helper_Salesforce_Order
{
    /**
     * @var array
     */
    protected $_allResults = array(
        'orders' => array(),
        'order_products' => array(),
    );

    /**
     * Push cart items, notes
     */
    protected function _pushRemainingEntityData()
    {
        if (!empty($this->_cache['orderItemsToUpsert'])) {
            if (!$this->_cache['bulkJobs']['orderProducts']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['orderProducts']['Id'] = $this->_createJob('OrderItem', 'upsert', 'Id');
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Syncronizing Order Products, created job: ' . $this->_cache['bulkJobs']['orderProducts']['Id']);
            }

            Mage::dispatchEvent("tnw_salesforce_order_products_send_before",array("data" => $this->_cache['orderItemsToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['orderProducts']['Id'], 'orderProducts', $this->_cache['orderItemsToUpsert']);

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Checking if Order Products were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['orderProducts']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['orderProducts']['Id']);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Still checking orderItemsToUpsert (job: ' . $this->_cache['bulkJobs']['orderProducts']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['orderProducts']['Id']);
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Order Products sync is complete! Moving on...');

            if (strval($_result) != 'exception') {
                $this->_checkOrderProductData();

                Mage::dispatchEvent("tnw_salesforce_order_products_send_after",array(
                    "data" => $this->_cache['orderItemsToUpsert'],
                    "result" => $this->_cache['responses']['orderProducts'],
                    'mode' => 'bulk'
                ));
            }
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
            "failed" => $this->_cache['failedOrders']
        ));

        // Mark orders as failed or successful
        $this->_updateOrders();

        // Activate orders
        if (!empty($this->_cache['orderToActivate'])) {
            foreach($this->_cache['orderToActivate'] as $_orderNum => $_object) {
                if (array_key_exists($_orderNum, $this->_cache  ['upserted' . $this->getManyParentEntityType()])) {
                    $_object->Id = $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_orderNum];

                    // Check if at least 1 product was added to the order before we try to activate
                    if (
                        !array_key_exists($_object->Id, $this->_cache['orderItemsProductsToSync'])
                        || empty($this->_cache['orderItemsProductsToSync'][$_object->Id])
                    ) {
                        unset($this->_cache['orderToActivate'][$_orderNum]);
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING ACTIVATION: Order (' . $_orderNum . ') Products did not make it into Salesforce.');
                        if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                            Mage::getSingleton('adminhtml/session')->addNotice("SKIPPING ORDER ACTIVATION: Order (" . $_orderNum . ") could not be activated w/o any products!");
                        }
                    }
                } else {
                    unset($this->_cache['orderToActivate'][$_orderNum]);
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING ACTIVATION: Order (' . $_orderNum . ') did not make it into Salesforce.');
                }
            }
            if (!empty($this->_cache['orderToActivate'])) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Activating Orders: Start----------');
                // Push Cart
                $_itemsToPushChunk = array_chunk($this->_cache['orderToActivate'], TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);
                foreach($_itemsToPushChunk as $_itemsToPush){
                    $this->_activateOrders($_itemsToPush);
                }

                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Activating Orders: End----------');
            }
        }
    }

    protected function _pushEntity()
    {
        if (!empty($this->_cache['ordersToUpsert'])) {

            if (!$this->_cache['bulkJobs']['order']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['order']['Id'] = $this->_createJob('Order', 'upsert', 'Id');
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Syncronizing Orders, created job: ' . $this->_cache['bulkJobs']['order']['Id']);
            }

            Mage::dispatchEvent("tnw_salesforce_order_send_before",array("data" => $this->_cache['ordersToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['order']['Id'], 'orders', $this->_cache['ordersToUpsert']);

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Checking if Orders were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['order']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['order']['Id']);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Still checking ordersToUpsert (job: ' . $this->_cache['bulkJobs']['order']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['order']['Id']);
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Orders sync is complete! Moving on...');

            if (strval($_result) != 'exception') {
                $this->_assignOrderIds();
            }
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('No Orders were queued for synchronization!');
        }
    }

    protected function _checkOrderProductData()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        if (array_key_exists('orderProducts', $this->_cache['batchCache'])) {
            $_sql = "";
            foreach ($this->_cache['batchCache']['orderProducts']['Id'] as $_key => $_batchId) {
                $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['orderProducts']['Id'] . '/batch/' . $_batchId . '/result');
                try {
                    $response = $this->_client->request()->getBody();
                    $response = simplexml_load_string($response);
                    $_i = 0;
                    $_batch = $this->_cache['batch']['orderProducts']['Id'][$_key];
                    $_batchKeys = array_keys($_batch);
                    foreach ($response as $_item) {
                        $_orderId = (string)$_batch[$_batchKeys[$_i]]->OrderId;
                        $_oid = array_search($_orderId, $this->_cache['upserted'.$this->getManyParentEntityType()]);

                        //Report Transaction
                        $this->_cache['responses']['orderProducts'][$_oid]['subObj'][] = json_decode(json_encode($_item), TRUE);
                        if ($_item->success == "false") {
                            $this->_processErrors($_item, 'orderProduct', $_batch[$_batchKeys[$_i]]);
                            if (!in_array($_oid, $this->_cache['failedOrders'])) {
                                $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())][] = $_oid;
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
                        $_noteId  = $_batchKeys[$_i];
                        $_orderId = (string)$_batch[$_noteId]->ParentId;
                        $_oid     = array_search($_orderId, $this->_cache  ['upserted' . $this->getManyParentEntityType()]);

                        //Report Transaction
                        $this->_cache['responses']['notes'][$_oid]['subObj'][] = json_decode(json_encode($_item), TRUE);
                        if ($_item->success == "false") {
                            $this->_processErrors($_item, 'notes', $_batch[$_noteId]);
                            if (!in_array($_oid, $this->_cache['failedOrders'])) {
                                $this->_cache['failedOrders'][] = $_oid;
                            }
                        } else {
                            $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_status_history') . "` SET salesforce_id = '" . (string)$_item->id . "' WHERE entity_id = '" . $_noteId . "';";
                            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Note (id: ' . $_noteId . ') upserted for order #' . $_orderId . ')');
                        }
                        $_i++;
                    }
                    if (!empty($sql)) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                        $this->_write->query($sql);
                    }
                } catch (Exception $e) {
                    // TODO:  Log error, quit
                }
            }
        }
    }

    protected function _updateOrders() {
        $sql = '';
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (!in_array($_orderNumber, $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())])) {
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 1 WHERE entity_id = " . $_key . ";";
            }
        }
        if ($sql != '') {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
            $this->_write->query($sql);
        }
    }

    protected function _assignOrderIds()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());
        $_entityArray = array_flip($this->_cache['entitiesUpdating']);
        $sql = '';

        foreach ($this->_cache['batchCache']['orders']['Id'] as $_key => $_batchId) {
            $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['order']['Id'] . '/batch/' . $_batchId . '/result');
            try {
                $response = $this->_client->request()->getBody();
                $response = simplexml_load_string($response);
                $_i = 0;
                $_batch = array_keys($this->_cache['batch']['orders']['Id'][$_key]);
                foreach ($response as $_item) {
                    $_oid = $_batch[$_i];

                    //Report Transaction
                    $this->_cache['responses']['orders'][$_oid] = json_decode(json_encode($_item), TRUE);

                    if ($_item->success == "true") {
                        $_orderStatus = is_array( $this->_cache['ordersToUpsert'])
                                && array_key_exists($_oid, $this->_cache['ordersToUpsert'])
                                && property_exists($this->_cache['ordersToUpsert'][$_oid], 'Status')
                            ? $this->_cache['ordersToUpsert'][$_oid]->Status
                            : TNW_Salesforce_Helper_Salesforce_Data_Order::DRAFT_STATUS;

                        $_orderStatus = is_array( $this->_cache['orderLookup'])
                                && array_key_exists($_oid, $this->_cache['orderLookup'])
                                && property_exists($this->_cache['orderLookup'][$_oid], 'Status')
                            ? $this->_cache['orderLookup'][$_oid]->Status : $_orderStatus;

                        $this->_cache['upsertedOrderStatuses'][$_oid] = $_orderStatus;

                        $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_oid] = (string)$_item->id;

                        $_contactId = ($this->_cache['orderCustomers'][$_oid]->getData('salesforce_id')) ? "'" . $this->_cache['orderCustomers'][$_oid]->getData('salesforce_id') . "'" : 'NULL';
                        $_accountId = ($this->_cache['orderCustomers'][$_oid]->getData('salesforce_account_id')) ? "'" . $this->_cache['orderCustomers'][$_oid]->getData('salesforce_account_id') . "'" : 'NULL';
                        $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET contact_salesforce_id = " . $_contactId . ", account_salesforce_id = " . $_accountId . ", sf_insync = 1, salesforce_id = '" . $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_oid] . "' WHERE entity_id = " . $_entityArray[$_oid] . ";";

                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Order Upserted: ' . $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_oid]);

                        if (Mage::registry('order_cached_' . $_oid)) {
                            $_order = Mage::registry('order_cached_' . $_oid);
                            Mage::unregister('order_cached_' . $_oid);
                            $_order->setData('salesforce_id', $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_oid]);
                            $_order->setData('sf_insync', 1);
                            Mage::register('order_cached_' . $_oid, $_order);
                            unset($_order);
                        }
                    } else {
                        $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())][] = $_oid;
                        $this->_processErrors($_item, 'order', $this->_cache['batch']['orders']['Id'][$_key][$_oid]);
                    }
                    $_i++;
                }
            } catch (Exception $e) {
                // TODO:  Log error, quit
            }
        }

        Mage::dispatchEvent("tnw_salesforce_order_send_after",array(
            "data" => $this->_cache['ordersToUpsert'],
            "result" => $this->_cache['responses']['orders']
        ));

        if (!empty($sql)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
            Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
        }
    }

    protected function _onComplete()
    {
        // Close Jobs
        if ($this->_cache['bulkJobs']['order']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['order']['Id']);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['order']['Id']);
        }
        if ($this->_cache['bulkJobs']['orderProducts']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['orderProducts']['Id']);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['orderProducts']['Id']);
        }
        if ($this->_cache['bulkJobs']['notes']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['notes']['Id']);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['notes']['Id']);
        }
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Clearing bulk sync cache...');

        $this->_cache['bulkJobs'] = array(
            'order' => array('Id' => NULL),
            'orderProducts' => array('Id' => NULL),
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
            'order' => array('Id' => NULL),
            'orderProducts' => array('Id' => NULL),
            'notes' => array('Id' => NULL),
        );

        $this->_cache['batch'] = array();
        $this->_cache['batchCache'] = array();
        $this->_cache['duplicateLeadConversions'] = array();

        $valid = $this->check();

        return $valid;
    }

    /**
     * @param string $type
     * @return bool
     */
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