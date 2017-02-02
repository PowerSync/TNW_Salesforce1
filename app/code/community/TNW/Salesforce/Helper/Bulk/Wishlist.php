<?php

class TNW_Salesforce_Helper_Bulk_Wishlist extends TNW_Salesforce_Helper_Salesforce_Wishlist
{
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

    /**
     * @throws Exception
     */
    protected function _pushEntity()
    {
        $keyUpsert = sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()));
        if (empty($this->_cache[$keyUpsert])) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('No Wishlist found queued for the synchronization!');

            return;
        }

        if (!$this->_cache['bulkJobs']['opportunity']['Id']) {
            // Create Job
            $this->_cache['bulkJobs']['opportunity']['Id'] = $this->_createJob('Opportunity', 'upsert', 'Id');
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Syncronizing Opportunities, created job: ' . $this->_cache['bulkJobs']['opportunity']['Id']);
        }

        $this->_pushChunked($this->_cache['bulkJobs']['opportunity']['Id'], 'opportunities', $this->_cache[$keyUpsert], 'Id');
        if (!$this->waitingSuccessStatusBatch($this->_cache['bulkJobs']['opportunity']['Id'])) {
            return;
        }

        foreach ($this->_cache['batchCache']['opportunities']['Id'] as $_key => $_batchId) {
            $_batch = &$this->_cache['batch']['opportunities']['Id'][$_key];
            try {
                $response = $this->getBatch($this->_cache['bulkJobs']['opportunity']['Id'], $_batchId);
            } catch (Exception $e) {
                $response = array_fill(0, count($_batch), $this->_buildErrorResponse($e->getMessage()));

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Prepare batch #'. $_batchId .' Error: ' . $e->getMessage());
            }

            $_i = 0;
            $entityNumbers = array_keys($_batch);
            foreach ($response as $_item) {
                $entityNumber = $entityNumbers[$_i++];

                //Report Transaction
                $this->_cache['responses']['opportunities'][$entityNumber] = json_decode(json_encode($_item), true);
                if ($_item->success == 'true') {
                    $entity = $this->getEntityCache($entityNumber);
                    $entity->addData(array(
                        'salesforce_id' => (string)$_item->id,
                        'sf_insync' => 1,
                    ));
                    $entity->getResource()->save($entity);

                    $this->_cache[sprintf('upserted%s',$this->getManyParentEntityType())][$entityNumber] = (string)$_item->id;
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveTrace('Opportunity Upserted: ' . (string)$_item->id);
                    continue;
                }

                $this->_cache['failedOpportunities'][] = $entityNumber;
                $this->_processErrors($_item, 'Opportunity', $_batch[$entityNumber]);
            }
        }
    }

    protected function _pushRemainingEntityData()
    {
        $itemKey = sprintf('%sToUpsert', lcfirst($this->getItemsField()));
        if (!empty($this->_cache[$itemKey])) {

            if (!$this->_cache['bulkJobs']['opportunityProducts']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['opportunityProducts']['Id'] = $this->_createJob('OpportunityLineItem', 'upsert', 'Id');

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Syncronizing Opportunity Products, created job: ' . $this->_cache['bulkJobs']['opportunityProducts']['Id']);
            }

            $this->_pushChunked($this->_cache['bulkJobs']['opportunityProducts']['Id'], 'opportunityProducts', $this->_cache[$itemKey]);
            if ($this->waitingSuccessStatusBatch($this->_cache['bulkJobs']['opportunityProducts']['Id'])) {
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
                    $entityNumbers = array_flip($this->_cache['upserted' . $this->getManyParentEntityType()]);
                    $entityItemNumbers = array_keys($_batch);
                    foreach ($response as $_item) {
                        $entityItemNumber = $entityItemNumbers[$_i++];
                        $entityNum = $entityNumbers[$_batch[$entityItemNumber]->OpportunityId];
                        $entity = $this->getEntityCache($entityNum);

                        //Report Transaction
                        $this->_cache['responses']['opportunityLineItems'][$entityNum]['subObj'][] = json_decode(json_encode($_item), TRUE);

                        if ($_item->success == 'true') {
                            $_itemCollection = $entity->getItemCollection()->getItemById(str_replace('cart_', '', $entityItemNumber));
                            if ($_itemCollection instanceof Mage_Core_Model_Abstract) {
                                $_itemCollection->setData('salesforce_id', (string)$_item->id);
                                $_itemCollection->getResource()->save($_itemCollection);
                            }

                            Mage::getSingleton('tnw_salesforce/tool_log')
                                ->saveTrace('Cart Item (id: ' . (string)$_item->id . ') for (order: ' . $entityNum . ') upserted.');
                            continue;
                        }

                        // Reset sync status
                        $entity->setData('sf_insync', 0);
                        $entity->getResource()->save($entity);

                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveError('ERROR: One of the Cart Item for (Wishlist: ' . $entityNum . ') failed to upsert.');

                        $this->_processErrors($_item, 'OpportunityLineItem', $_batch[$entityItemNumber]);
                    }
                }
            }
        }

        if (!empty($this->_cache['contactRolesToUpsert'])) {

            if (!$this->_cache['bulkJobs']['customerRoles']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['customerRoles']['Id'] = $this->_createJob('OpportunityContactRole', 'upsert', 'Id');

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Syncronizing Opportunity Contact Roles, created job: ' . $this->_cache['bulkJobs']['customerRoles']['Id']);
            }

            $this->_pushChunked($this->_cache['bulkJobs']['customerRoles']['Id'], 'opportunityContactRoles', $this->_cache['contactRolesToUpsert']);
            if ($this->waitingSuccessStatusBatch($this->_cache['bulkJobs']['customerRoles']['Id'])) {
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
                    $entityNumbers = array_flip($this->_cache['upserted'.$this->getManyParentEntityType()]);
                    $entityItemNumbers = array_keys($_batch);
                    foreach ($response as $_rKey => $_item) {
                        $entityItemNumber = $entityItemNumbers[$_i++];
                        $entityNum = $entityNumbers[$_batch[$entityItemNumber]->OpportunityId];
                        $entity = $this->getEntityCache($entityNum);

                        //Report Transaction
                        $this->_cache['responses']['opportunityCustomerRoles'][$entityNum]['subObj'][] = json_decode(json_encode($_item), TRUE);

                        if ($_item->success == 'true') {
                            Mage::getSingleton('tnw_salesforce/tool_log')
                                ->saveTrace('Contact Role (role: ' . $_batch[$entityItemNumber]->Role . ') for (quote: ' . $entityNum . ') upserted.');

                            continue;
                        }

                        // Reset sync status
                        $entity->setData('sf_insync', 0);
                        $entity->getResource()->save($entity);

                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveError('ERROR: Contact Role (role: ' . $_batch[$entityItemNumber]->Role . ') for (quote: ' . $entityNum . ') failed to upsert.');

                        $this->_processErrors($_item, 'OpportunityContactRole', $_batch[$entityItemNumber]);
                    }
                }
            }
        }
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
        );

        $this->_cache['batch'] = array();
        $this->_cache['batchCache'] = array();

        return $this->check();
    }

    /**
     *
     */
    protected function _onComplete()
    {
        // Close Jobs
        if ($this->_cache['bulkJobs']['opportunity']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['opportunity']['Id']);

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("Closing job: {$this->_cache['bulkJobs']['opportunity']['Id']}");
        }

        if ($this->_cache['bulkJobs']['opportunityProducts']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['opportunityProducts']['Id']);

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("Closing job: {$this->_cache['bulkJobs']['opportunityProducts']['Id']}");
        }

        if ($this->_cache['bulkJobs']['customerRoles']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['customerRoles']['Id']);

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("Closing job: {$this->_cache['bulkJobs']['customerRoles']['Id']}");
        }

        parent::_onComplete();
    }
}