<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 * Class TNW_Salesforce_Helper_Salesforce_Opportunity
 *
 * @method Mage_Sales_Model_Quote _loadEntityByCache($_key, $cachePrefix = null)
 */

class TNW_Salesforce_Helper_Salesforce_Abandoned_Opportunity extends TNW_Salesforce_Helper_Salesforce_Opportunity
{
    /**
     * @comment magento entity alias
     * @var string
     */
    protected $_magentoEntityName = 'quote';

    /**
     * @var string
     */
    protected $_mappingEntityName = 'Abandoned';

    /**
     * @var string
     */
    protected $_mappingEntityItemName = 'Abandoneditem';

    /**
     * @comment magento entity model alias
     * @var string
     */
    protected $_magentoEntityModel = 'sales/quote';

    /**
     * @comment magento entity model alias
     * @var string
     */
    protected $_magentoEntityId = 'entity_id';

    /**
     * @comment magento entity item qty field name
     * @var string
     */
    protected $_itemQtyField = 'qty';

    /**
     * @comment salesforce field name to assign parent entity
     * @var string
     */
    protected $_salesforceParentIdField = 'OpportunityId';

    /**
     * @var array
     */
    protected $_availableFees = array();

    /**
     * @param $id
     * @return Mage_Sales_Model_Quote
     */
    protected function _loadEntity($id)
    {
        $stores = Mage::app()->getStores(true);
        $storeIds = array_keys($stores);

        return $this->_modelEntity()
            ->setSharedStoreIds($storeIds)
            ->load($id);
    }

    /**
     * @param $ids
     * Reset Salesforce ID in Magento for the order
     */
    public function resetEntity($ids)
    {
        TNW_Salesforce_Helper_Salesforce_Abstract_Base::resetEntity($ids);
    }

    /**
     * Try to find order in SF and save in local cache
     */
    protected function _prepareOrderLookup()
    {
        $this->_cache['opportunityLookup'] = Mage::helper('tnw_salesforce/salesforce_data')
            ->opportunityLookup($this->_cache['entitiesUpdating']);
    }

    /**
     * Remaining Data
     */
    protected function _prepareRemaining()
    {
        if (Mage::helper('tnw_salesforce')->doPushShoppingCart()) {
            $this->_prepareEntityItems();
        }

        if (Mage::helper('tnw_salesforce/config_sales_abandoned')->isEnabledCustomerRole()) {
            $this->_prepareContactRoles();
        }
    }

