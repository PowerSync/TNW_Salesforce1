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
     * @deprecated
     */
    protected function _loadQuote($id)
    {
        return $this->_loadEntity($id);
    }

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

    /**
     * @param $item Mage_Sales_Model_Quote_Item
     */
    protected function _getItemDescription($item)
    {
        $opt = array();
        $_summary = array();

        $typeId = $item->getProduct()->getTypeId();
        switch($typeId) {
            case 'bundle':
                /** @var Mage_Bundle_Helper_Catalog_Product_configuration $configuration */
                $configuration = Mage::helper('bundle/catalog_product_configuration');
                $options = $configuration->getOptions($item);
                break;

            case 'downloadable':
                /** @var Mage_Downloadable_Helper_Catalog_Product_Configuration $configuration */
                $configuration = Mage::helper('downloadable/catalog_product_configuration');
                $options = $configuration->getOptions($item);
                break;

            default:
                /** @var Mage_Catalog_Helper_Product_Configuration $configuration */
                $configuration = Mage::helper('catalog/product_configuration');
                $options = $configuration->getOptions($item);
                break;
        }

        $_prefix = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th></tr></thead><tbody>';
        foreach ($options as $_option) {
            $optionValue = '';
            if(isset($_option['print_value'])) {
                $optionValue = $_option['print_value'];
            } elseif (isset($_option['value'])) {
                $optionValue = $_option['value'];
            }

            if (is_array($optionValue)) {
                $optionValue = implode(', ', $optionValue);
            }

            $opt[] = '<tr><td align="left">' . $_option['label'] . '</td><td align="left">' . $optionValue . '</td></tr>';
            $_summary[] = strip_tags($optionValue);
        }

        if (count($opt) > 0) {
            $syncParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Product_Options__c";
            $this->_obj->$syncParam = $_prefix . join("", $opt) . '</tbody></table>';

            $this->_obj->Description = join(", ", $_summary);
            if (strlen($this->_obj->Description) > 200) {
                $this->_obj->Description = substr($this->_obj->Description, 0, 200) . '...';
            }
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
     * assign ownerId to opportunity
     *
     * @return bool
     */
    protected function _assignOwnerIdToOpp()
    {
        $_websites = $_emailArray = array();
        foreach ($this->_cache['quoteToEmail'] as $_quoteNumber => $_email) {
            $_customerId = $this->_cache['quoteToCustomerId'][$_quoteNumber];
            $_emailArray[$_customerId] = $_email;
            $_quote = $this->_loadEntityByCache(array_search($_quoteNumber, $this->_cache['entitiesUpdating']), $_quoteNumber);
            $_websiteId = (array_key_exists($_quoteNumber, $this->_cache['quoteCustomers']) && $this->_cache['quoteCustomers'][$_quoteNumber]->getData('website_id'))
                ? $this->_cache['quoteCustomers'][$_quoteNumber]->getData('website_id')
                : Mage::getModel('core/store')->load($_quote->getData('store_id'))->getWebsiteId();

            $_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];
        }

        // update contact lookup data
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emailArray, $_websites);
        $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($_emailArray, $_websites);

        // assign owner id to opp
        foreach ($this->_cache['opportunitiesToUpsert'] as $_quoteNumber => $_opportunityData) {
            $_email = $this->_cache['quoteToEmail'][$_quoteNumber];
            $_quote = $this->_loadEntityByCache(array_search($_quoteNumber, $this->_cache['entitiesUpdating']), $_quoteNumber);
            $_websiteId = ($this->_cache['quoteCustomers'][$_quoteNumber]->getData('website_id'))
                ? $this->_cache['quoteCustomers'][$_quoteNumber]->getData('website_id')
                : Mage::getModel('core/store')->load($_quote->getData('store_id'))->getWebsiteId();

            $websiteSfId = $this->_websiteSfIds[$_websiteId];

            // Default Owner ID as configured in Magento
            $_opportunityData->OwnerId = Mage::helper('tnw_salesforce')->getDefaultOwner();
            if (
                is_array($this->_cache['opportunityLookup'])
                && array_key_exists($_quoteNumber, $this->_cache['opportunityLookup'])
                && is_object($this->_cache['opportunityLookup'][$_quoteNumber])
                && property_exists($this->_cache['opportunityLookup'][$_quoteNumber], 'OwnerId')
                && $this->_cache['opportunityLookup'][$_quoteNumber]->OwnerId
            ) {
                // Overwrite Owner ID if Opportuinity already exists, use existing owner
                $_opportunityData->OwnerId = $this->_cache['opportunityLookup'][$_quoteNumber]->OwnerId;
            } elseif (
                $_email
                && is_array($this->_cache['contactsLookup'])
                && array_key_exists($websiteSfId, $this->_cache['contactsLookup'])
                && array_key_exists($_email, $this->_cache['contactsLookup'][$websiteSfId])
                && property_exists($this->_cache['contactsLookup'][$websiteSfId][$_email], 'OwnerId')
                && $this->_cache['contactsLookup'][$websiteSfId][$_email]->OwnerId
            ) {
                // Overwrite Owner ID, use Owner ID from Contact
                $_opportunityData->OwnerId = $this->_cache['contactsLookup'][$websiteSfId][$_email]->OwnerId;
            }
            // Reset back if inactive
            if (!$this->_isUserActive($_opportunityData->OwnerId)) {
                $_opportunityData->OwnerId = Mage::helper('tnw_salesforce')->getDefaultOwner();
            }
            $this->_cache['opportunitiesToUpsert'][$_quoteNumber] = $_opportunityData;
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
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of an quote to Salesforce failed' . $e->getMessage());
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

                $this->_processErrors($_result, 'quote', $this->_cache['opportunitiesToUpsert'][$_quoteNum]);
                $this->_cache['failedOpportunities'][] = $_quoteNum;

                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Opportunity Failed: (quote: ' . $_quoteNum . ')');
            } else {
                $_entity = $this->_loadEntityByCache(array_search($_quoteNum, $this->_cache['entitiesUpdating']), $_quoteNum);
                $_entity->addData(array(
                    'sf_sync_force'         => 0,
                    'sf_insync'             => 1,
                    'salesforce_id'         => $_result->id,
                    'contact_salesforce_id' => $this->_cache['quoteCustomers'][$_quoteNum]->getSalesforceId(),
                    'account_salesforce_id' => $this->_cache['quoteCustomers'][$_quoteNum]->getSalesforceAccountId()
                ));
                $_entity->getResource()->save($_entity);

                $this->_cache[sprintf('upserted%s', $this->getManyParentEntityType())][$_quoteNum] = $_result->id;
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

    /**
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return Mage_Customer_Model_Customer
     */
    protected function getQuoteCustomer($quote)
    {
        $_entityNumber = $this->_getEntityNumber($quote);

        if (!isset($this->_cache['quoteToCustomerId'][$_entityNumber])
            || !$this->_cache['quoteToCustomerId'][$_entityNumber]
        ) {
            $this->_cache['quoteToCustomerId'][$_entityNumber] = $quote->getCustomerId();
        }

        $customerId = $this->_cache['quoteToCustomerId'][$_entityNumber];

        //always update cache array if customer ids are the same
        if ($customerId == $quote->getCustomerId() && !$this->_cache['quoteCustomers'][$_entityNumber]) {
            $this->_cache['quoteCustomers'][$_entityNumber] = $quote->getCustomer();
        }

        return $this->_cache['quoteCustomers'][$_entityNumber];
    }

    protected function _prepareContactRoles()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Opportunity Contact Role: Start----------');
        foreach ($this->getUpsertedEntityIds() as $key => $quoteNumber) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('******** QUOTE (' . $quoteNumber . ') ********');

            $quote = $this->_loadEntityByCache($key, $quoteNumber);
            $customer = $this->getQuoteCustomer($quote);

            $contactRole = new stdClass();
            $email = strtolower($customer->getEmail());

            $contactRole->ContactId = $customer->getSalesforceId();

            // Check if already exists
            $skip = false;
            $defaultCustomerRole = Mage::helper('tnw_salesforce/config_sales_abandoned')->getDefaultCustomerRole();

            if ($this->_cache['opportunityLookup']
                && array_key_exists($quoteNumber, $this->_cache['opportunityLookup'])
                && $this->_cache['opportunityLookup'][$quoteNumber]->OpportunityContactRoles
            ) {
                $opportunity = $this->_cache['opportunityLookup'][$quoteNumber];
                foreach ($opportunity->OpportunityContactRoles->records as $role) {
                    if ($role->ContactId == $contactRole->ContactId) {
                        if ($role->Role == $defaultCustomerRole) {
                            // No update required
                            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Contact Role information is the same, no update required!');
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
                        . 'Please synchronize customer (email: ' . $email . ')');
                }
            }
        }
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Opportunity Contact Role: End----------');
    }

    protected function _pushEntityItems($chunk = array())
    {
        if (empty($this->_cache  ['upserted' . $this->getManyParentEntityType()])) {
            return false;
        }
        $_quoteNumbers = array_flip($this->_cache  ['upserted' . $this->getManyParentEntityType()]);
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
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of Opportunity Line Items to SalesForce failed' . $e->getMessage());
        }

        foreach ($results as $_key => $_result) {

            $_quoteNum = $_quoteNumbers[$this->_cache['opportunityLineItemsToUpsert'][$_quoteCartNumbers[$_key]]->OpportunityId];

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
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_quote') . "` SET sf_insync = 0, created_at = created_at WHERE salesforce_id = '" . $this->_cache['opportunityLineItemsToUpsert'][$_quoteCartNumbers[$_key]]->OpportunityId . "';";
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: One of the Cart Item for (quote: ' . $_quoteNum . ') failed to upsert.');
                $this->_processErrors($_result, 'quoteCart', $chunk[$_quoteCartNumbers[$_key]]);
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Cart Item (id: ' . $_result->id . ') for (quote: ' . $_quoteNum . ') upserted.');
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

    protected function _updateQuoteStageName($quote)
    {
        $this->_obj->StageName = 'Committed'; // if $collection is empty then we had error "CRITICAL: Failed to upsert order: Required fields are missing: [StageName]"

        if ($stage = Mage::helper('tnw_salesforce/config_sales_abandoned')->getDefaultAbandonedCartStageName()) {
            $this->_obj->StageName = $stage;
        }

        return $this;
    }

    /**
     * create opportunity object
     *
     * @param $quote Mage_Sales_Model_Quote
     * @return mixed|void
     */
    protected function _setEntityInfo($quote)
    {
        $_websiteId   = $quote->getStoreId();
        $_quoteNumber = $this->_getEntityNumber($quote);
        $_customer    = $this->_cache[sprintf('%sCustomers', $this->_magentoEntityName)][$_quoteNumber];
        $_lookupKey   = sprintf('%sLookup', $this->_salesforceEntityName);

        // Magento Order ID
        if (isset($this->_cache[$_lookupKey][$_quoteNumber])) {
            $this->_obj->Id = $this->_cache[$_lookupKey][$_quoteNumber]->Id;
        }

        $this->_obj->{$this->_magentoId}
            = $_quoteNumber;

        $this->_updateQuoteStageName($quote);

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $quote->getData('quote_currency_code');
        }

        // Link to a Website
        if (
            $_websiteId != NULL
            && array_key_exists($_websiteId, $this->_websiteSfIds)
            && $this->_websiteSfIds[$_websiteId]
        ) {
            $this->_obj->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()} = $this->_websiteSfIds[$_websiteId];
        }

        // Force configured pricebook
        $this->_assignPricebookToOrder($quote);

        // Close Date
        if ($quote->getUpdatedAt()) {

            $closeDate = new Zend_Date($quote->getUpdatedAt(), Varien_Date::DATETIME_INTERNAL_FORMAT);
            $closeDate->addDay(Mage::helper('tnw_salesforce/config_sales_abandoned')->getAbandonedCloseTimeAfter($quote));

            // Always use quote date as closing date if quote already exists
            $this->_obj->CloseDate = gmdate(DATE_ATOM, $closeDate->getTimestamp());

        } else {
            // this should never happen
            $this->_obj->CloseDate = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
        }

        // Account ID
        $this->_obj->AccountId = ($_customer->getSalesforceAccountId()) ? $_customer->getSalesforceAccountId() : NULL;
        // For guest, extract converted Account Id
        if (!$this->_obj->AccountId) {
            $this->_obj->AccountId = (
                array_key_exists($_quoteNumber, $this->_cache['convertedLeads'])
                && property_exists($this->_cache['convertedLeads'][$_quoteNumber], 'accountId')
            ) ? $this->_cache['convertedLeads'][$_quoteNumber]->accountId : NULL;
        }

        //Process mapping
        Mage::getSingleton('tnw_salesforce/sync_mapping_quote_opportunity')
            ->setSync($this)
            ->processMapping($quote);

        $this->_setOpportunityName($_quoteNumber);
    }

    /**
     * @param $orderNumber
     */
    protected function _setOpportunityName($orderNumber)
    {
        $this->_obj->Name = "Abandoned Cart #" . $orderNumber;
    }

    public function reset()
    {
        parent::reset();

        $this->_standardPricebookId = Mage::helper('tnw_salesforce/salesforce_data')->getStandardPricebookId();
        $this->_defaultPriceBook = (Mage::helper('tnw_salesforce')->getDefaultPricebook()) ? Mage::helper('tnw_salesforce')->getDefaultPricebook() : $this->_standardPricebookId;

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

        if (empty($this->_attributes)) {
            $resource = Mage::getResourceModel('eav/entity_attribute');
            $this->_attributes['salesforce_id'] = $resource->getIdByCode('customer', 'salesforce_id');
            $this->_attributes['salesforce_account_id'] = $resource->getIdByCode('customer', 'salesforce_account_id');
            $this->_attributes['salesforce_lead_id'] = $resource->getIdByCode('customer', 'salesforce_lead_id');
            $this->_attributes['salesforce_is_person'] = $resource->getIdByCode('customer', 'salesforce_is_person');
        }

        return $this->check();
    }

    /**
     * Get child product ids
     *
     * @param Mage_Sales_Model_Quote_Item $_item
     * @return array
     */
    protected function _getChildProductIdsFromCart($_item)
    {
        $Ids = array();
        $productId = $_item->getItemId();
        $Ids[] = (int) $_item->getProductId();

        foreach ($_item->getQuote()->getAllItems() as $_itemProduct) {
            if ($_itemProduct->getParentItemId() == $productId) {
                $Ids[] = (int) $_itemProduct->getProductId();
            }
        }

        return $Ids;
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

        $this->_emails[$_customerId] = strtolower($_entity->getCustomerEmail());

        // Associate quote Number with a customer Email
        $this->_cache['quoteToEmail'][$_entityNumber] = $this->_cache['quoteCustomers'][$_entityNumber]->getEmail();

        // Store quote number and customer Email into a variable for future use
        $_quoteEmail = strtolower($this->_cache['quoteCustomers'][$_entityNumber]->getEmail());
        if (empty($_quoteEmail)) {
            $this->logNotice('SKIPPED: Sync for quote #' . $_entityNumber . ' failed, quote is missing an email address!');
            return false;
        }

        // Associate quote ID with quote Number
        $this->_cache['entitiesUpdating'][$_entity->getId()] = $_entityNumber;
        $this->_quotes[$_entity->getId()] = $_entityNumber;

        $_websiteId = Mage::getModel('core/store')->load($_entity->getData('store_id'))->getWebsiteId();
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
        if ($this->_updateCustomer) {
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
}