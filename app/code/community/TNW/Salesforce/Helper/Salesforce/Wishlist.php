<?php

/**
 * @method Mage_Wishlist_Model_Wishlist getEntityCache($cachePrefix)
 */
class TNW_Salesforce_Helper_Salesforce_Wishlist extends TNW_Salesforce_Helper_Salesforce_Abstract_Sales
{
    const SALESFORCE_ENTITY_PREFIX = 'wish_';

    /**
     * @comment magento entity alias "convert from"
     * @var string
     */
    protected $_magentoEntityName = 'wishlist';

    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = 'opportunity';

    /**
     * @var string
     */
    protected $_mappingEntityName = 'WishlistOpportunity';

    /**
     * @var string
     */
    protected $_mappingEntityItemName = 'WishlistOpportunityLine';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityModel = 'wishlist/wishlist';

    /**
     * @comment salesforce field name to assign parent entity
     * @var string
     */
    protected $_salesforceParentIdField = 'OpportunityId';

    /**
     * @param $_entity Mage_Wishlist_Model_Wishlist
     * @return bool
     * @throws Exception
     */
    protected function _checkMassAddEntity($_entity)
    {
        $entityNumber = $this->_getEntityNumber($_entity);

        $entityItems = $this->getItems($_entity);
        if (!count($entityItems)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice('SKIPPING: Wishlist #'.$entityNumber.' is empty!');

            return false;
        }

        $customer = $this->_generateCustomer(array(
            'customer_id' => $_entity->getCustomerId(),
        ));

        $this->_cache[sprintf('%sCustomers', $this->_magentoEntityName)][$entityNumber] = $customer;
        $this->_cache[sprintf('%sToCustomerId', $this->_magentoEntityName)][$entityNumber] = $customer->getId();
        $this->_cache[sprintf('%sToEmail', $this->_magentoEntityName)][$entityNumber] = $customer->getData('email');

        // Check if customer from this group is allowed to be synchronized
        if (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($customer->getGroupId())) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice("SKIPPING: Sync for customer group #{$customer->getGroupId()} is disabled!");

            return false;
        }

        $this->_websites[$customer->getId()] = $this->_websiteSfIds[$customer->getStore()->getWebsiteId()];
        $this->_emails[$customer->getId()] = $customer->getData('email');

