<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 * Class TNW_Salesforce_Helper_Salesforce_Order
 *
 * @method Mage_Sales_Model_Order _loadEntityByCache($_key, $cachePrefix = null)
 */

class TNW_Salesforce_Helper_Salesforce_Order extends TNW_Salesforce_Helper_Salesforce_Abstract_Order
{

    /**
     * @comment magento entity alias "convert from"
     * @var string
     */
    protected $_magentoEntityName = 'order';

    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = 'order';

    /**
     * @var string
     */
    protected $_mappingEntityName = 'Order';

    /**
     * @var string
     */
    protected $_mappingEntityItemName = 'OrderItem';

    /**
     * @comment magento entity model alias
     * @var string
     */
    protected $_magentoEntityModel = 'sales/order';

    /**
     * @comment magento entity model alias
     * @var string
     */
    protected $_magentoEntityId = 'increment_id';

    /**
     * @comment magento entity item qty field name
     * @var string
     */
    protected $_itemQtyField = 'qty_ordered';

    /**
     * @comment salesforce field name to assign parent entity
     * @var string
     */
    protected $_salesforceParentIdField = 'OrderId';

    /**
     * @comment salesforce field name to assign parent entity
     * @var string
     */
    protected $_salesforceParentOpportunityField = 'OpportunityId';

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @param $key
     */
    protected function _prepareEntityObjCustom($_entity, $key)
    {
        $_entityNumber = $this->_getEntityNumber($_entity);
        if (Mage::helper('tnw_salesforce')->getType() == 'PRO') {
            $disableSyncField = Mage::helper('tnw_salesforce/config')->getDisableSyncField();
            $this->_obj->$disableSyncField = true;
        }

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $this->getCurrencyCode($_entity);
        }

        $_oppExists = property_exists($this->_obj, 'OpportunityId');
        if ((!$_oppExists || ($_oppExists && empty($this->_obj->OpportunityId)))
            && !empty($this->_cache['abandonedCart'])
            && array_key_exists($_entity->getQuoteId(), $this->_cache['abandonedCart'])
        ) {
            $this->_obj->OpportunityId = $this->_cache['abandonedCart'][$_entity->getQuoteId()];
        }

        if (property_exists($this->_obj, 'OpportunityId') && empty($this->_obj->OpportunityId)) {
            unset($this->_obj->OpportunityId);
        }

