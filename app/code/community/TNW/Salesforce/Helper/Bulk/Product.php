<?php

/**
 * Class TNW_Salesforce_Helper_Bulk_Product
 */
class TNW_Salesforce_Helper_Bulk_Product extends TNW_Salesforce_Helper_Salesforce_Product
{
    /**
     * @var array
     */
    protected $_duplicates = array();

    /**
     * @var array
     */
    protected $_allResults = array(
        'existing_products_in_salesforce' => array(),
        'new_products_from_magento' => array(),
        'standard_pricebook_entry' => array(),
        'default_pricebook_entry' => array(),
    );

    protected function _updateMagento()
    {
        // Update
        $this->getHelper()->log("---------- Start: Magento Update ----------");
        $this->_sqlToRun = array();
        $ids = array();

        foreach ($this->_cache['toSaveInMagento'] as $_magentoId => $_product) {
            /**
             * skip order fee products, these products don't exist in magento and use sku instead Id
             */
            if (!is_numeric($_magentoId)) {
                continue;
            }
            $_product->salesforceId = (property_exists($_product, 'productId')) ? $_product->productId : NULL;
            $_product->pricebookEntityIds = (property_exists($_product, 'pricebookEntityIds')) ? $_product->pricebookEntityIds : array();
            $_product->SfInSync = (property_exists($_product, 'syncComplete')) ? $_product->syncComplete : 0;

            if (
                is_array($_product->pricebookEntityIds)
                && array_key_exists('Standard', $_product->pricebookEntityIds)
                && array_key_exists(0, $_product->pricebookEntityIds)
                && $_product->pricebookEntityIds['Standard'] == $_product->pricebookEntityIds[0]
            ) {
                unset($_product->pricebookEntityIds[0]);
            }

            $this->updateMagentoEntityValue($_magentoId, $_product->salesforceId, 'salesforce_id');
            $this->updateMagentoEntityValue($_magentoId, $_product->SfInSync, 'sf_insync', 'catalog_product_entity_int', 0);
            foreach (Mage::app()->getStores() as $_storeId => $_store) {
                $this->updateMagentoEntityValue($_magentoId, $_product->SfInSync, 'sf_insync', 'catalog_product_entity_int', $_storeId);
            }

            if (empty($_product->pricebookEntityIds)) {
                Mage::helper('tnw_salesforce')->log("Could not extract product (ID: " . $_magentoId . ") prices from Salesforce!");
            } else {
                foreach ($_product->pricebookEntityIds as $_key => $_pbeId) {
                    $this->updateMagentoEntityValue($_magentoId, $_pbeId, 'salesforce_pricebook_id', 'catalog_product_entity_text', $_key);
                }
            }

            $ids[] = $_magentoId;

            $this->clearMemory();
            unset($_product);
        }

        if (!empty($this->_sqlToRun)) {
            $this->processSql();
            $this->_sqlToRun = array();

            if ($this->getOrderStoreId() !== NULL) {
                try {
                    $productsCollection = Mage::getModel('catalog/product')->getCollection();
                    $productsCollection->addAttributeToFilter('entity_id', array('in' => $ids));
                    $productsCollection->addAttributeToSelect('*');
                    $productsCollection->joinAttribute('salesforce_id', 'catalog_product/salesforce_id', 'entity_id', null, 'left', $this->getOrderStoreId());
                    $productsCollection->joinAttribute('salesforce_pricebook_id', 'catalog_product/salesforce_pricebook_id', 'entity_id', null, 'left', $this->getOrderStoreId());
                    $productsCollection->setStoreId($this->getOrderStoreId());

                    foreach ($productsCollection as $_product) {
                        if (Mage::registry('product_cache_' . $_product->getId() . '_' . $this->getOrderStoreId())) {
                            Mage::unregister('product_cache_' . $_product->getId() . '_' . $this->getOrderStoreId());
                        }
                        Mage::register('product_cache_' . $_product->getId() . '_' . $this->getOrderStoreId(), $_product);
                    }
                } catch (Exception $e) {
                    Mage::helper('tnw_salesforce')->log("Exception: " . $e->getMessage());
                }
            }
        }

        Mage::helper('tnw_salesforce')->log("Updated: " . count($this->_cache['toSaveInMagento']) . " products!");
        Mage::helper('tnw_salesforce')->log("---------- End: Magento Update ----------");
    }

