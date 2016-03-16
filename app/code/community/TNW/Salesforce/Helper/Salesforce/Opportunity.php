<?php

/**
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

    protected function _prepareRemaining()
    {
        parent::_prepareRemaining();

        if (Mage::helper('tnw_salesforce')->isEnabledCustomerRole()) {
            $this->_prepareContactRoles();
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

    /**
     * assign ownerId to opportunity
     *
     * @return bool
     */
    protected function _assignOwnerIdToOpp()
    {
        // assign owner id to opp
        foreach ($this->_cache['opportunitiesToUpsert'] as $_entityNumber => $_opportunityData) {
            $_entity   = $this->_loadEntityByCache(array_search($_entityNumber, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_entityNumber);
            /** @var Mage_Customer_Model_Customer $_customer */
            $_customer = $this->_getObjectByEntityType($_entity, 'Customer');

            $_websiteId = Mage::app()->getStore($_entity->getStoreId())->getWebsiteId();
            $websiteSfId = $this->_websiteSfIds[$_websiteId];

            // Default Owner ID as configured in Magento
            $_opportunityData->OwnerId = Mage::helper('tnw_salesforce')->getDefaultOwner();
            if (
                isset($this->_cache['opportunityLookup'][$_entityNumber])
                && property_exists($this->_cache['opportunityLookup'][$_entityNumber], 'OwnerId')
                && $this->_cache['opportunityLookup'][$_entityNumber]->OwnerId
            ) {
                // Overwrite Owner ID if Opportuinity already exists, use existing owner
                $_opportunityData->OwnerId = $this->_cache['opportunityLookup'][$_entityNumber]->OwnerId;
            } elseif (
                isset($this->_cache['contactsLookup'][$websiteSfId][$_customer->getEmail()])
                && property_exists($this->_cache['contactsLookup'][$websiteSfId][$_customer->getEmail()], 'OwnerId')
                && $this->_cache['contactsLookup'][$websiteSfId][$_customer->getEmail()]->OwnerId
            ) {
                // Overwrite Owner ID, use Owner ID from Contact
                $_opportunityData->OwnerId = $this->_cache['contactsLookup'][$websiteSfId][$_customer->getEmail()]->OwnerId;
            }

            // Reset back if inactive
            if (!$this->_isUserActive($_opportunityData->OwnerId)) {
                $_opportunityData->OwnerId = Mage::helper('tnw_salesforce')->getDefaultOwner();
            }

            $this->_cache['opportunitiesToUpsert'][$_entityNumber] = $_opportunityData;
        }

        return true;
    }

    protected function _pushEntity()
    {
        if (empty($this->_cache['opportunitiesToUpsert'])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('No Opportunities found queued for the synchronization!');
            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Opportunity Push: Start----------');
        foreach (array_values($this->_cache['opportunitiesToUpsert']) as $_opp) {
            foreach ($_opp as $_key => $_value) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Opportunity Object: " . $_key . " = '" . $_value . "'");
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("--------------------------");
        }

        // assign owner id to opportunity
        $this->_assignOwnerIdToOpp();

        $_keys = array_keys($this->_cache['opportunitiesToUpsert']);
        try {
            Mage::dispatchEvent("tnw_salesforce_order_send_before",array(
                "data" => $this->_cache['opportunitiesToUpsert']
            ));

            $results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['opportunitiesToUpsert']), 'Opportunity');
            Mage::dispatchEvent("tnw_salesforce_order_send_after",array(
                "data" => $this->_cache['opportunitiesToUpsert'],
                "result" => $results
            ));
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach($_keys as $_id) {
                $this->_cache['responses']['opportunities'][$_id] = $_response;
            }

            $results = array();
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of an order to Salesforce failed' . $e->getMessage());
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
                    'salesforce_id'         => $_result->id,
                    'sf_insync'             => 1
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
                $this->_mySforceConnection->undelete($_toUndelete);
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

            $websiteId   = $_customer->getWebsiteId()
                ? $_customer->getWebsiteId()
                : $_order->getStore()->getWebsiteId();

            $websiteSfId = $this->_websiteSfIds[$websiteId];
            if (isset($this->_cache['contactsLookup'][$websiteSfId][$_customer->getEmail()])){
                $this->_obj->ContactId = $this->_cache['contactsLookup'][$websiteSfId][$_customer->getEmail()]->Id;
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
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Was not able to convert customer Lead, skipping Opportunity Contact Role assignment. Please synchronize customer (email: ' . $_customer->getEmail() . ')');
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
            $results = $this->_mySforceConnection->upsert("Id", array_values($chunk), 'OpportunityLineItem');
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach($chunk as $_object) {
                $_orderNum = $_orderNumbers[$_object->OpportunityId];
                $this->_cache['responses']['opportunityLineItems'][$_orderNum]['subObj'][] = $_response;
            }

            $results = array();
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of Opportunity Line Items to SalesForce failed' . $e->getMessage());
        }

        foreach ($results as $_key => $_result) {
            $_cartItemId     = $_chunkKeys[$_key];
            $_sOpportunityId = $this->_cache['opportunityLineItemsToUpsert'][$_cartItemId]->OpportunityId;
            $_entityNum      = $_orderNumbers[$_sOpportunityId];
            $_entity         = $this->_loadEntityByCache(array_search($_entityNum, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_entityNum);

            //Report Transaction
            $this->_cache['responses']['opportunityLineItems'][$_entityNum]['subObj'][] = $_result;

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
                    $_item->setData('salesforce_id', $_result->id);
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
                $results = $this->_mySforceConnection->upsert("Id", $this->_cache['contactRolesToUpsert'], 'OpportunityContactRole');
            } catch (Exception $e) {
                $_response = $this->_buildErrorResponse($e->getMessage());
                foreach($this->_cache['contactRolesToUpsert'] as $_object) {
                    $_orderNum = $_orderNumbers[$_object->OpportunityId];
                    $this->_cache['responses']['opportunityCustomerRoles'][$_orderNum]['subObj'][] = $_response;
                }
                $results = array();
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of contact roles to SalesForce failed' . $e->getMessage());
            }

            foreach ($results as $_key => $_result) {
                $_sOpportunityId = $this->_cache['contactRolesToUpsert'][$_key]->OpportunityId;
                $_orderNum = $_orderNumbers[$_sOpportunityId];

                //Report Transaction
                $this->_cache['responses']['opportunityCustomerRoles'][$_orderNum]['subObj'][] = $_result;

                if (!$_result->success) {
                    // Reset sync status
                    $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 0 WHERE salesforce_id = '" . $_sOpportunityId . "';";
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
        // Salesforce lookup, find all orders by Magento order number
        $this->_cache['opportunityLookup'] = Mage::helper('tnw_salesforce/salesforce_data')->opportunityLookup($this->_cache['entitiesUpdating']);
    }

    /**
     * @param $_entity Mage_Sales_Model_Order
     */
    protected function _prepareEntityObjCustom($_entity)
    {
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $this->getCurrencyCode($_entity);
        }

        // Use existing Opportunity if creating from Quote
        $modules = Mage::getConfig()->getNode('modules')->children();
        if (
            property_exists($modules, 'Ophirah_Qquoteadv')
            && (string)$modules->Ophirah_Qquoteadv->active == "true"
            && $_entity->getData('c2q_internal_quote_id')
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Quote Id: " . $_entity->getData('c2q_internal_quote_id'));
            $_quote = Mage::getModel('qquoteadv/qqadvcustomer')->load($_entity->getData('c2q_internal_quote_id'));
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Opportunity Id: " . $_quote->getData('opportunity_id'));
            if ($_quote && $_quote->getData('opportunity_id')) {
                $this->_obj->Id = $_quote->getData('opportunity_id');
                // Delete Products
                $oppItemSetId = array();
                $oppItemSet = Mage::helper('tnw_salesforce/salesforce_data')->getOpportunityItems($this->_obj->Id);
                foreach ($oppItemSet as $item) {
                    $oppItemSetId[] = $item->Id;
                }
                $this->_mySforceConnection->delete($oppItemSetId);
            }
        }
    }

    public function reset()
    {
        parent::reset();

        // get all allowed order statuses from configuration
        $this->_allowedOrderStatuses = explode(',', Mage::helper('tnw_salesforce')->getAllowedOrderStates());

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