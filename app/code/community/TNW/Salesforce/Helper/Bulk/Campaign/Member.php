<?php

class TNW_Salesforce_Helper_Bulk_Campaign_Member extends TNW_Salesforce_Helper_Salesforce_Campaign_Member
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
                = $this->_createJob('CampaignMember', 'upsert', 'Id');

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace(sprintf('Syncronizing %s, created job: %s',
                    ucwords($this->getManyParentEntityType()),
                    $this->_cache['bulkJobs'][$this->_magentoEntityName]['Id']));
        }

        Mage::dispatchEvent('tnw_salesforce_campaign_member_send_before',
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

                    $this->_cache[sprintf('upserted%s', $this->getManyParentEntityType())][$_oid] = (string)$_item->id;
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveTrace(ucwords($this->_magentoEntityName) . ' Upserted: ' . (string)$_item->id);
                    continue;
                }

                $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())][] = $_oid;
                $this->_processErrors($_item, $this->_magentoEntityName, $_batch[$_oid]);
            }
        }

        Mage::dispatchEvent('tnw_salesforce_campaign_member_send_after', array(
            "data" => $this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))],
            "result" => $this->_cache['responses'][strtolower($this->getManyParentEntityType())]
        ));
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
        );

        $this->_cache['batch'] = array();
        $this->_cache['batchCache'] = array();

        return $this->check();
    }
}