    protected function _onComplete()
    {

        if ($this->_cache['bulkJobs']['product']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['product']['Id']);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['product']['Id']);
        }

        if ($this->_cache['bulkJobs']['product'][$this->_magentoId]) {
            $this->_closeJob($this->_cache['bulkJobs']['product'][$this->_magentoId]);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['product'][$this->_magentoId]);
        }

        if ($this->_cache['bulkJobs']['pricebookEntry']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['pricebookEntry']['Id']);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['pricebookEntry']['Id']);
        }

        // Clear Session variables
        $this->_cache['bulkJobs']['product'] = array('Id' => NULL, $this->_magentoId => NULL);
        $this->_cache['bulkJobs']['pricebookEntry'] = array('Id' => NULL);

        parent::_onComplete();
    }

    /**
     * @param null $_type
     * @param null $_jobId
     */
    protected function _updatePriceBookEntry($_type = NULL, $_jobId = NULL)
    {
        foreach ($this->_cache['batchCache'][$_type]['Id'] as $_key => $_batch) {
            $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $_jobId . '/batch/' . $_batch . '/result');
            $this->_client->setMethod('GET');
            $this->_client->setHeaders('Content-Type: application/xml');
            $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());
            try {
                $response = $this->_client->request()->getBody();
                $response = simplexml_load_string($response);
                $_keys = array_keys($this->_cache['batch'][$_type]['Id'][$_key]);
                $_i = 0;
                foreach ($response as $_result) {
                    $_tmp = explode(':::', $_keys[$_i]);
                    $pricebookItem = $this->_cache['batch'][$_type]['Id'][$_key][$_keys[$_i]];
                    //Report Transaction
                    $this->_cache['responses']['pricebooks'][$_keys[$_i]] = json_decode(json_encode($_result), TRUE);

                    $_magentoId = $_tmp[1];
                    $currencyCode = $_tmp[2];
                    $pricebookEntryKey = $_keys[$_i];
                    ++$_i;
                    if ((string)$_result->success == "false") {
                        $this->_cache['toSaveInMagento'][$_magentoId]->syncComplete = false;
                        $this->_processErrors($_result, 'productPricebook', $pricebookItem);
                        continue;
                    }
                    if (!is_array($this->_cache['toSaveInMagento'][$_magentoId]->pricebookEntityIds)) {
                        $this->_cache['toSaveInMagento'][$_magentoId]->pricebookEntityIds = array();
                    }

                    $updateStoreIds = array_unique($this->_cache['pricebookEntryKeyToStore'][$pricebookEntryKey]);
                    foreach ($updateStoreIds as $uStoreId) {
                        if (!isset($this->_cache['toSaveInMagento'][$_magentoId]->pricebookEntityIds[$uStoreId])) {
                            $this->_cache['toSaveInMagento'][$_magentoId]->pricebookEntityIds[$uStoreId] = '';
                        }
                        $this->_cache['toSaveInMagento'][$_magentoId]->pricebookEntityIds[$uStoreId] .= $currencyCode . ':' . (string)$_result->id . "\n";
                    }

                    if ($this->_cache['toSaveInMagento'][$_magentoId]->syncComplete != false) {
                        $this->_cache['toSaveInMagento'][$_magentoId]->syncComplete = true;
                    }
                    $this->clearMemory();
                }
            } catch (Exception $e) {
                $this->getHelper()->log('_updatePriceBookEntry error ' . $e->getMessage() . '!');

                // TODO:  Log error, quit
            }
        }
    }

    protected function _pushPricebooks()
    {
        if (!empty($this->_cache['standardPricebooksToUpsert']) || !empty($this->_cache['pricebookEntryToSync'])) {
            if (!$this->_cache['bulkJobs']['pricebookEntry']['Id']) {
                $this->_cache['bulkJobs']['pricebookEntry']['Id'] = $this->_createJob('PricebookEntry', 'upsert', 'Id');
                $this->getHelper()->log('Syncronizing Products Pricebook Entries, created job: ' . $this->_cache['bulkJobs']['pricebookEntry']['Id']);
            }
        }
        if (!empty($this->_cache['standardPricebooksToUpsert'])) {
            $this->_pushChunked($this->_cache['bulkJobs']['pricebookEntry']['Id'], 'product_standard_entries', $this->_cache['standardPricebooksToUpsert']);

            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['pricebookEntry']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['pricebookEntry']['Id']);
                $this->clearMemory();
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['pricebookEntry']['Id']);
            }

            // 1.b If SF fails to upsert a pricebook, but the products made it... stop
            if (strval($_result) == 'exception') {
                return false;
            }
        }

        if (!empty($this->_cache['pricebookEntryToSync'])) {
            $this->_pushChunked($this->_cache['bulkJobs']['pricebookEntry']['Id'], 'product_default_entries', $this->_cache['pricebookEntryToSync']);

            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['pricebookEntry']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['pricebookEntry']['Id']);
                $this->clearMemory();
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['pricebookEntry']['Id']);
            }

            // 1.b If SF fails to upsert a pricebook, but the products made it... stop
            if (strval($_result) == 'exception') {
                return false;
            }
        }

        if (!empty($this->_cache['standardPricebooksToUpsert'])) {
            $this->_updatePriceBookEntry('product_standard_entries', $this->_cache['bulkJobs']['pricebookEntry']['Id']);
        }
        if (!empty($this->_cache['pricebookEntryToSync'])) {
            $this->_updatePriceBookEntry('product_default_entries', $this->_cache['bulkJobs']['pricebookEntry']['Id']);
        }
    }

    /**
     * @param null $_type
     * @param null $jobId
     */
    protected function _preparePriceBooks($_type = NULL, $jobId = NULL)
    {
        // Get upserted product ID's and create a batches for Pricebooks
        foreach ($this->_cache['batchCache']['product'][$_type] as $_key => $_batch) {
            $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $jobId . '/batch/' . $_batch . '/result');
            $this->_client->setMethod('GET');
            $this->_client->setHeaders('Content-Type: application/xml');
            $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());
            try {
                $response = $this->_client->request()->getBody();
                $response = simplexml_load_string($response);
                $_magentoIds = array_keys($this->_cache['batch']['product'][$_type][$_key]);
                $_i = 0;
                foreach ($response as $_result) {
                    $_magentoId = $_magentoIds[$_i];
                    //Report Transaction
                    $this->_cache['responses']['products'][$_magentoId] = json_decode(json_encode($_result), TRUE);

                    $_i++;
                    if ((string)$_result->success == "false") {
                        // Hide errors when product has been archived
                        foreach ($_result->errors as $_error) {
                            if ($_error->message == 'entity is deleted'
                                && $_error->statusCode == 'ENTITY_IS_DELETED'){
                                Mage::getSingleton('adminhtml/session')
                                    ->addWarning('Product w/ SKU "'
                                        . $this->_obj->ProductCode
                                        . '" have not been synchronized. Entity is deleted or archived.'
                                    );
                                continue 2;
                            }
                        }
                        $this->_processErrors($_result, 'product', $this->_cache['batch']['product'][$_type][$_key][$_magentoId]);
                        continue;
                    }
                    $this->_cache['toSaveInMagento'][$_magentoId]->productId = (string)$_result->id;
                    $this->_cache['toSaveInMagento'][$_magentoId]->syncComplete = true;
                    $this->_addPriceBookEntry($_magentoId, $this->_cache['toSaveInMagento'][$_magentoId]->productId);
                }
            } catch (Exception $e) {
                Mage::helper('tnw_salesforce')->log('_preparePriceBooks function has an error: '.$e->getMessage());

                // TODO:  Log error, quit
            }
        }

        Mage::dispatchEvent("tnw_salesforce_product_send_after",array(
            "data" => $this->_cache['productsToSync'][$_type],
            "result" => $this->_cache['responses']['products']
        ));

        $this->clearMemory();
    }

    /**
     * decorator pattern used
     *
     * @param array $ids
     */
    public function massAdd($ids = array())
    {
        parent::massAdd($ids);

        $defaultObject = new stdClass();
        $defaultObject->productId = null;
        $defaultObject->pricebookEntityIds = null;
        $defaultObject->syncComplete = null;

        foreach ($ids as $id) {
            $object = clone $defaultObject;
            $object->magentoId = $id;
            $this->_cache['toSaveInMagento'][$id] = $object;
        }
    }

    /**
     * @param string $type
     * @return bool|void
     */
    protected function _pushProducts($type = 'update')
    {
        if (!empty($this->_cache['productsToSync']['Id'])) {
            if (!$this->_cache['bulkJobs']['product']['Id']) {
                $this->_cache['bulkJobs']['product']['Id'] = $this->_createJob('Product2', 'upsert', 'Id');
                Mage::helper('tnw_salesforce')->log('Syncronizing Products (on Id), created job: ' . $this->_cache['bulkJobs']['product']['Id']);

                Mage::dispatchEvent("tnw_salesforce_product_send_before",array("data" => $this->_cache['productsToSync']['Id']));
                $this->_pushChunked($this->_cache['bulkJobs']['product']['Id'], 'product', $this->_cache['productsToSync']['Id'], 'Id');
            }

            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['product']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['product']['Id']);
                $this->clearMemory();
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['product']['Id']);
            }

            // Products: 1.a if SF fails to upsert a product, dont attempt to sync a pricebook and stop
            if (strval($_result) == 'exception') {
                return false;
            }
        }

        if (!empty($this->_cache['productsToSync'][$this->_magentoId])) {
            if (!$this->_cache['bulkJobs']['product'][$this->_magentoId]) {
                $this->_cache['bulkJobs']['product'][$this->_magentoId] = $this->_createJob('Product2', 'upsert', $this->_magentoId);
                Mage::helper('tnw_salesforce')->log('Syncronizing Products (on Mid), created job: ' . $this->_cache['bulkJobs']['product'][$this->_magentoId]);

                Mage::dispatchEvent("tnw_salesforce_product_send_before",array("data" => $this->_cache['productsToSync'][$this->_magentoId]));
                $this->_pushChunked($this->_cache['bulkJobs']['product'][$this->_magentoId], 'product', $this->_cache['productsToSync'][$this->_magentoId], $this->_magentoId);
            }

            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['product'][$this->_magentoId]);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['product'][$this->_magentoId]);
                $this->clearMemory();
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['product'][$this->_magentoId]);
            }

            // Products: 1.a if SF fails to upsert a product, dont attempt to sync a pricebook and stop
            if (strval($_result) == 'exception') {
                return false;
            }
        }

        $this->_cache['productsLookup'] = Mage::helper('tnw_salesforce/salesforce_lookup')->productLookup($this->_cache['productIdToSku']);

        if (!empty($this->_cache['productsToSync']['Id'])) {
            $this->_preparePriceBooks('Id', $this->_cache['bulkJobs']['product']['Id']);
        }
        if (!empty($this->_cache['productsToSync'][$this->_magentoId])) {
            $this->_preparePriceBooks($this->_magentoId, $this->_cache['bulkJobs']['product'][$this->_magentoId]);
        }

        foreach ($this->_cache['pricebookEntryToSync'] as $_key => $_tmpObject) {
            if (!is_object($_tmpObject)) {continue;}
            if (!$this->isFromCLI()) {
                // Dump products that will be synced
                foreach ($_tmpObject as $key => $value) {
                    Mage::helper('tnw_salesforce')->log("Pricebook Object: " . $key . " = '" . $value . "'");
                }

                Mage::helper('tnw_salesforce')->log("---------------------------------------");
            }
        }

        //Prepeare Pricebooks
        foreach ($this->_cache['pricebookEntryToSync'] as $_key => $_pricebookEntry) {
            if (!property_exists($_pricebookEntry, 'Id')) {
                if ($_pricebookEntry->Pricebook2Id == $this->_standardPricebookId) {
                    $this->_cache['standardPricebooksToUpsert'][$_key] = $_pricebookEntry;
                    unset($this->_cache['pricebookEntryToSync'][$_key]); // remove duplicate
                    continue;
                }
            }
            $this->_cache['pricebookEntryToSync'][$_key] = $_pricebookEntry;
        }

        $this->_pushPricebooks();
        $this->clearMemory();
    }

    /**
     * @return bool
     */
    public function reset()
    {
        parent::reset();

        $this->_cache['batch'] = array();
        $this->_cache['batchCache'] = array();

        $this->_cache['standardPricebooksToUpsert'] = array();
        $this->_cache['bulkJobs'] = array(
            'product' => array('Id' => NULL, $this->_magentoId => NULL),
            'pricebookEntry' => array('Id' => NULL),
        );

        $this->_client = $this->getHttpClient();
        $this->_client->setConfig(
            array(
                'maxredirects' => 0,
                'timeout' => 10,
                'keepalive' => true,
                'storeresponse' => true,
            )
        );

        $valid = $this->check();

        return $valid;
    }

    public function process()
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
}