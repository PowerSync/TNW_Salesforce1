<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 * Class TNW_Salesforce_Helper_Salesforce_Opportunity
 *
 * @method Mage_Sales_Model_Order _loadEntityByCache($_key, $cachePrefix = null)
 */

class TNW_Salesforce_Helper_Salesforce_Opportunity extends TNW_Salesforce_Helper_Salesforce_Abstract_Order
{

    /**
     * @comment magento entity alias
     * @var array
     */
    protected $_magentoEntityName = 'order';

    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = 'opportunity';

    /**
     * @var string
     */
    protected $_mappingEntityName = 'Opportunity';

    /**
     * @var string
     */
    protected $_mappingEntityItemName = 'OpportunityLineItem';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityModel = 'sales/order';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityId = 'increment_id';

    /**
     * @comment magento entity item qty field name
     * @var array
     */
    protected $_itemQtyField = 'qty_ordered';

    /**
     * @comment salesforce field name to assign parent entity
     * @var string
     */
    protected $_salesforceParentIdField = 'OpportunityId';

    /**
     * @var null
     */
    protected $_customerEntityTypeCode = NULL;

    /**
     * @var null
     */
    protected $_standardPricebookId = NULL;

    /**
     * @var null
     */
    protected $_defaultPriceBook = NULL;

    /**
     * @var bool
     */
    protected $_isCron = false;

    /**
     * @var array
     */
    protected $_allResults = array(
        'opportunities_skipped' => 0,
    );

