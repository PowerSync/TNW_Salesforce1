<?php

abstract class TNW_Salesforce_Helper_Salesforce_Campaign_Abstract extends TNW_Salesforce_Helper_Salesforce_Abstract_Base
{
    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = 'Campaign';

    /**
     * @param array $_ids
     */
    protected function _massAddBefore($_ids)
    {
        return;
    }

    /**
     * @param $_entity
     * @return bool
     * @throws Exception
     */
    protected function _checkMassAddEntity($_entity)
    {
        return true;
    }

    /**
     *
     */
    protected function _massAddAfter()
    {
        // Salesforce lookup, find all orders by Magento order number
        $this->_cache[sprintf('%sLookup', $this->_salesforceEntityName)] = Mage::helper('tnw_salesforce/salesforce_data_campaign')
            ->lookup($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);

        return;
    }

    /**
     * @param $_entity
     */
    protected function _prepareEntityObjCustom($_entity)
    {
        return;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function _pushEntity()
    {
        $entityToUpsertKey = sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()));
        if (empty($this->_cache[$entityToUpsertKey])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('No Invoice found queued for the synchronization!');
            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Invoice Push: Start----------');
        foreach ($this->_cache[$entityToUpsertKey] as $_opp) {
            foreach ($_opp as $_key => $_value) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('%s Object: %s = "%s"', $this->_salesforceEntityName, $_key, $_value));
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("--------------------------");
        }

        $_keys = array_keys($this->_cache[$entityToUpsertKey]);

        try {
            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_send_before', $this->_magentoEntityName),
                array("data" => $this->_cache[$entityToUpsertKey]));

            $results = $this->getClient()->upsert(
                'Id', array_values($this->_cache[$entityToUpsertKey]), 'Campaign');

            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_send_after', $this->_magentoEntityName), array(
                "data" => $this->_cache[$entityToUpsertKey],
                "result" => $results
            ));
        }
        catch (Exception $e) {
            $results   = array_fill(0, count($_keys), $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of an order to Salesforce failed' . $e->getMessage());
        }

        foreach ($results as $_key => $_result) {
            $_entityNum = $_keys[$_key];

            //Report Transaction
            $this->_cache['responses'][strtolower($this->getManyParentEntityType())][$_entityNum] = $_result;

            if (!$_result->success) {
                $this->_processErrors($_result, $this->_salesforceEntityName, $this->_cache[$entityToUpsertKey][$_entityNum]);
                $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())][] = $_entityNum;

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError(sprintf('%s Failed: (%s: ' . $_entityNum . ')', $this->_salesforceEntityName, $this->_magentoEntityName));
            }
            else {
                $_entity = $this->_loadEntityByCache(array_search($_entityNum, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_entityNum);
                $_entity->addData(array(
                    'sf_insync'     => 1,
                    'salesforce_id' => (string)$_result->id
                ));
                $_entity->getResource()->save($_entity);

                $this->_cache[sprintf('upserted%s', $this->getManyParentEntityType())][$_entityNum] = $_result->id;
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('%s Upserted: %s' , $this->_salesforceEntityName, $_result->id));
            }
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Order Push: End----------');
    }

    /**
     * @param $type
     * @throws Exception
     */
    protected function _process($type)
    {
        $this->_prepareEntity();
        $this->_pushEntity();

        $this->clearMemory();
        $this->_onComplete();
    }

    /**
     * @return bool|void
     * Prepare values for the synchroization
     */
    public function reset()
    {
        parent::reset();

        // Clean order cache
        if (is_array($this->_cache['entitiesUpdating'])) {
            foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
                $this->unsetEntityCache($_orderNumber);
            }
        }

        $this->_cache = array(
            'entitiesUpdating' => array(),
            sprintf('upserted%s', $this->getManyParentEntityType()) => array(),
            sprintf('failed%s', $this->getManyParentEntityType()) => array(),
            sprintf('%sToUpsert', lcfirst($this->getItemsField())) => array(),
        );

        return $this->check();
    }
}