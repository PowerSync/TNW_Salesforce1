<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Bulk_Product extends TNW_Salesforce_Helper_Salesforce_Product
{
    protected function _onComplete()
    {
        if (!empty($this->_cache['bulkJobs']['product']['Id'])) {
            $this->_closeJob($this->_cache['bulkJobs']['product']['Id']);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['product']['Id']);
        }

        if (!empty($this->_cache['bulkJobs']['product'][$this->_magentoId])) {
            $this->_closeJob($this->_cache['bulkJobs']['product'][$this->_magentoId]);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['product'][$this->_magentoId]);
        }

        if (!empty($this->_cache['bulkJobs']['pricebookEntry']['Id'])) {
            $this->_closeJob($this->_cache['bulkJobs']['pricebookEntry']['Id']);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Closing job: " . $this->_cache['bulkJobs']['pricebookEntry']['Id']);
        }

        // Clear Session variables
        $this->_cache['bulkJobs']['product'] = array('Id' => NULL);
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
            $_keys = array_keys($this->_cache['batch'][$_type]['Id'][$_key]);

            try {
                $response = $this->getBatch($_jobId, $_batch);
            } catch (Exception $e) {
                $response = array_fill(0, count($_keys), $this->_buildErrorResponse($e->getMessage()));

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('_updatePriceBookEntry error ' . $e->getMessage() . '!');
            }

            $_i = 0;
            foreach ($response as $_result) {
                $pricebookEntryKey = $_keys[$_i++];
                list($priceBookId, $_magentoId) = explode(':::', $pricebookEntryKey, 3);
                $sku  = $this->_cache['productIdToSku'][$_magentoId];

                //Report Transaction
                $this->_cache['responses']['pricebooks'][$pricebookEntryKey] = json_decode(json_encode($_result), TRUE);
                if ($_result->success == "true") {

                    if (!empty($this->_cache['pricebookEntryKeyToStore'][$pricebookEntryKey])) {
                        foreach (array_unique((array)$this->_cache['pricebookEntryKeyToStore'][$pricebookEntryKey]) as $store) {
                            list($currencyCode, $storeId) = explode(':::', $store, 2);
                            $this->_cache['toSaveInMagento'][$sku]->pricebookEntryIds[$storeId][] = "{$currencyCode}:{$_result->id}";
                        }
                    }

                    $standard = ($priceBookId == $this->_standardPricebookId) ? ' of standard pricebook' : '';
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveTrace("PRICEBOOK ENTRY: Product SKU ({$sku}) : salesforceID ({$_result->id}){$standard}");
                    continue;
                }

                $this->_cache['toSaveInMagento'][$sku]->SfInSync = 0;
                $this->_processErrors($_result, 'productPricebook', $this->_cache['batch'][$_type]['Id'][$_key][$pricebookEntryKey]);
            }
        }
    }

    protected function _pushEntityPriceBook()
    {
        if (empty($this->_cache['pricebookEntryToSync'])) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('No PriceBook found queued for the synchronization!');

            return;
        }

        if (!$this->_cache['bulkJobs']['pricebookEntry']['Id']) {
            $this->_cache['bulkJobs']['pricebookEntry']['Id'] = $this->_createJob('PricebookEntry', 'upsert', 'Id');

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Syncronizing Products Pricebook Entries, created job: ' . $this->_cache['bulkJobs']['pricebookEntry']['Id']);
        }

        $this->_pushChunked($this->_cache['bulkJobs']['pricebookEntry']['Id'], 'product_default_entries', $this->_cache['pricebookEntryToSync']);

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Checking if PriceBook were successfully synced...');
        $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['pricebookEntry']['Id']);
        $_attempt = 1;
        while (strval($_result) != 'exception' && !$_result) {
            sleep(5);
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['pricebookEntry']['Id']);
            $this->clearMemory();
            $_attempt++;

            $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['pricebookEntry']['Id']);
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('PriceBook sync is complete! Moving on...');
        if (strval($_result) == 'exception') {
            return;
        }

        $this->_updatePriceBookEntry('product_default_entries', $this->_cache['bulkJobs']['pricebookEntry']['Id']);
    }

    /**
     * @param null $jobId
     */
    protected function _preparePriceBooks($jobId = NULL)
    {
        if (!empty($this->_cache['batchCache']['product']['Id']) || !is_array($this->_cache['batchCache']['product']['Id'])) {
            // Get upserted product ID's and create a batches for Pricebooks
            foreach ($this->_cache['batchCache']['product']['Id'] as $_key => $_batchId) {
                $_batch = &$this->_cache['batch']['product']['Id'][$_key];

                try {
                    $response = $this->getBatch($jobId, $_batchId);
                } catch (Exception $e) {
                    $response = array_fill(0, count($_batch), $this->_buildErrorResponse($e->getMessage()));

                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError('Prepare batch #' . $_batchId . ' Error: ' . $e->getMessage());
                }

                $_i = 0;
                $_batchKeys = array_keys($_batch);
                foreach ($response as $_result) {
                    $_sku = $_batchKeys[$_i++];

                    //Report Transaction
                    $this->_cache['responses']['products'][$_sku] = json_decode(json_encode($_result), TRUE);
                    if ($_result->success == "true") {
                        $_product = new stdClass();
                        $_product->salesforceId = (string)$_result->id;
                        $_product->SfInSync = 1;

                        $this->_cache['toSaveInMagento'][$_sku] = $_product;
                        $this->_cache[sprintf('upserted%s', $this->getManyParentEntityType())][$_sku] = (string)$_result->id;

                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveTrace('PRODUCT SKU (' . $_sku . '): salesforceID (' . (string)$_result->id . ')');
                        continue;
                    }

                    // Hide errors when product has been archived
                    foreach ($_result->errors as $_error) {
                        if ($_error->statusCode == 'ENTITY_IS_DELETED') {
                            Mage::getSingleton('tnw_salesforce/tool_log')
                                ->saveWarning('Product w/ SKU "'
                                    . $_batch[$_sku]->ProductCode
                                    . '" have not been synchronized. Entity is deleted or archived.');

                            continue 2;
                        }
                    }

                    $this->_processErrors($_result, 'product', $_batch[$_sku]);
                }
            }

            Mage::dispatchEvent("tnw_salesforce_product_send_after", array(
                "data" => $this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))],
                "result" => $this->_cache['responses']['products']
            ));
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Batch cache is empty. Type is : ' . gettype($this->_cache['batchCache']['product']['Id']));
        }
    }

    /**
     *
     */
    protected function _pushEntity()
    {
        $toUpsertKey = sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()));
        if (empty($this->_cache[$toUpsertKey])) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('No Product found queued for the synchronization!');

            return;
        }

        if (!$this->_cache['bulkJobs']['product']['Id']) {
            $this->_cache['bulkJobs']['product']['Id'] = $this->_createJob('Product2', 'upsert', 'Id');

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Syncronizing Products (on Id), created job: ' . $this->_cache['bulkJobs']['product']['Id']);
        }

        Mage::dispatchEvent("tnw_salesforce_product_send_before", array(
            "data" => $this->_cache[$toUpsertKey]
        ));

        $this->_pushChunked($this->_cache['bulkJobs']['product']['Id'], 'product', $this->_cache[$toUpsertKey]);

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Checking if Product were successfully synced...');
        $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['product']['Id']);
        $_attempt = 1;
        while (strval($_result) != 'exception' && !$_result) {
            sleep(5);
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['product']['Id']);
            $this->clearMemory();
            $_attempt++;

            $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['product']['Id']);
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Product sync is complete! Moving on...');
        if (strval($_result) == 'exception') {
            return;
        }

        $this->_preparePriceBooks($this->_cache['bulkJobs']['product']['Id']);
    }

    /**
     * @return bool
     */
    public function reset()
    {
        parent::reset();

        $this->_cache['batch'] = array();
        $this->_cache['batchCache'] = array();

        $this->_cache['bulkJobs'] = array(
            'product' => array('Id' => NULL),
            'pricebookEntry' => array('Id' => NULL),
        );

        return $this->check();
    }

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
}