<?php

class TNW_Salesforce_Helper_Salesforce_Campaign_Member extends TNW_Salesforce_Helper_Salesforce_Abstract_Base
{
    /**
     * @comment magento entity alias "convert from"
     * @var string
     */
    protected $_magentoEntityName = '';

    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = '';

    /**
     * @var string
     */
    protected $_mappingEntityName = 'CampaignMember';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityModel = 'customer/customer';

    /**
     * @param Mage_Customer_Model_Customer[] $_customers
     * @return bool
     */
    public function forceAdd(array $_customers)
    {
        // test sf api connection
        /** @var TNW_Salesforce_Model_Connection $_client */
        $_client = Mage::getSingleton('tnw_salesforce/connection');
        if (!$_client->initConnection()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR on sync entity, sf api connection failed");

            return false;
        }

        try {
            $_existIds = array_filter(array_map(function(Mage_Customer_Model_Customer $_customer){
                return $_customer->getId();
            }, $_customers));

            $this->_massAddBefore($_existIds);

            foreach ($_customers as $key => $_customer) {
                $_customer->setData('_tnw_order', $key);

                $this->setEntityCache($_customer);
                $entityId = $this->_getEntityId($_customer);

                if (!$this->_checkMassAddEntity($_customer)) {
                    continue;
                }

                // Associate order ID with order Number
                $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING][$entityId] = $this->_getEntityNumber($_customer);
            }

            if (empty($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING])) {
                return false;
            }

            $this->_massAddAfter();

            return !empty($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: " . $e->getMessage());
            return false;
        }
    }

    /**
     * @param $_entity Mage_Customer_Model_Customer
     * @return mixed
     * @throws Exception
     */
    protected function _generateKeyPrefixEntityCache($_entity)
    {
        return $this->_getEntityId($_entity);
    }

    /**
     * @param $_entity Mage_Customer_Model_Customer
     * @return mixed
     * @throws Exception
     */
    protected function _getEntityNumber($_entity)
    {
        return strtolower($_entity->getData('email'));
    }

    /**
     * @param $_entity Mage_Customer_Model_Customer
     * @return string
     */
    protected function _getEntityId($_entity)
    {
        if (!$_entity->getId() && $_entity->hasData('_tnw_order')) {
            return 'guest_' . $_entity->getData('_tnw_order');
        }

        return $_entity->getId();
    }

    /**
     * @param array $_ids
     */
    protected function _massAddBefore($_ids)
    {
        return;
    }

    /**
     * @param $_entity Mage_Customer_Model_Customer
     * @return bool
     * @throws Exception
     */
    protected function _checkMassAddEntity($_entity)
    {
        if (!$_entity->getData('salesforce_id')) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice("SKIPPING: The customer #". $_entity->getData('email') ." is not synchronized!");
            return false;
        }

        if (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($_entity->getGroupId())) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice("SKIPPING: Sync for customer group #" . $_entity->getGroupId() . " is disabled!");
            return false;
        }

        return true;
    }

    /**
     *
     */
    protected function _massAddAfter()
    {
        // Salesforce lookup, find all orders by Magento order number
        $this->_cache[sprintf('%sLookup', $this->_salesforceEntityName)] = Mage::helper('tnw_salesforce/salesforce_data_campaign_member')
            ->lookup($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);

        return;
    }

    /**
     * @param $_entity
     */
    protected function _prepareEntityObjCustom($_entity)
    {
        //$this->_obj->ContactId;
        //$this->_obj->CurrencyIsoCode;
        //$this->_obj->FirstRespondedDate;
        //$this->_obj->HasResponded;
        //$this->_obj->LeadId;
        //$this->_obj->RecordTypeId;
        //$this->_obj->Status;
        return;
    }

    /**
     * @param $_entity
     * @param $type string
     * @return mixed
     */
    protected function _getObjectByEntityType($_entity, $type)
    {
        switch($type)
        {
            case 'Invoice':
                $_object = $_entity;
                break;

            default:
                $_object = null;
                break;
        }

        return $_object;
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
                'Id', array_values($this->_cache[$entityToUpsertKey]), '');

            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_send_after', $this->_magentoEntityName), array(
                "data" => $this->_cache[$entityToUpsertKey],
                "result" => $results
            ));
        }
        catch (Exception $e) {
            $results   = array_fill(0, count($_keys),
                $this->_buildErrorResponse($e->getMessage()));

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
}