    /**
     * @param $ids
     * Reset Salesforce ID in Magento for the order
     */
    public function resetEntity($ids)
    {
        if (empty($ids)) {
            return;
        }

        $ids = !is_array($ids)
            ? array($ids) : $ids;

        $resource    = $this->_modelEntity()->getResource();
        $mainTable   = $resource->getMainTable();
        $idFieldName = $resource->getIdFieldName();
        $sql = "UPDATE `$mainTable` SET opportunity_id = NULL, sf_insync = 0 WHERE $idFieldName IN (" . join(',', $ids) . ");";
        Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf("%s ID and Sync Status for %s (#%s) were reset.",
                $this->_magentoEntityName, $this->_magentoEntityName, join(',', $ids)));
    }

    protected function _doesCartItemExist($parentEntityNumber, $qty, $productIdentifier, $description = 'default', $item = null)
    {
        $parentEntityCacheKey = sprintf('%sLookup', $this->_salesforceEntityName);
        if (empty($this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$this->getItemsField()}->records)) {
            return null;
        }

        foreach ($this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$this->getItemsField()}->records as $_cartItem) {
            if ($_cartItem->Id != $item->getData('opportunity_id')) {
                continue;
            }

            return $_cartItem->Id;
        }

        return null;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @param $item Mage_Sales_Model_Order_Item
     */
    protected function _prepareAdditionalFees($_entity, $item)
    {
        $_salesforceId        = null;
        $_entityNumber        = $this->_getEntityNumber($_entity);
        $parentEntityCacheKey = sprintf('%sLookup', $this->_salesforceEntityName);
        $productSalesforceId  = $this->_getObjectByEntityItemType($item, 'Product')->getData('salesforce_id');

        $records              = !empty($this->_cache[$parentEntityCacheKey][$_entityNumber]->{$this->getItemsField()})
            ? $this->_cache[$parentEntityCacheKey][$_entityNumber]->{$this->getItemsField()}->records : array();

        foreach ($records as $_cartItem) {
            if ($_cartItem->PricebookEntry->Product2Id != $productSalesforceId) {
                continue;
            }

            $_salesforceId = $_cartItem->Id;
            break;
        }

        $item->setData('opportunity_id', $_salesforceId);
    }

    /**
     * @param Mage_Sales_Model_Order $_entity
     * @return bool
     */
    protected function _checkMassAddEntity($_entity)
    {
        $salesConfig = Mage::helper('tnw_salesforce/config_sales');
        if ($salesConfig->showOrderId() && $_entity->getData('opportunity_id') && !$salesConfig->orderSyncAllowed($_entity)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("Order #{$_entity->getIncrementId()}, paid. Skipped sync Salesforce Opportunity");

            return false;
        }

        return parent::_checkMassAddEntity($_entity);
    }

    protected function _prepareRemaining()
    {
        $_lookupKey = sprintf('%sLookup', $this->_salesforceEntityName);
        $opportunityIds = array();
        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $entityNumber) {
            if (empty($this->_cache[$_lookupKey][$entityNumber])) {
                continue;
            }

            if (empty($this->_cache[$_lookupKey][$entityNumber]->MagentoId)) {
                continue;
            }

            if ($this->_cache[$_lookupKey][$entityNumber]->MagentoId == $entityNumber) {
                continue;
            }

            $opportunityIds[] = $this->_cache[$_lookupKey][$entityNumber]->Id;
        }

        $this->deleteOpportunityItems($opportunityIds);

        parent::_prepareRemaining();

        if (Mage::helper('tnw_salesforce')->isEnabledCustomerRole()) {
            $this->_prepareContactRoles();
        }
    }

    /**
     * @param array $opportunity
     */
    public function deleteOpportunityItems(array $opportunity)
    {
        if (empty($opportunity)) {
            return;
        }

        $oppItemSet = Mage::helper('tnw_salesforce/salesforce_data')->getOpportunityItems($opportunity);
        if (empty($oppItemSet)) {
            return;
        }

        $oppItemSetId = array();
        foreach ($oppItemSet as $item) {
            $oppItemSetId[] = $item->Id;
        }

        foreach (array_chunk($oppItemSetId, TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT) as $oppItemSetId) {
            $this->getClient()->delete($oppItemSetId);
        }
    }

    protected function _onComplete()
    {
        parent::_onComplete();

        if (Mage::helper('tnw_salesforce')->isRemoteLogEnabled()) {
            $logger = Mage::helper('tnw_salesforce/report');
            $logger->reset();

            $logger->add('Salesforce', 'Opportunity', $this->_cache['opportunitiesToUpsert'], $this->_cache['responses']['opportunities']);
            $logger->add('Salesforce', 'OpportunityLineItem', $this->_cache['opportunityLineItemsToUpsert'], $this->_cache['responses']['opportunityLineItems']);
            $logger->add('Salesforce', 'OpportunityContactRole', $this->_cache['contactRolesToUpsert'], $this->_cache['responses']['opportunityCustomerRoles']);

            $logger->send();
        }

        // Logout
        $this->reset();
        $this->clearMemory();
    }

    protected function _pushEntity()
    {
        if (empty($this->_cache['opportunitiesToUpsert'])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('No Opportunities found queued for the synchronization!');
            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Opportunity Push: Start----------');
        $_keys = array_keys($this->_cache['opportunitiesToUpsert']);
        try {
            Mage::dispatchEvent('tnw_salesforce_opportunity_send_before',array(
                'data' => $this->_cache['opportunitiesToUpsert']
            ));

            $results = $this->getClient()->upsert('Id', array_values($this->_cache['opportunitiesToUpsert']), 'Opportunity');
            Mage::dispatchEvent('tnw_salesforce_opportunity_send_after',array(
                'data' => $this->_cache['opportunitiesToUpsert'],
                'result' => $results
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
            $this->_cache['responses']['opportunities'][$_orderNum] = $_result;

            if (!$_result->success) {
                if ($_result->errors[0]->statusCode == "ENTITY_IS_DELETED") {
                    $_undeleteIds[] = $_orderNum;
                }

                $this->_cache['failedOpportunities'][] = $_orderNum;

                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Opportunity Failed: (order: ' . $_orderNum . ')');
                $this->_processErrors($_result, 'order', $this->_cache['opportunitiesToUpsert'][$_orderNum]);
            } else {
                $_order    = $this->_loadEntityByCache(array_search($_orderNum, $this->_cache['entitiesUpdating']), $_orderNum);
                $_customer = $this->_getObjectByEntityType($_order, 'Customer');

                $_order->addData(array(
                    'contact_salesforce_id' => $_customer->getData('salesforce_id'),
                    'account_salesforce_id' => $_customer->getData('salesforce_account_id'),
                    'opportunity_id'        => $_result->id,
                    'sf_insync'             => 1,
                    'owner_salesforce_id'   => $this->_cache['opportunitiesToUpsert'][$_orderNum]->OwnerId
                ));
                $_order->getResource()->save($_order);

                $this->_cache[sprintf('upserted%s',$this->getManyParentEntityType())][$_orderNum] = $_result->id;
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Opportunity Upserted: ' . $_result->id);
            }
        }

        if (!empty($_undeleteIds)) {
            $_deleted = Mage::helper('tnw_salesforce/salesforce_data')->opportunityLookup($_undeleteIds);
            $_toUndelete = array();
            foreach ($_deleted as $_object) {
                $_toUndelete[] = $_object->Id;
            }
            if (!empty($_toUndelete)) {
                $this->getClient()->undelete($_toUndelete);
            }
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Opportunity Push: End----------');
    }

    protected function _prepareContactRoles()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Opportunity Contact Role: Start----------');
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (in_array($_orderNumber, $this->_cache['failedOpportunities'])) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('ORDER (' . $_orderNumber . '): Skipping, issues with upserting an opportunity!');
                continue;
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('******** ORDER (' . $_orderNumber . ') ********');

            $this->_obj = new stdClass();

            /** @var Mage_Sales_Model_Order $_order */
            $_order      = $this->_loadEntityByCache($_key, $_orderNumber);

            /** @var Mage_Customer_Model_Customer $_customer */
            $_customer   = $this->_getObjectByEntityType($_order, 'Customer');
            $customerEmail = strtolower($_customer->getEmail());

            $websiteId   = $_customer->getWebsiteId()
                ? $_customer->getWebsiteId()
                : $_order->getStore()->getWebsiteId();

            $websiteSfId = $this->_websiteSfIds[$websiteId];
            if (isset($this->_cache['contactsLookup'][$websiteSfId][$customerEmail])){
                $this->_obj->ContactId = $this->_cache['contactsLookup'][$websiteSfId][$customerEmail]->Id;
            }

            if ($_customer->getData('salesforce_id')) {
                $this->_obj->ContactId = $_customer->getData('salesforce_id');
            }

            // Check if already exists
            $_skip = false;
            if (isset($this->_cache['opportunityLookup'][$_orderNumber]) && $this->_cache['opportunityLookup'][$_orderNumber]->OpportunityContactRoles) {
                foreach ($this->_cache['opportunityLookup'][$_orderNumber]->OpportunityContactRoles->records as $_role) {
                    if (property_exists($this->_obj, 'ContactId') && property_exists($_role, 'ContactId') && $_role->ContactId == $this->_obj->ContactId) {
                        if ($_role->Role == Mage::helper('tnw_salesforce')->getDefaultCustomerRole()) {
                            // No update required
                            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Contact Role information is the same, no update required!');
                            $_skip = true;
                            break;
                        }

                        $this->_obj->Id = $_role->Id;
                        $this->_obj->ContactId = $_role->ContactId;
                        break;
                    }
                }
            }

            if (!$_skip) {
                $this->_obj->IsPrimary = true;
                $this->_obj->OpportunityId = $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_orderNumber];

                $this->_obj->Role = Mage::helper('tnw_salesforce')->getDefaultCustomerRole();

                foreach ($this->_obj as $key => $_item) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("OpportunityContactRole Object: " . $key . " = '" . $_item . "'");
                }

                if ($this->_obj->ContactId) {
                    $this->_cache['contactRolesToUpsert'][] = $this->_obj;
                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Was not able to convert customer Lead, skipping Opportunity Contact Role assignment. Please synchronize customer (email: ' . $customerEmail . ')');
                }
            }
        }
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Opportunity Contact Role: End----------');
    }

    protected function _pushEntityItems($chunk = array())
    {
        $_orderNumbers = array_flip($this->_cache  ['upserted' . $this->getManyParentEntityType()]);
        $_chunkKeys = array_keys($chunk);
        try {
            $results = $this->getClient()->upsert("Id", array_values($chunk), 'OpportunityLineItem');
        } catch (Exception $e) {
            $results = array_fill(0, count($chunk),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of Opportunity Line Items to SalesForce failed' . $e->getMessage());
        }

        foreach ($results as $_key => $_result) {
            $_cartItemId     = $_chunkKeys[$_key];
            $_sOpportunityId = $this->_cache['opportunityLineItemsToUpsert'][$_cartItemId]->OpportunityId;
            $_entityNum      = $_orderNumbers[$_sOpportunityId];
            $_entity         = $this->_loadEntityByCache(array_search($_entityNum, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_entityNum);

            //Report Transaction
            $this->_cache['responses']['opportunityLineItems'][$_entityNum]['subObj'][$_cartItemId] = $_result;

            if (!$_result->success) {
                // Hide errors when product has been archived
                foreach ($_result->errors as $_error) {
                    if ($_error->statusCode == 'FIELD_INTEGRITY_EXCEPTION'
                        && $_error->message == 'field integrity exception: PricebookEntryId (pricebook entry has been archived)'
                    ) {
                        Mage::getSingleton('adminhtml/session')
                            ->addWarning('A product in Order #'
                                . $_entityNum
                                . ' have not been synchronized. Pricebook entry has been archived.'
                            );
                        continue 2;
                    }
                }

                // Reset sync status
                $_entity->setData('sf_insync', 0);
                $_entity->getResource()->save($_entity);

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR: One of the Cart Item for (order: ' . $_entityNum . ') failed to upsert.');
                $this->_processErrors($_result, 'orderCart', $chunk[$_cartItemId]);
            } else {
                $_item = $_entity->getItemsCollection()->getItemById(str_replace('cart_', '', $_cartItemId));
                if ($_item instanceof Mage_Core_Model_Abstract) {
                    $_item->setData('opportunity_id', $_result->id);
                    $_item->getResource()->save($_item);
                }

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Cart Item (id: ' . $_result->id . ') for (order: ' . $_entityNum . ') upserted.');
            }
        }
    }

    protected function _pushRemainingCustomEntityData()
    {
        parent::_pushRemainingCustomEntityData();

        if (!empty($this->_cache['contactRolesToUpsert'])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Contact Roles: Start----------');

            $_orderNumbers = array_flip($this->_cache  ['upserted' . $this->getManyParentEntityType()]);

            Mage::dispatchEvent("tnw_salesforce_opportunity_contact_roles_send_before", array("data" => $this->_cache['contactRolesToUpsert']));

            // Push Contact Roles
            try {
                $results = $this->getClient()->upsert("Id", $this->_cache['contactRolesToUpsert'], 'OpportunityContactRole');
            } catch (Exception $e) {
                $results = array_fill(0, count($this->_cache['contactRolesToUpsert']),
                    $this->_buildErrorResponse($e->getMessage()));

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('CRITICAL: Push of contact roles to SalesForce failed' . $e->getMessage());
            }

            foreach ($results as $_key => $_result) {
                $_sOpportunityId = $this->_cache['contactRolesToUpsert'][$_key]->OpportunityId;
                $_orderNum = $_orderNumbers[$_sOpportunityId];

                //Report Transaction
                $this->_cache['responses']['opportunityCustomerRoles'][$_orderNum]['subObj'][] = $_result;

                if (!$_result->success) {
                    // Reset sync status
                    $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 0 WHERE opportunity_id = '" . $_sOpportunityId . "';";
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                    Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Contact Role (role: ' . $this->_cache['contactRolesToUpsert'][$_key]->Role . ') for (order: ' . $_orderNum . ') failed to upsert.');
                    $this->_processErrors($_result, 'orderCart', $this->_cache['contactRolesToUpsert'][$_key]);
                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Contact Role (role: ' . $this->_cache['contactRolesToUpsert'][$_key]->Role . ') for (order: ' . $_orderNum . ') upserted.');
                }
            }

            Mage::dispatchEvent("tnw_salesforce_opportunity_contact_roles_send_after",array(
                "data" => $this->_cache['contactRolesToUpsert'],
                "result" => $this->_cache['responses']['opportunityCustomerRoles']
            ));

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Contact Roles: End----------');
        }
    }

    /**
     * @comment This method is not necessary for Opportunity
     * @param $quotes
     */
    protected function _findAbandonedCart($quotes)
    {
        return;
    }

    /**
     * Try to find order in SF and save in local cache
     */
    protected function _prepareOrderLookup()
    {
        $orders = array();
        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $entityNumber) {
            $orders[] = $this->getEntityCache($entityNumber);
        }

        // Salesforce lookup, find all orders by Magento order number
        $this->_cache['opportunityLookup'] = Mage::helper('tnw_salesforce/salesforce_data_opportunity')
            ->lookup($orders);
    }

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @param $key
     */
    protected function _prepareEntityObjCustom($_entity, $key)
    {
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $this->getCurrencyCode($_entity);
        }
    }

    /**
     * @param Mage_Sales_Model_Order_Item $_entityItem
     */
    protected function _prepareEntityItemObjCustom($_entityItem)
    {
        if (Mage::helper('tnw_salesforce')->isProfessionalEdition()) {
            $disableSyncField = Mage::helper('tnw_salesforce/config')->getDisableSyncField();
            $this->_obj->$disableSyncField = true;
        }

        parent::_prepareEntityItemObjCustom($_entityItem);
    }

    /**
     * @param $notes Mage_Sales_Model_Order_Status_History
     * @return mixed
     */
    protected function _getNotesParentSalesforceId($notes)
    {
        return $notes->getOrder()->getData('opportunity_id');
    }

    /**
     * @return string
     */
    protected function _notesTableFieldName()
    {
        return 'opportunity_id';
    }

    public function reset()
    {
        parent::reset();

        // Reset cache (need to conver to magento cache
        $this->_cache = array(
            'upsertedOpportunities' => array(),
            'accountsLookup' => array(),
            'entitiesUpdating' => array(),
            'opportunityLookup' => array(),
            'opportunitiesToUpsert' => array(),
            'opportunityLineItemsToUpsert' => array(),
            'opportunityLineItemsToDelete' => array(),
            'notesToUpsert' => array(),
            'contactRolesToUpsert' => array(),
            'leadsToConvert' => array(),
            'leadLookup' => array(),
            'orderCustomers' => array(),
            'toSaveInMagento' => array(),
            'contactsLookup' => array(),
            'failedOpportunities' => array(),
            'orderToEmail' => array(),
            'convertedLeads' => array(),
            'orderToCustomerId' => array(),
            'responses' => array(
                'leadsToConvert' => array(),
                'opportunities' => array(),
                'opportunityLineItems' => array(),
                'opportunityCustomerRoles' => array()
            ),
            'orderCustomersToSync' => array(),
            'leadsFaildToConvert' => array()
        );

        return $this->check();
    }
}