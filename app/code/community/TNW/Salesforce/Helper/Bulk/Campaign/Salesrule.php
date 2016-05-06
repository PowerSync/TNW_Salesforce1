<?php

class TNW_Salesforce_Helper_Bulk_Campaign_Salesrule extends TNW_Salesforce_Helper_Salesforce_Campaign_Salesrule
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
                = $this->_createJob('Campaign', 'upsert', 'Id');

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
                    $this->_magentoEntityName, $this->_cache['bulkJobs'][$this->_magentoEntityName]['Id']));

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
        foreach ($this->_cache['batchCache'][$this->getManyParentEntityType()]['Id'] as $_key => $_batchId) {
            $_batch = &$this->_cache['batch'][$this->getManyParentEntityType()]['Id'][$_key];

            try {
                $response = $this->getBatch($this->_cache['bulkJobs'][$this->_magentoEntityName]['Id'], $_batchId);
            } catch (Exception $e) {
                $response = array_fill(0, count($_batch), $this->_buildErrorResponse($e->getMessage()));

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Prepare batch #'. $_batchId .' Error: ' . $e->getMessage());
            }

            $_i = 0;
            $_batchKeys = array_keys($_batch);
            foreach ($response as $_item) {
                $_oid = $_batchKeys[$_i++];

                //Report Transaction
                $this->_cache['responses'][strtolower($this->getManyParentEntityType())][$_oid] = json_decode(json_encode($_item), TRUE);

                if ($_item->success == "true") {
                    $_record = $this->_loadEntityByCache(array_search($_oid, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_oid);
                    $_record->setData('salesforce_id', (string)$_item->id);
                    $_record->setData('sf_insync', 1);
                    $_record->getResource()->save($_record);

                    $this->_cache[sprintf('upserted%s', $this->getManyParentEntityType())][$_oid] = (string)$_item->id;
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveTrace(ucwords($this->_magentoEntityName) . ' Upserted: ' . (string)$_item->id);
                    continue;
                }

                $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())][] = $_oid;
                $this->_processErrors($_item, $this->_magentoEntityName, $_batch[$_oid]);
            }
        }

        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_send_after', $this->_magentoEntityName), array(
            "data" => $this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))],
            "result" => $this->_cache['responses'][strtolower($this->getManyParentEntityType())]
        ));
    }
}