    protected function _prepareEntity()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Opportunity Preparation: Start----------');
        $opportunities = array();
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_quoteNumber) {
            $_quote = $this->_loadEntityByCache($_key, $_quoteNumber);

            $this->_obj = new stdClass();
            $this->_setEntityInfo($_quote);
            // Check if Pricebook Id does not match
            if (
                is_array($this->_cache['opportunityLookup'])
                && array_key_exists($_quoteNumber, $this->_cache['opportunityLookup'])
                && property_exists($this->_cache['opportunityLookup'][$_quoteNumber], 'Pricebook2Id')
                && $this->_obj->Pricebook2Id != $this->_cache['opportunityLookup'][$_quoteNumber]->Pricebook2Id
            ) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SKIPPED Quote: " . $_quoteNumber . " - Opportunity uses a different pricebook(" . $this->_cache['opportunityLookup'][$_quoteNumber]->Pricebook2Id . "), please change it in Salesforce.");
                unset($this->_cache['entitiesUpdating'][$_key]);
                unset($this->_cache['quoteToEmail'][$_quoteNumber]);
                $this->_allResults['opportunities_skipped']++;
            } else {
                $this->_cache['opportunitiesToUpsert'][$_quoteNumber] = $this->_obj;
            }

            if (!empty($this->_obj->Id)) {
                $opportunities[] = $this->_obj->Id;
            }
        }

        $this->deleteOpportunityItems($opportunities);
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Opportunity Preparation: End----------');
    }

    /**
     * @param $_entity
     * @return mixed
     * @throws Exception
     */
    protected function _getEntitySalesforceId($_entity)
    {
        return TNW_Salesforce_Helper_Salesforce_Abstract_Base::_getEntitySalesforceId($_entity);
    }

    /**
     * Return false to re-create items
     *
     * @param $parentEntityNumber
     * @param $qty
     * @param $productIdentifier
     * @param string $description
     * @param null $item
     * @return bool
     */
    protected function _doesCartItemExist($parentEntityNumber, $qty, $productIdentifier, $description = 'default', $item = null)
    {
        return false;
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
            Mage::dispatchEvent("tnw_salesforce_opportunity_send_before", array(
                "data" => $this->_cache['opportunitiesToUpsert']
            ));

            $results = $this->getClient()->upsert('Id', array_values($this->_cache['opportunitiesToUpsert']), 'Opportunity');
            Mage::dispatchEvent("tnw_salesforce_opportunity_send_after", array(
                "data" => $this->_cache['opportunitiesToUpsert'],
                "result" => $results
            ));
        } catch (Exception $e) {
            $results = array_fill(0, count($_keys),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of an quote to Salesforce failed' . $e->getMessage());
        }

        $_undeleteIds = array();
        foreach ($results as $_key => $_result) {
            $_quoteNum = $_keys[$_key];

            //Report Transaction
            $this->_cache['responses']['opportunities'][$_quoteNum] = $_result;

            if (!$_result->success) {
                if ($_result->errors[0]->statusCode == "ENTITY_IS_DELETED") {
                    $_undeleteIds[] = $_quoteNum;
                }

                $this->_cache['failedOpportunities'][] = $_quoteNum;

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Opportunity Failed: (quote: ' . $_quoteNum . ')');
                $this->_processErrors($_result, 'quote', $this->_cache['opportunitiesToUpsert'][$_quoteNum]);
            }
            else {
                $_entity   = $this->_loadEntityByCache(array_search($_quoteNum, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_quoteNum);
                $_customer = $this->_getObjectByEntityType($_entity, 'Customer');

                $saveData = array(
                    'sf_sync_force'         => 0,
                    'sf_insync'             => 1,
                    'salesforce_id'         => $_result->id,
                    'contact_salesforce_id' => $_customer->getSalesforceId(),
                    'account_salesforce_id' => $_customer->getSalesforceAccountId()
                );

                $_entity->addData($saveData);

                // Save Attribute
                $fakeEntity = clone $_entity;
                $_entity->getResource()->save($fakeEntity->setData($saveData)->setId($_entity->getId()));

                $this->_cache[sprintf('upserted%s', $this->getManyParentEntityType())][$_quoteNum] = $_result->id;
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Opportunity Upserted: ' . $_result->id);
            }
        }

        do {
            if (empty($_undeleteIds)) {
                break;
            }

            $_deleted = Mage::helper('tnw_salesforce/salesforce_data')->opportunityLookup($_undeleteIds);
            if (empty($_deleted)) {
                break;
            }

            $_toUndelete = array();
            foreach ($_deleted as $_object) {
                $_toUndelete[] = $_object->Id;
            }

            $this->getClient()->undelete($_toUndelete);
        } while(false);

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Opportunity Push: End----------');
    }

    /**
     * @param $entityItem Mage_Sales_Model_Quote_Item
     * @param $fieldName
     * @return null
     */
    public function getFieldFromEntityItem($entityItem, $fieldName)
    {
        $field = null;
        switch ($entityItem->getProductType()) {
            case Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE:
                $children = $entityItem->getChildren();
                if (empty($children)) {
                    $productId = null;
                    break;
                }

                $field = reset($children)->getData($fieldName);
                break;

            default:
                $field = $entityItem->getData($fieldName);
                break;
        }

        return $field;
    }

    /**
     * @return array
     */
    protected function getUpsertedEntityIds()
    {
        $entityIds = array();
        foreach ($this->_cache['entitiesUpdating'] as $key => $quoteId) {
            if (!in_array($quoteId, $this->_cache['failedOpportunities'])) {
                $entityIds[$key] = $quoteId;
            }
        }

        return $entityIds;
    }

    protected function _prepareContactRoles()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Opportunity Contact Role: Start----------');
        foreach ($this->getUpsertedEntityIds() as $key => $quoteNumber) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('******** QUOTE (' . $quoteNumber . ') ********');

            $quote    = $this->_loadEntityByCache($key, $quoteNumber);
            /** @var Mage_Customer_Model_Customer $customer */
            $customer = $this->_getObjectByEntityType($quote, 'Customer');
            $customerEmail = strtolower($customer->getEmail());

            $contactRole = new stdClass();

            $websiteId   = $customer->getWebsiteId()
                ? $customer->getWebsiteId()
                : $quote->getStore()->getWebsiteId();

            $websiteSfId = $this->_websiteSfIds[$websiteId];
            if (isset($this->_cache['contactsLookup'][$websiteSfId][$customerEmail])){
                $contactRole->ContactId = $this->_cache['contactsLookup'][$websiteSfId][$customerEmail]->Id;
            }

            if ($customer->getData('salesforce_id')) {
                $contactRole->ContactId = $customer->getData('salesforce_id');
            }

            // Check if already exists
            $skip = false;
            $defaultCustomerRole = Mage::helper('tnw_salesforce/config_sales_abandoned')->getDefaultCustomerRole();

            if (isset($this->_cache['opportunityLookup'][$quoteNumber])
                && $this->_cache['opportunityLookup'][$quoteNumber]->OpportunityContactRoles
            ) {
                $opportunity = $this->_cache['opportunityLookup'][$quoteNumber];
                foreach ($opportunity->OpportunityContactRoles->records as $role) {
                    if ($role->ContactId == $contactRole->ContactId) {
                        if ($role->Role == $defaultCustomerRole) {
                            // No update required
                            Mage::getSingleton('tnw_salesforce/tool_log')
                                ->saveTrace('Contact Role information is the same, no update required!');
                            $skip = true;
                            break;
                        }

                        $contactRole->Id = $role->Id;
                        $contactRole->ContactId = $role->ContactId;
                        break;
                    }
                }
            }

            if (!$skip) {
                $contactRole->IsPrimary = true;
                $contactRole->OpportunityId = $this->_cache['upserted' . $this->getManyParentEntityType()][$quoteNumber];
                $contactRole->Role = $defaultCustomerRole;

                foreach ($contactRole as $key => $value) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(
                        sprintf('OpportunityContactRole Object: %s = \'%s\'', $key, $value));
                }

                if ($contactRole->ContactId) {
                    $this->_cache['contactRolesToUpsert'][] = $contactRole;
                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Was not able to convert customer Lead, '
                        . 'skipping Opportunity Contact Role assignment. '
                        . 'Please synchronize customer (email: ' . $customerEmail . ')');
                }
            }
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Opportunity Contact Role: End----------');
    }

    protected function _pushEntityItems($chunk = array())
    {
        if (empty($this->_cache['upserted'.$this->getManyParentEntityType()])) {
            return;
        }

        $_quoteNumbers = array_flip($this->_cache['upserted'.$this->getManyParentEntityType()]);
        $_quoteCartNumbers = array_keys($chunk);
        try {
            $results = $this->getClient()->upsert("Id", array_values($chunk), 'OpportunityLineItem');
        } catch (Exception $e) {
            $results = array_fill(0, count($chunk),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of Opportunity Line Items to SalesForce failed' . $e->getMessage());
        }

        foreach ($results as $_key => $_result) {
            $_cartItemId = $_quoteCartNumbers[$_key];
            $_quoteNum   = $_quoteNumbers[$this->_cache['opportunityLineItemsToUpsert'][$_cartItemId]->OpportunityId];
            $_entity     = $this->_loadEntityByCache(array_search($_quoteNum, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_quoteNum);

            //Report Transaction
            $this->_cache['responses']['opportunityLineItems'][$_quoteNum]['subObj'][] = $_result;

            if (!$_result->success) {
                foreach ($_result->errors as $_error) {
                    if ($_error->statusCode == 'FIELD_INTEGRITY_EXCEPTION'
                        && $_error->message == 'field integrity exception: PricebookEntryId (pricebook entry has been archived)'
                    ) {
                        Mage::getSingleton('adminhtml/session')
                            ->addWarning('A product in Quote #'
                                . $_quoteNum
                                . ' have not been synchronized. Pricebook entry has been archived.'
                            );
                        continue 2;
                    }
                }

                // Reset sync status
                $saveData = array('sf_insync' => 0);
                $_entity->addData($saveData);

                // Save Attribute
                $fakeEntity = clone $_entity;
                $_entity->getResource()->save($fakeEntity->setData($saveData)->setId($_entity->getId()));

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR: One of the Cart Item for (quote: ' . $_quoteNum . ') failed to upsert.');
                $this->_processErrors($_result, 'quoteCart', $chunk[$_quoteCartNumbers[$_key]]);
            }
            else {
                $_item = $_entity->getItemsCollection()->getItemById(str_replace('cart_', '', $_cartItemId));
                if ($_item instanceof Mage_Core_Model_Abstract) {
                    $saveData = array(
                        'salesforce_id' => $_result->id
                    );

                    $_item->addData($saveData);

                    // Save Attribute
                    $fakeItem = clone $_item;
                    $_item->getResource()->save($fakeItem->setData($saveData)->setId($_item->getId()));
                }

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Cart Item (id: ' . $_result->id . ') for (quote: ' . $_quoteNum . ') upserted.');
            }
        }
    }

    protected function _pushRemainingCustomEntityData()
    {
        if (!empty($this->_cache['contactRolesToUpsert'])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Contact Roles: Start----------');
            $_quoteNumbers = array_flip($this->_cache  ['upserted' . $this->getManyParentEntityType()]);
            // Push Contact Roles
            try {
                $results = $this->getClient()->upsert("Id", $this->_cache['contactRolesToUpsert'], 'OpportunityContactRole');
            } catch (Exception $e) {
                $results = array_fill(0, count($this->_cache['contactRolesToUpsert']),
                    $this->_buildErrorResponse($e->getMessage()));

                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of contact roles to SalesForce failed' . $e->getMessage());
            }

            foreach ($results as $_key => $_result) {
                $_quoteNum = $_quoteNumbers[$this->_cache['contactRolesToUpsert'][$_key]->OpportunityId];

                //Report Transaction
                $this->_cache['responses']['opportunityCustomerRoles'][$_quoteNum]['subObj'][] = $_result;

                if (!(int)$_result->success) {
                    // Reset sync status
                    $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_quote') . "` SET sf_sync_force = 0, sf_insync = 0, created_at = created_at WHERE salesforce_id = '" . $this->_cache['contactRolesToUpsert'][$_key]->OpportunityId . "';";
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                    Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Contact Role (role: ' . $this->_cache['contactRolesToUpsert'][$_key]->Role . ') for (quote: ' . $_quoteNum . ') failed to upsert.');
                    $this->_processErrors($_result, 'quoteCart', $this->_cache['contactRolesToUpsert'][$_key]);
                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Contact Role (role: ' . $this->_cache['contactRolesToUpsert'][$_key]->Role . ') for (quote: ' . $_quoteNum . ') upserted.');
                }
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Contact Roles: End----------');
        }

    }

    /**
     * @param $_entityNumber
     * @return bool
     */
    protected function _checkSyncCustomer($_entityNumber)
    {
        return TNW_Salesforce_Helper_Salesforce_Abstract_Sales::_checkSyncCustomer($_entityNumber);
    }

    /**
     * @param $_entity Mage_Sales_Model_Quote
     * @param $key
     */
    protected function _prepareEntityObjCustom($_entity, $key)
    {
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $_entity->getData('quote_currency_code');
        }
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
            'quoteCustomers' => array(),
            'toSaveInMagento' => array(),
            'contactsLookup' => array(),
            'failedOpportunities' => array(),
            'quoteToEmail' => array(),
            'convertedLeads' => array(),
            'quoteToCustomerId' => array(),
            'responses' => array(
                'leadsToConvert' => array(),
                'opportunities' => array(),
                'opportunityLineItems' => array(),
                'opportunityCustomerRoles' => array()
            ),
            'quoteCustomersToSync' => array(),
            'leadsFaildToConvert' => array()
        );

        return $this->check();
    }

    /**
     * @param $_entity Mage_Sales_Model_Quote
     * @return bool
     * @throws Exception
     */
    protected function _checkMassAddEntity($_entity)
    {
        $_entityNumber = $this->_getEntityNumber($_entity);

        // Quote could not be loaded for some reason
        if (count($_entity->getAllItems()) == 0) {
            $this->logNotice('SKIPPING: Abandoned cart #' . $_entityNumber . ' is empty!');
            return false;
        }

        // Get Magento customer object
        $customer = $this->_generateCustomerByOrder($_entity);

        // Associate quote Number with a customer ID
        $_customerId = ($customer->getId())
            ? $customer->getId() : sprintf('guest_%d', $this->_guestCount++);

        $customer->setId($_customerId);

        // Store quote number and customer Email into a variable for future use
        $_quoteEmail = strtolower($customer->getEmail());
        if (empty($_quoteEmail)) {
            $this->logNotice('SKIPPED: Sync for quote #' . $_entityNumber . ' failed, quote is missing an email address!');
            return false;
        }

        $this->_cache['quoteCustomers'][$_entityNumber] = $customer;
        $this->_cache['quoteToCustomerId'][$_entityNumber] = $_customerId;
        $this->_cache['quoteToEmail'][$_entityNumber] = $_quoteEmail;

        // Check if customer from this group is allowed to be synchronized
        $_customerGroup = $_entity->getData('customer_group_id');
        if ($_customerGroup === NULL) {
            $_customerGroup = $customer->getGroupId();
        }

        if ($_customerGroup === NULL && !$this->isFromCLI()) {
            $_customerGroup = Mage::getSingleton('customer/session')->getCustomerGroupId();
        }

        if (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($_customerGroup)) {
            $this->logNotice("SKIPPING: Sync for customer group #" . $_customerGroup . " is disabled!");
            return false;
        }

        $_websiteId = Mage::app()->getStore($_entity->getData('store_id'))->getWebsiteId();
        $this->_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];
        $this->_emails[$_customerId] = $_quoteEmail;
        $this->_quotes[$_entity->getId()] = $_entityNumber;

        return true;
    }

    /**
     * @param $_entity Mage_Sales_Model_Quote
     * @return mixed
     */
    protected function _getEntityNumber($_entity)
    {
        return TNW_Salesforce_Helper_Config_Sales_Abandoned::ABANDONED_CART_ID_PREFIX . $_entity->getId();
    }

    /**
     * @param $_entity Mage_Sales_Model_Quote
     * @param $type string
     * @return mixed
     */
    protected function _getObjectByEntityType($_entity, $type)
    {
        switch($type)
        {
            case 'Cart':
                $_object = $_entity;
                break;

            default:
                $_object = parent::_getObjectByEntityType($_entity, $type);
                break;
        }

        return $_object;
    }

    /**
     * @param $_entityItem Mage_Sales_Model_Quote_Item
     * @param $_type
     * @return null
     */
    protected function _getObjectByEntityItemType($_entityItem, $_type)
    {
        switch($_type)
        {
            case 'Cart':
                $_object = $this->getEntityByItem($_entityItem);
                break;

            case 'Cart Item':
                $_object = $_entityItem;
                break;

            default:
                $_object = parent::_getObjectByEntityItemType($_entityItem, $_type);
                break;
        }

        return $_object;
    }

    /**
     * Return parent entity items and bundle items
     *
     * @param $parentEntity Mage_Sales_Model_Order
     * @return mixed
     */
    public function getItems($parentEntity)
    {
        $_items = array();

        /** @var Mage_Sales_Model_Quote_Item $_item */
        foreach ($parentEntity->getAllVisibleItems() as $_item) {

            if ($_item->getProductType() != Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                $_items[] = $_item;
                continue;
            }

            switch (Mage::getStoreConfig(TNW_Salesforce_Helper_Config_Sales::XML_PATH_ORDERS_BUNDLE_ITEM_SYNC)) {
                case 0:
                    $_items[] = $_item;
                    break;

                case 1:
                    $_items[] = $_item;

                    /** @var Mage_Sales_Model_Quote_Item $_childItem */
                    foreach ($_item->getChildren() as $_childItem) {
                        $_childItem
                            ->setTaxAmount(null)
                            ->setBaseTaxAmount(null)
                            ->setHiddenTaxAmount(null)
                            ->setBaseHiddenTaxAmount(null)
                            ->setRowTotal(null)
                            ->setBaseRowTotal(null)
                            ->setDiscountAmount(null)
                            ->setBaseDiscountAmount(null)
                            ->setQty($_childItem->getOrigData('qty') * $_item->getQty())
                            ->setBundleItemToSync(TNW_Salesforce_Helper_Config_Sales::BUNDLE_ITEM_MARKER . $_item->getSku())
                        ;

                        $_items[] = $_childItem;
                    }
                    break;

                case 2:
                    /** @var Mage_Sales_Model_Quote_Item $_childItem */
                    foreach ($_item->getChildren() as $_childItem) {
                        $_childItem
                            ->setQty($_childItem->getOrigData('qty') * $_item->getQty())
                            ->setBundleItemToSync(TNW_Salesforce_Helper_Config_Sales::BUNDLE_ITEM_MARKER . $_item->getSku())
                        ;

                        $_items[] = $_childItem;
                    }
                    break;
            }
        }

        return $_items;
    }
}