        /**
         * Set 'Draft' status temporarry, it's necessary for order change with status from "Activated" group
         */
        $_currentStatus = $this->_obj->Status;
        $_draftStatus = Mage::helper('tnw_salesforce/config_sales')->getOrderDraftStatus();
        if ($_currentStatus != $_draftStatus) {
            $this->_obj->Status = $_draftStatus;

            $_toActivate = new stdClass();
            $_toActivate->Status = $_currentStatus;
            $_toActivate->Id = NULL;

            if (Mage::helper('tnw_salesforce')->getType() == 'PRO') {
                $disableSyncField = Mage::helper('tnw_salesforce/config')->getDisableSyncField();
                $_toActivate->$disableSyncField = true;
            }

            $this->_cache['orderToActivate'][$_entityNumber] = $_toActivate;
        }
    }

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @param $types string
     * @return mixed
     */
    protected function _getObjectByEntityType($_entity, $types)
    {
        switch($types)
        {
            case 'Aitoc':
                $_customer     = $this->_getObjectByEntityType($_entity, 'Customer');
                $aitocValues   = array('order' => NULL, 'customer' => NULL);

                $modules       = Mage::getConfig()->getNode('modules')->children();
                if (property_exists($modules, 'Aitoc_Aitcheckoutfields')) {
                    $aitocValues['customer'] = Mage::getModel('aitcheckoutfields/transport')->loadByCustomerId($_customer->getId());
                    $aitocValues['order'] = Mage::getModel('aitcheckoutfields/transport')->loadByOrderId($_entity->getId());
                }

                $_object = $aitocValues;
                break;

            default:
                $_object = parent::_getObjectByEntityType($_entity, $types);
                break;
        }

        return $_object;
    }

    /**
     * @return bool|void
     * Prepare values for the synchroization
     */
    public function reset()
    {
        parent::reset();

        // Reset cache (need to conver to magento cache
        $this->_cache = array(
            sprintf('upserted%s', $this->getManyParentEntityType()) => array(),
            'upsertedOrderStatuses' => array(),
            'accountsLookup' => array(),
            'entitiesUpdating' => array(),
            'abandonedCart' => array(),
            'orderLookup' => array(),
            'ordersToUpsert' => array(),
            'orderItemsToUpsert' => array(),
            'leadsToConvert' => array(),
            'leadLookup' => array(),
            'orderCustomers' => array(),
            'toSaveInMagento' => array(),
            'contactsLookup' => array(),
            sprintf('failed%s', $this->getManyParentEntityType()) => array(),
            'orderToEmail' => array(),
            'convertedLeads' => array(),
            'orderToCustomerId' => array(),
            'orderToActivate' => array(),
            'notesToUpsert' => array(),
            'responses' => array(
                'leadsToConvert' => array(),
                'orders' => array(),
                'orderItems' => array(),
                'notes' => array(),
            ),
            'orderCustomersToSync' => array(),
            'leadsFailedToConvert' => array(),
            'userRulesToUpsert' => array(),
        );

        return $this->check();
    }

    /**
     * Clean up all the data & memory
     */
    protected function _onComplete()
    {
        parent::_onComplete();

        if (Mage::helper('tnw_salesforce')->isRemoteLogEnabled()) {
            $logger = Mage::helper('tnw_salesforce/report');
            $logger->reset();

            $logger->add('Salesforce', 'leadsToConvert', $this->_cache['leadsToConvert'], $this->_cache['responses']['leadsToConvert']);
            $logger->add('Salesforce', 'Order', $this->_cache['ordersToUpsert'], $this->_cache['responses']['orders']);
            $logger->add('Salesforce', 'OrderItem', $this->_cache['orderItemsToUpsert'], $this->_cache['responses']['orderItems']);
            $logger->add('Salesforce', 'Note', $this->_cache['notesToUpsert'], $this->_cache['responses']['notes']);

            $logger->send();
        }

        // Logout
        $this->reset();
        $this->clearMemory();
    }

    /**
     * Push Order(s) to Salesforce
     */
    protected function _pushEntity()
    {
        if (empty($this->_cache['ordersToUpsert'])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('No Orders found queued for the synchronization!');
            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Order Push: Start----------');
        foreach (array_values($this->_cache['ordersToUpsert']) as $_opp) {
            foreach ($_opp as $_key => $_value) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Order Object: " . $_key . " = '" . $_value . "'");
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("--------------------------");
        }

        $_keys = array_keys($this->_cache['ordersToUpsert']);
        try {
            Mage::dispatchEvent("tnw_salesforce_order_send_before", array(
                "data" => $this->_cache['ordersToUpsert']
            ));

            $results = $this->getClient()->upsert('Id', array_values($this->_cache['ordersToUpsert']), 'Order');
            Mage::dispatchEvent("tnw_salesforce_order_send_after", array(
                "data" => $this->_cache['ordersToUpsert'],
                "result" => $results
            ));
        } catch (Exception $e) {
            $results = array_fill(0, count($_keys),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of an order to Salesforce failed' . $e->getMessage());
        }

        $_undeleteIds = array();
        foreach ($results as $_key => $_result) {
            $_orderNum = $_keys[$_key];

            //Report Transaction
            $this->_cache['responses']['orders'][$_orderNum] = $_result;
            if (!$_result->success) {
                if ($_result->errors[0]->statusCode == "ENTITY_IS_DELETED") {
                    $_undeleteIds[] = $_orderNum;
                }

                $this->_cache['failedOrders'][] = $_orderNum;

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Order Failed: (order: ' . $_orderNum . ')');
                $this->_processErrors($_result, 'order', $this->_cache['ordersToUpsert'][$_orderNum]);
            } else {
                $_order    = $this->_loadEntityByCache(array_search($_orderNum, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_orderNum);
                $_customer = $this->_getObjectByEntityType($_order, 'Customer');

                $_order->addData(array(
                    'contact_salesforce_id' => $_customer->getData('salesforce_id'),
                    'account_salesforce_id' => $_customer->getData('salesforce_account_id'),
                    'sf_insync'             => 1,
                    'salesforce_id'         => $_result->id
                ));

                $_order->getResource()->save($_order);

                $this->_cache[sprintf('upserted%s', $this->getManyParentEntityType())][$_orderNum] = $_result->id;
                $this->_cache['upsertedOrderStatuses'][$_orderNum] = (is_array($this->_cache['orderLookup']) && array_key_exists($_orderNum, $this->_cache['orderLookup']))
                    ? $this->_cache['orderLookup'][$_orderNum]->Status : $this->_cache['ordersToUpsert'][$_orderNum]->Status;

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Order Upserted: ' . $_result->id);
            }
        }

        if (!empty($_undeleteIds)) {
            $_deleted = Mage::helper('tnw_salesforce/salesforce_data_order')->lookup($_undeleteIds);
            $_toUndelete = array();
            foreach ($_deleted as $_object) {
                $_toUndelete[] = $_object->Id;
            }
            if (!empty($_toUndelete)) {
                $this->getClient()->undelete($_toUndelete);
            }
        }


        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Order Push: End----------');
    }

    protected function _checkPrepareEntityItem($_key)
    {
        $_orderNumber   = $this->_cache['entitiesUpdating'][$_key];
        $_orderStatuses = $this->_cache['upsertedOrderStatuses'];

        $_draftStatus = Mage::helper('tnw_salesforce/config_sales')->getOrderDraftStatus();
        if (array_key_exists($_orderNumber, $_orderStatuses) && $_orderStatuses[$_orderNumber] != $_draftStatus) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('ORDER (' . $_orderNumber . '): Skipping, order is already Active!');
            return false;
        }

        return true;
    }

    protected function _pushRemainingCustomEntityData()
    {
        parent::_pushRemainingCustomEntityData();

        /** @var $manualSync TNW_Salesforce_Helper_Salesforce_Newslettersubscriber */
        $manualSync = Mage::helper('tnw_salesforce/salesforce_newslettersubscriber');
        if (!empty($this->_cache['productCampaignAssignment']) && $manualSync->validateSync(true)) {
            $campaignMember = Mage::helper('tnw_salesforce/salesforce_campaign_member');
            if ($campaignMember->reset() && $campaignMember->memberAdd($this->_cache['productCampaignAssignment'])) {
                $campaignMember->process();
            }
        }

        // Activate orders
        if (!empty($this->_cache['orderToActivate'])) {
            foreach ($this->_cache['orderToActivate'] as $_orderNum => $_object) {
                if (!isset($this->_cache['upserted'.$this->getManyParentEntityType()][$_orderNum])) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveTrace('SKIPPING ACTIVATION: Order (' . $_orderNum . ') did not make it into Salesforce.');

                    unset($this->_cache['orderToActivate'][$_orderNum]);
                    continue;
                }

                $_object->Id = $this->_cache['upserted'.$this->getManyParentEntityType()][$_orderNum];

                // Check if at least 1 product was added to the order before we try to activate
                if (empty($this->_cache['orderItemsProductsToSync'][$_object->Id])) {
                    unset($this->_cache['orderToActivate'][$_orderNum]);
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING ACTIVATION: Order (' . $_orderNum . ') Products did not make it into Salesforce.');
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice("SKIPPING ORDER ACTIVATION: Order (" . $_orderNum . ") could not be activated w/o any products!");
                    }
                }
            }

            if (!empty($this->_cache['orderToActivate'])) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Activating Orders: Start----------');

                $_orderChunk = array_chunk($this->_cache['orderToActivate'], TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);
                foreach ($_orderChunk as $_itemsToPush) {
                    $this->_activateOrders($_itemsToPush);
                }

                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Activating Orders: End----------');
            }
        }

        if (!empty($this->_cache['userRulesToUpsert'])) {
            $campaignMember = Mage::helper('tnw_salesforce/salesforce_campaign_member');
            if ($campaignMember->reset() && $campaignMember->memberAdd($this->_cache['userRulesToUpsert'])) {
                $campaignMember->process();
            }
        }
    }

    /**
     * @param array $chunk
     * push OrderItem chunk into Salesforce
     * @return mixed|void
     */
    protected function _pushEntityItems($chunk = array())
    {
        $_orderNumbers = array_flip($this->_cache['upserted'.$this->getManyParentEntityType()]);
        $_chunkKeys = array_keys($chunk);
        try {
            $results = $this->getClient()->upsert("Id", array_values($chunk), 'OrderItem');
        } catch (Exception $e) {
            $results = array_fill(0, count($chunk),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of Order Items to SalesForce failed' . $e->getMessage());
        }

        foreach ($results as $_key => $_result) {
            $_cartItemId = $_chunkKeys[$_key];
            $_sOrderId   = $this->_cache['orderItemsToUpsert'][$_cartItemId]->OrderId;
            $_entityNum  = $_orderNumbers[$_sOrderId];
            $_entity     = $this->_loadEntityByCache(array_search($_entityNum, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_entityNum);

            //Report Transaction
            $this->_cache['responses']['orderItems'][$_entityNum]['subObj'][$_cartItemId] = $_result;
            if (!$_result->success) {
                // Reset sync status
                $_entity->setData('sf_insync', 0);
                $_entity->getResource()->save($_entity);

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR: One of the Cart Item for (order: ' . $_entityNum . ') failed to upsert.');
                $this->_processErrors($_result, 'orderCart', $chunk[$_cartItemId]);
            }
            else {
                $_item = $_entity->getItemsCollection()->getItemById(str_replace('cart_', '', $_cartItemId));
                if ($_item instanceof Mage_Core_Model_Abstract) {
                    $_item->setData('salesforce_id', $_result->id);
                    $_item->getResource()->save($_item);
                }

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Cart Item (id: ' . $_result->id . ') for (order: ' . $_entityNum . ') upserted.');
            }
        }
    }

    /**
     * @param array $chunk
     * Actiate orders in Salesforce
     */
    protected function _activateOrders($chunk = array())
    {
        try {
            $results = $this->getClient()->upsert("Id", array_values($chunk), 'Order');
        } catch (Exception $e) {
            $results = array();
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Activation of Orders in SalesForce failed!' . $e->getMessage());
        }

        $_orderNumbers = array_keys($chunk);
        foreach ($results as $_key => $_result) {
            $_entityNum = $_orderNumbers[$_key];

            if (!$_result->success) {
                // Reset sync status
                $_entity = $this->_loadEntityByCache(array_search($_entityNum, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_entityNum);
                $_entity->setData('sf_insync', 0);
                $_entity->getResource()->save($_entity);

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR: Order: ' . $_entityNum . ') failed to activate.');
            }
            else {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Order: ' . $_entityNum . ') activated.');
            }
        }
    }

    protected function _prepareEntityAfter()
    {
        $_toUpsert = &$this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))];
        if (empty($_toUpsert)) {
            return;
        }

        // Prepare opportunity array
        $_opportunityIds = array();
        foreach ($_toUpsert as $entityNumber => $entity) {
            if (!property_exists($entity, $this->_salesforceParentOpportunityField)) {
                continue;
            }

            if (empty($entity->{$this->_salesforceParentOpportunityField})) {
                continue;
            }

            $_opportunityIds[$entityNumber] = $entity->{$this->_salesforceParentOpportunityField};
        }

        if (empty($_opportunityIds)) {
            return;
        }

        foreach(array_chunk($_opportunityIds, TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true) as $_chunk) {
            try {
                $results = $this->getClient()->retrieve('Id', 'Opportunity', array_values($_chunk));
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Check exist Opportunity to Salesforce failed' . $e->getMessage());
                continue;
            }

            $results = is_array($results)
                ? $results
                : array($results);

            $_opportunityExistIds = array();
            foreach ($results as $_result) {
                if (!$_result instanceof stdClass) {
                    continue;
                }

                $_opportunityExistIds[] = $_result->Id;
                $_opportunityExistIds[] = Mage::helper('tnw_salesforce')->prepareId($_result->Id);
            }

            // Undelete
            $_opportunityIdUndelete = array_diff($_chunk, $_opportunityExistIds);
            if (empty($_opportunityIdUndelete)) {
                continue;
            }

            try {
                $results = $this->getClient()->undelete(array_values($_opportunityIdUndelete));
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Check exist Opportunity to Salesforce failed' . $e->getMessage());
                continue;
            }

            $_keys = array_keys($_opportunityIdUndelete);
            foreach ($results as $_key => $result) {
                if ($result->success) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('INFO: Restoring objects opportunity: ' . $result->id);
                    continue;
                }

                $_entityNumber = $_keys[$_key];
                /** @var Mage_Sales_Model_Order $_entity */
                $_entity = $this->_loadEntityByCache(array_search($_entityNumber, $this->_cache['entitiesUpdating']), $_entityNumber);
                $_entity->setData('opportunity_id', '');
                $_entity->getResource()->save($_entity);

                unset($_toUpsert[$_entityNumber]->{$this->_salesforceParentOpportunityField});
            }
        }
    }

    /**
     * Remaining Data
     */
    protected function _prepareRemaining()
    {
        parent::_prepareRemaining();

        if (Mage::helper('tnw_salesforce')->isOrderRulesEnabled()) {
            $this->_prepareRules();
        }
    }

    protected function _prepareRules()
    {
        $failedKey = sprintf('failed%s', $this->getManyParentEntityType());

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Rules: Start----------');

        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $_key => $_number) {
            if (in_array($_number, $this->_cache[$failedKey])) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('%s (%s): Skipping, issues with upserting an %s!',
                    strtoupper($this->getMagentoEntityName()), $_number, $this->getSalesforceEntityName()));

                continue;
            }

            $_entity   = $this->_loadEntityByCache($_key, $_number);
            foreach ($this->getUserRulesByOrder($_entity) as $campaignId => $item) {
                if (!isset($this->_cache['userRulesToUpsert'][$campaignId])) {
                    $this->_cache['userRulesToUpsert'][$campaignId] = array();
                }

                $this->_cache['userRulesToUpsert'][$campaignId] = array_merge($this->_cache['userRulesToUpsert'][$campaignId], $item);
            }
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Rules: End----------');
    }

    /**
     * @param $entity Mage_Sales_Model_Order
     * @return array
     */
    public function getUserRulesByOrder($entity)
    {
        /** @var Mage_Customer_Model_Customer $_customer */
        $_customer = $this->_getObjectByEntityType($entity, 'Customer');

        $customers = array();
        foreach (array_filter(explode(',', $entity->getAppliedRuleIds())) as $id) {
            $rule       = Mage::getModel('salesrule/rule')->load($id);
            $campaignId = $rule->getData('salesforce_id');
            if (empty($campaignId)) {
                continue;
            }

            $customers[$campaignId][] = $_customer;
        }

        /** @var Mage_Sales_Model_Order_Item $item */
        /*foreach ($entity->getAllVisibleItems() as $item) {
            foreach (array_filter(explode(',', $item->getAppliedRuleIds())) as $id) {
                $rule = Mage::getModel('catalogrule/rule')->load($id);
                $campaignId = $rule->getData('salesforce_id');
                if (empty($campaignId)) {
                    continue;
                }

                $customers[$campaignId][] = $_customer;
            }
        }*/

        return $customers;
    }
}