        return true;
    }

    /**
     * @param $_entity Mage_Wishlist_Model_Wishlist
     * @return mixed
     */
    protected function _getEntityNumber($_entity)
    {
        return self::SALESFORCE_ENTITY_PREFIX . $_entity->getId();
    }

    /**
     * @param $entityItem Mage_Wishlist_Model_Item
     * @return Mage_Wishlist_Model_Wishlist
     */
    public function getEntityByItem($entityItem)
    {
        return $this->getEntityCache(self::SALESFORCE_ENTITY_PREFIX . $entityItem->getWishlistId());
    }

    /**
     *
     */
    protected function _massAddAfterLookup()
    {
        $this->_cache[sprintf('%sLookup', $this->_salesforceEntityName)] = Mage::helper('tnw_salesforce/salesforce_data')
            ->opportunityLookup($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);
    }

    /**
     * @comment return entity items
     * @param $_entity Mage_Wishlist_Model_Wishlist
     * @return Mage_Wishlist_Model_Item[]
     */
    public function getItems($_entity)
    {
        $entityNumber = $this->_getEntityNumber($_entity);
        if (!isset($this->_cache['entityItems'][$entityNumber])) {
            /** @var Mage_Wishlist_Model_Resource_Item_Collection $collection */
            $collection = Mage::getResourceModel('wishlist/item_collection')
                ->addWishlistFilter($_entity)
                ->setVisibilityFilter(false)
                ->setSalableFilter(false);

            $this->_cache['entityItems'][$entityNumber] = $collection->getItems();
        }

        return $this->_cache['entityItems'][$entityNumber];
    }

    /**
     * Remaining Data
     */
    protected function _prepareRemaining()
    {
        if (Mage::helper('tnw_salesforce')->doPushShoppingCart()) {
            $this->_prepareEntityItems();
        }

        if (Mage::helper('tnw_salesforce/config_wishlist')->syncContactRole()) {
            $this->_prepareContactRoles();
        }
    }

    /**
     * @param $_entity
     * @param $key
     */
    protected function _prepareEntityObjCustom($_entity, $key)
    {
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            /** @var Mage_Core_Model_Store $store */
            $this->_obj->CurrencyIsoCode = $this->getCurrencyCode($_entity);
        }

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_ENTERPRISE . 'disableMagentoSync__c'}
            = true;
    }

    /**
     * @param $_entity Mage_Wishlist_Model_Wishlist
     * @param $type string
     * @return mixed
     */
    protected function _getObjectByEntityType($_entity, $type)
    {
        switch ($type) {
            case 'Wishlist':
                $_object = $_entity;
                break;

            case 'Customer':
                $_entityNumber = $this->_getEntityNumber($_entity);
                $_object = $this->_cache[sprintf('%sCustomers', $this->_magentoEntityName)][$_entityNumber];
                break;

            case 'Custom':
                /** @var Mage_Customer_Model_Customer $customer */
                $customer = $this->_getObjectByEntityType($_entity, 'Customer');
                $_object = $customer->getStore();
                break;

            default:
                $_object = null;
                break;
        }

        return $_object;
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

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Wishlist Push: Start----------');
        try {
            Mage::dispatchEvent('tnw_salesforce_wishlist_send_before', array(
                'data' => $this->_cache[$keyUpsert]
            ));

            $results = $this->getClient()->upsert('Id', array_values($this->_cache[$keyUpsert]), 'Opportunity');
            Mage::dispatchEvent('tnw_salesforce_wishlist_send_after', array(
                'data' => $this->_cache[$keyUpsert],
                'result' => $results
            ));
        } catch (Exception $e) {
            $results = array_fill(0, count($this->_cache[$keyUpsert]),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of an order to Salesforce failed' . $e->getMessage());
        }

        $entityNumbers = array_keys($this->_cache[$keyUpsert]);
        foreach ($results as $key => $result) {
            $entityNumber = $entityNumbers[$key];

            //Report Transaction
            $this->_cache['responses']['opportunities'][$entityNumber] = $result;

            if (!$result->success) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Opportunity Failed: (Wishlist: ' . $entityNumber . ')');

                $this->_cache['failedOpportunities'][] = $entityNumber;
                $this->_processErrors($result, 'Opportunity', $this->_cache[$keyUpsert][$entityNumber]);
                continue;
            }

            $entity = $this->getEntityCache($entityNumber);
            $entity->addData(array(
                'salesforce_id' => $result->id,
                'sf_insync' => 1,
            ));
            $entity->getResource()->save($entity);

            $this->_cache[sprintf('upserted%s',$this->getManyParentEntityType())][$entityNumber] = $result->id;
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Opportunity Upserted: ' . $result->id);
        }

        do {
            if (empty($this->_cache[sprintf('upserted%s',$this->getManyParentEntityType())])) {
                break;
            }

            $oppItemSet = Mage::helper('tnw_salesforce/salesforce_data')
                ->getOpportunityItems($this->_cache[sprintf('upserted%s',$this->getManyParentEntityType())]);

            if (empty($oppItemSet)) {
                break;
            }

            $oppItemSetId = array();
            foreach ($oppItemSet as $item) {
                $oppItemSetId[] = $item->Id;
            }

            foreach (array_chunk($oppItemSetId, TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT) as $oppItemSetId) {
                $this->getClient()->delete($oppItemSetId);
            }
        } while(false);

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Opportunity Push: End----------');
    }

    /**
     * @param $_entityItem Mage_Wishlist_Model_Item
     * @return bool
     */
    protected function _getEntityItemSalesforceId($_entityItem)
    {
        return false;
    }

    /**
     * @param $_entity Mage_Wishlist_Model_Wishlist
     * @return string
     */
    public function getCurrencyCode($_entity)
    {
        /** @var Mage_Core_Model_Store $store */
        $store = $this->_getObjectByEntityType($_entity, 'Custom');
        return $store->getBaseCurrencyCode();
    }

    /**
     * @param $_entityItem Mage_Wishlist_Model_Item
     * @throws Exception
     */
    protected function _prepareEntityItemObjCustom($_entityItem)
    {
        $entity = $this->getEntityByItem($_entityItem);

        $this->_obj->OpportunityId
            = $this->_cache['upserted' . $this->getManyParentEntityType()][$this->getEntityNumber($entity)];

        if (Mage::helper('tnw_salesforce')->isProfessionalEdition()) {
            $disableSyncField = Mage::helper('tnw_salesforce/config')->getDisableSyncField();
            $this->_obj->$disableSyncField = true;
        }

        $key = $_entityItem->getId();
        $this->_cache[sprintf('%sToUpsert', lcfirst($this->getItemsField()))]['cart_' . $key] = $this->_obj;
    }

    /**
     * @param $_entityItem Mage_Wishlist_Model_Item
     * @param $_type string
     * @return mixed
     * @throws \Mage_Core_Exception
     */
    protected function _getObjectByEntityItemType($_entityItem, $_type)
    {
        switch($_type)
        {
            case 'Wishlist':
                $_object = $this->getEntityByItem($_entityItem);
                break;

            case 'Wishlist Item':
                $_object = $_entityItem;
                break;

            case 'Product':
                $_object = $_entityItem->getProduct();
                if (!$_object->hasData('salesforce_pricebook_id')) {
                    // Load All Attributes
                    $_object->getResource()->load($_object, $_object->getId());
                }

                //FIX: Bundle product generate SKU
                $_object->setData('sku_type', '1');
                break;

            case 'Product Inventory':
                $product = $this->_getObjectByEntityItemType($_entityItem, 'Product');
                $_object = Mage::getModel('cataloginventory/stock_item')
                    ->loadByProduct($product);
                break;

            case 'Custom':
                $entity = $this->getEntityByItem($_entityItem);
                $_object = $this->_getObjectByEntityType($entity, 'Custom');
                break;

            default:
                $_object = null;
                break;
        }

        return $_object;
    }

    /**
     * @param array $chunk
     * @throws Exception
     */
    protected function _pushEntityItems($chunk = array())
    {
        try {
            $results = $this->getClient()->upsert('Id', array_values($chunk), 'OpportunityLineItem');
        } catch (Exception $e) {
            $results = array_fill(0, count($chunk),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of Opportunity Line Items to SalesForce failed' . $e->getMessage());
        }

        $entityNumbers = array_flip($this->_cache['upserted' . $this->getManyParentEntityType()]);
        $entityItemNumbers = array_keys($chunk);
        foreach ($results as $key => $result) {
            $entityItemNumber = $entityItemNumbers[$key];
            $entityNum = $entityNumbers[$chunk[$entityItemNumber]->OpportunityId];
            $entity = $this->getEntityCache($entityNum);

            //Report Transaction
            $this->_cache['responses'][lcfirst($this->getItemsField())][$entityNum]['subObj'][$entityItemNumber] = $result;

            if (!$result->success) {
                // Reset sync status
                $entity->setData('sf_insync', 0);
                $entity->getResource()->save($entity);

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR: One of the Cart Item for (Wishlist: ' . $entityNum . ') failed to upsert.');

                $this->_processErrors($result, 'OpportunityLineItem', $chunk[$entityItemNumber]);
                continue;
            }

            $_item = $entity->getItemCollection()->getItemById(str_replace('cart_', '', $entityItemNumber));
            if ($_item instanceof Mage_Core_Model_Abstract) {
                $_item->setData('salesforce_id', $result->id);
                $_item->getResource()->save($_item);
            }

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Cart Item (id: ' . $result->id . ') for (order: ' . $entityNum . ') upserted.');
        }
    }

    /**
     *
     */
    protected function _prepareContactRoles()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Opportunity Contact Role: Start----------');
        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $key => $entityNumber) {
            if (empty($this->_cache['upserted' . $this->getManyParentEntityType()][$entityNumber])) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('%s (%s): Skipping, issues with upserting an %s!',
                        strtoupper($this->_magentoEntityName), $entityNumber, $this->_salesforceEntityName));

                continue;
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('******** QUOTE (' . $entityNumber . ') ********');

            $entity = $this->getEntityCache($entityNumber);
            /** @var Mage_Customer_Model_Customer $customer */
            $customer = $this->_getObjectByEntityType($entity, 'Customer');
            $customerEmail = strtolower($customer->getEmail());

            $contactRole = new stdClass();

            $websiteId = $customer->getStore()->getWebsiteId();
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

            if (!empty($this->_cache['opportunityLookup'][$entityNumber]->OpportunityContactRoles->records)) {
                foreach ($this->_cache['opportunityLookup'][$entityNumber]->OpportunityContactRoles->records as $role) {
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
                $contactRole->OpportunityId = $this->_cache['upserted' . $this->getManyParentEntityType()][$entityNumber];
                $contactRole->Role = $defaultCustomerRole;

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

    /**
     *
     */
    protected function _pushRemainingCustomEntityData()
    {
        if (empty($this->_cache['contactRolesToUpsert'])) {
            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Contact Roles: Start----------');

        // Push Contact Roles
        try {
            $results = $this->getClient()->upsert('Id', array_values($this->_cache['contactRolesToUpsert']), 'OpportunityContactRole');
        } catch (Exception $e) {
            $results = array_fill(0, count($this->_cache['contactRolesToUpsert']),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of contact roles to SalesForce failed' . $e->getMessage());
        }

        $entityNumbers = array_flip($this->_cache['upserted'.$this->getManyParentEntityType()]);
        $entityItemNumbers = array_keys($this->_cache['contactRolesToUpsert']);
        foreach ($results as $key => $result) {
            $entityItemNumber = $entityItemNumbers[$key];
            $entityNum = $entityNumbers[$this->_cache['contactRolesToUpsert'][$entityItemNumber]->OpportunityId];
            $entity = $this->getEntityCache($entityNum);

            //Report Transaction
            $this->_cache['responses']['opportunityCustomerRoles'][$entityNum]['subObj'][] = $result;

            if (!$result->success) {
                // Reset sync status
                $entity->setData('sf_insync', 0);
                $entity->getResource()->save($entity);

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR: Contact Role (role: ' . $this->_cache['contactRolesToUpsert'][$entityItemNumber]->Role . ') for (quote: ' . $entityNum . ') failed to upsert.');

                $this->_processErrors($result, 'OpportunityContactRole', $this->_cache['contactRolesToUpsert'][$entityItemNumber]);
                continue;
            }

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Contact Role (role: ' . $this->_cache['contactRolesToUpsert'][$entityItemNumber]->Role . ') for (quote: ' . $entityNum . ') upserted.');
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Contact Roles: End----------');
    }
}