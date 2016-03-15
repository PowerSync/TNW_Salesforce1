<?php

/**
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
     * @var null
     */
    protected $_read = null;

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
        $opportunitiesUpdate = array();
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

            if ($_quote->getData('salesforce_id')) {
                $opportunitiesUpdate[] = $_quote->getData('salesforce_id');
            }
        }

        /* If existing Opportunity, delete products */
        if (!empty($opportunitiesUpdate)) {

            // Delete Products
            $oppItemSetId = array();
            $oppItemSet = Mage::helper('tnw_salesforce/salesforce_data')->getOpportunityItems($opportunitiesUpdate);
            foreach ($oppItemSet as $item) {
                $oppItemSetId[] = $item->Id;
            }

            $oppItemSetIds = array_chunk($oppItemSetId, TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT);
            foreach ($oppItemSetIds as $oppItemSetId) {
                $this->_mySforceConnection->delete($oppItemSetId);
            }

        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Opportunity Preparation: End----------');
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
            Mage::dispatchEvent("tnw_salesforce_opportunity_send_before", array(
                "data" => $this->_cache['opportunitiesToUpsert']
            ));

            $results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['opportunitiesToUpsert']), 'Opportunity');
            Mage::dispatchEvent("tnw_salesforce_opportunity_send_after", array(
                "data" => $this->_cache['opportunitiesToUpsert'],
                "result" => $results
            ));
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach ($_keys as $_id) {
                $this->_cache['responses']['opportunities'][$_id] = $_response;
            }

            $results = array();
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

                $_entity->addData(array(
                    'sf_sync_force'         => 0,
                    'sf_insync'             => 1,
                    'salesforce_id'         => $_result->id,
                    'contact_salesforce_id' => $_customer->getSalesforceId(),
                    'account_salesforce_id' => $_customer->getSalesforceAccountId()
                ));
                $_entity->getResource()->save($_entity);

                $this->_cache[sprintf('upserted%s', $this->getManyParentEntityType())][$_quoteNum] = $_result->id;
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Opportunity Upserted: ' . $_result->id);
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

    /**
     * Prepare Store Id for upsert
     *
     * @param Mage_Sales_Model_Quote $_item
     */
    protected function _prepareStoreId($_item) {
        $itemId = $this->getProductIdFromCart($_item);
        $_quote = $_item->getQuote();
        $_storeId = $_quote->getStoreId();

        if (!array_key_exists($_storeId, $this->_stockItems)) {
            $this->_stockItems[$_storeId] = array();
        }
        // Item's stock needs to be updated in Salesforce
        if (!in_array($itemId, $this->_stockItems[$_storeId])) {
            $this->_stockItems[$_storeId][] = $itemId;
        }
    }

    /**
     * @param $_item Mage_Sales_Model_Quote_Item
     * @return int
     * Get product Id from the cart
     */
    public function getProductIdFromCart($_item)
    {
        if (!$_item instanceof Mage_Sales_Model_Quote_Item) {
            return false;
        }

        /** @var Mage_Catalog_Helper_Product_Configuration $configuration */
        $configuration = Mage::helper('catalog/product_configuration');
        $custom = $configuration->getCustomOptions($_item);

        if (
            $_item->getData('product_type') == 'bundle'
            || (is_array($custom) && count($custom) > 0)
        ) {
            $id = $_item->getData('product_id');
        } else {
            $id = (int)Mage::getModel('catalog/product')->getIdBySku($_item->getSku());
        }

        return $id;
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

            $contactRole = new stdClass();

            $websiteId   = $customer->getWebsiteId()
                ? $customer->getWebsiteId()
                : $quote->getStore()->getWebsiteId();

            $websiteSfId = $this->_websiteSfIds[$websiteId];
            if (isset($this->_cache['contactsLookup'][$websiteSfId][$customer->getEmail()])){
                $contactRole->ContactId = $this->_cache['contactsLookup'][$websiteSfId][$customer->getEmail()]->Id;
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
                        . 'Please synchronize customer (email: ' . $customer->getEmail() . ')');
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
            $results = $this->_mySforceConnection->upsert("Id", array_values($chunk), 'OpportunityLineItem');
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach ($chunk as $_object) {
                $_quoteNum = $_quoteNumbers[$_object->OpportunityId];
                $this->_cache['responses']['opportunityLineItems'][$_quoteNum]['subObj'][] = $_response;
            }

            $results = array();
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
                $_entity->setData('sf_insync', 0);
                $_entity->getResource()->save($_entity);

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR: One of the Cart Item for (quote: ' . $_quoteNum . ') failed to upsert.');
                $this->_processErrors($_result, 'quoteCart', $chunk[$_quoteCartNumbers[$_key]]);
            }
            else {
                $_item = $_entity->getItemsCollection()->getItemById(str_replace('cart_', '', $_cartItemId));
                if ($_item instanceof Mage_Core_Model_Abstract) {
                    $_item->setData('salesforce_id', $_result->id);
                    $_item->getResource()->save($_item);
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
                $results = $this->_mySforceConnection->upsert("Id", $this->_cache['contactRolesToUpsert'], 'OpportunityContactRole');
            } catch (Exception $e) {
                $_response = $this->_buildErrorResponse($e->getMessage());
                foreach ($this->_cache['contactRolesToUpsert'] as $_object) {
                    $_quoteNum = $_quoteNumbers[$_object->OpportunityId];
                    $this->_cache['responses']['opportunityLineItems'][$_quoteNum]['subObj'][] = $_response;
                }

                $results = array();
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
     * @param $_websites
     * @return bool
     */
    protected function _checkSyncCustomer($_entityNumber, $_websites)
    {
        $_entityId   = array_search($_entityNumber, $this->_cache['entitiesUpdating']);
        if (false === $_entityId) {
            return false;
        }

        $customerId  = $this->_cache[sprintf('%sToCustomerId', $this->_magentoEntityName)][$_entityNumber];
        $email       = $this->_cache[sprintf('%sToEmail', $this->_magentoEntityName)][$_entityNumber];
        $websiteSfId = $_websites[$customerId];

        $syncCustomer = false;

        if (!isset($this->_cache['contactsLookup'][$websiteSfId][$email])
            || !isset($this->_cache['accountsLookup'][0][$email])
            || (
                isset($this->_cache['leadsLookup'][$websiteSfId][$email])
                && !$this->_cache['leadsLookup'][$websiteSfId][$email]->IsConverted
            )
        ) {
            $syncCustomer = true;
        }

        return $syncCustomer;
    }

    /**
     * @param $_entity Mage_Sales_Model_Quote
     */
    protected function _prepareEntityObjCustom($_entity)
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
        $this->_cache['quoteCustomers'][$_entityNumber] = $this->_getCustomer($_entity);

        // Associate quote Number with a customer ID
        $_customerId = ($this->_cache['quoteCustomers'][$_entityNumber]->getId())
            ? $this->_cache['quoteCustomers'][$_entityNumber]->getId()
            : $this->_guestCount++;
        $this->_cache['quoteToCustomerId'][$_entityNumber] = $_customerId;

        // Check if customer from this group is allowed to be synchronized
        $_customerGroup = $_entity->getData('customer_group_id');
        if ($_customerGroup === NULL) {
            $_customerGroup = $this->_cache['quoteCustomers'][$_entityNumber]->getGroupId();
        }

        if ($_customerGroup === NULL && !$this->isFromCLI()) {
            $_customerGroup = Mage::getSingleton('customer/session')->getCustomerGroupId();
        }

        if (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($_customerGroup)) {
            $this->logNotice("SKIPPING: Sync for customer group #" . $_customerGroup . " is disabled!");
            return false;
        }

        // Store quote number and customer Email into a variable for future use
        $_quoteEmail = strtolower($this->_cache['quoteCustomers'][$_entityNumber]->getEmail());
        if (empty($_quoteEmail)) {
            $this->logNotice('SKIPPED: Sync for quote #' . $_entityNumber . ' failed, quote is missing an email address!');
            return false;
        }

        $this->_emails[$_customerId] = $_quoteEmail;

        // Associate quote Number with a customer Email
        $this->_cache['quoteToEmail'][$_entityNumber] = $_quoteEmail;

        // Associate quote ID with quote Number
        $this->_quotes[$_entity->getId()] = $_entityNumber;

        $_websiteId = Mage::app()->getStore($_entity->getData('store_id'))->getWebsiteId();
        $this->_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];

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
     *
     */
    protected function _massAddAfter()
    {
        // Salesforce lookup, find all contacts/accounts by email address
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($this->_emails, $this->_websites);
        $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($this->_emails, $this->_websites);
        $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($this->_emails, $this->_websites);

        // Salesforce lookup, find all opportunities by Magento quote number
        $this->_cache['opportunityLookup'] = Mage::helper('tnw_salesforce/salesforce_data')->opportunityLookup($this->_cache['entitiesUpdating']);

        /**
         * Order customers sync can be denied if we just update order status
         */
        if ($this->getUpdateCustomer()) {
            $this->syncEntityCustomers($this->_emails, $this->_websites);
        }

        /**
         * define Salesforce data for order customers
         */
        foreach ($this->_cache['entitiesUpdating'] as $id => $_quoteNumber) {

            $email = strtolower($this->_cache['quoteToEmail'][$_quoteNumber]);

            if (isset($this->_cache['quoteCustomers'][$_quoteNumber])
                && $this->_cache['quoteCustomers'][$_quoteNumber] instanceof Varien_Object
                && !empty($this->_cache['accountsLookup'][0][$email])
            ) {

                $_websiteId = $this->_cache['quoteCustomers'][$_quoteNumber]->getData('website_id');

                $this->_cache['quoteCustomers'][$_quoteNumber]->setData('salesforce_id', $this->_cache['accountsLookup'][0][$email]->Id);
                $this->_cache['quoteCustomers'][$_quoteNumber]->setData('salesforce_account_id', $this->_cache['accountsLookup'][0][$email]->Id);

                // Overwrite Contact Id for Person Account
                if (property_exists($this->_cache['accountsLookup'][0][$email], 'PersonContactId')) {
                    $this->_cache['quoteCustomers'][$_quoteNumber]->setData('salesforce_id', $this->_cache['accountsLookup'][0][$email]->PersonContactId);
                }

                // Overwrite from Contact Lookup if value exists there
                if (isset($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$email])) {
                    $this->_cache['quoteCustomers'][$_quoteNumber]->setData('salesforce_id', $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$email]->Id);
                }

                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SUCCESS: Automatic customer synchronization.');

            } else {
                /**
                 * No customers for this order in salesforce - error
                 */
                // Something is wrong, could not create / find Magento customer in SalesForce
                $this->logError('CRITICAL ERROR: Contact or Lead for Magento customer (' . $email . ') could not be created / found!');
                $this->_skippedEntity[$id] = $id;

                continue;
            }
        }

        if (!empty($this->_skippedEntity)) {
            foreach ($this->_skippedEntity as $_idToRemove) {
                unset($this->_cache['entitiesUpdating'][$_idToRemove]);
            }
        }
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
                return $_entity;

            default:
                return parent::_getObjectByEntityType($_entity, $type);
        }
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
                return $this->getEntityByItem($_entityItem);

            case 'Cart Item':
                return $_entityItem;

            default:
                return parent::_getObjectByEntityItemType($_entityItem, $_type);
        }
    }
}