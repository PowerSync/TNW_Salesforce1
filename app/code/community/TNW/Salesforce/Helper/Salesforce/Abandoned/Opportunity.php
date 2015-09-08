<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Opportunity
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
    protected $_salesforceParentIdField = 'opportunityId';

    /**
     * @var null
     */
    protected $_read = null;

    /**
     * @param $id
     * @return Mage_Sales_Model_Quote
     */
    protected function _loadQuote($id)
    {
        $stores = Mage::app()->getStores(true);
        $storeIds = array_keys($stores);

        return Mage::getModel('sales/quote')->setSharedStoreIds($storeIds)->load($id);
    }

    /**
     * @param string $type
     * @return bool
     */
    public function process($type = 'soft')
    {
        try {
            if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
                Mage::helper('tnw_salesforce')->log("CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
                if (!$this->isFromCLI() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addWarning('WARNING: SKIPPING synchronization, could not establish Salesforce connection.');
                }

                return false;
            }
            Mage::helper('tnw_salesforce')->log("================ MASS SYNC: START ================");

            if (!is_array($this->_cache) || empty($this->_cache['entitiesUpdating'])) {
                Mage::helper('tnw_salesforce')->log("WARNING: Sync abandoned carts, cache is empty!", 1, "sf-errors");
                $this->_dumpObjectToLog($this->_cache, "Cache", true);

                return false;
            }

            if (!empty($this->_cache['leadsToConvert'])) {
                Mage::helper('tnw_salesforce')->log('----------Converting Leads: Start----------');
                $this->_convertLeads();
                Mage::helper('tnw_salesforce')->log('----------Converting Leads: End----------');
                if (!empty($this->_cache['toSaveInMagento'])) {
                    //if (!empty($this->_cache['toSaveInMagento']) && Mage::helper('tnw_salesforce')->usePersonAccount()) {
                    $this->_updateMagento();
                }
                $this->clearMemory();
            }

            $this->_alternativeKeys = $this->_cache['entitiesUpdating'];

            $this->_prepareOpportunities();
            $this->_pushOpportunitiesToSalesforce();
            $this->clearMemory();

            set_time_limit(1000);

            if ($type == 'full') {
                if (Mage::helper('tnw_salesforce')->doPushShoppingCart()) {
                    $this->_prepareOpportunityLineItems();
                }

                if (Mage::helper('tnw_salesforce/abandoned')->isEnabledCustomerRole()) {
                    $this->_prepareContactRoles();
                }
                $this->_pushRemainingOpportunityData();
                $this->clearMemory();
            }

            $this->_onComplete();

            Mage::helper('tnw_salesforce')->log("================= MASS SYNC: END =================");
            return true;
        } catch (Exception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log("CRITICAL: " . $e->getMessage());
        }
    }

    /**
     * @comment call leads convertation method
     */
    protected function _convertLeads()
    {
        return Mage::helper('tnw_salesforce/salesforce_data_lead')->setParent($this)->convertLeads('abandoned');
    }

    protected function _prepareOpportunities()
    {
        Mage::helper('tnw_salesforce')->log('----------Opportunity Preparation: Start----------');
        $opportunitiesUpdate = array();
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_quoteNumber) {
            if (!Mage::registry('quote_cached_' . $_quoteNumber)) {
                $_quote = $this->_loadQuote($_key);
                Mage::register('quote_cached_' . $_quoteNumber, $_quote);
            } else {
                $_quote = Mage::registry('quote_cached_' . $_quoteNumber);
            }

            $this->_obj = new stdClass();
            $this->_setOpportunityInfo($_quote);
            // Check if Pricebook Id does not match
            if (
                is_array($this->_cache['opportunityLookup'])
                && array_key_exists($_quoteNumber, $this->_cache['opportunityLookup'])
                && property_exists($this->_cache['opportunityLookup'][$_quoteNumber], 'Pricebook2Id')
                && $this->_obj->Pricebook2Id != $this->_cache['opportunityLookup'][$_quoteNumber]->Pricebook2Id
            ) {
                Mage::helper('tnw_salesforce')->log("SKIPPED Quote: " . $_quoteNumber . " - Opportunity uses a different pricebook(" . $this->_cache['opportunityLookup'][$_quoteNumber]->Pricebook2Id . "), please change it in Salesforce.");
                unset($this->_cache['entitiesUpdating'][$_key]);
                unset($this->_cache['abandonedToEmail'][$_quoteNumber]);
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

        Mage::helper('tnw_salesforce')->log('----------Opportunity Preparation: End----------');
    }

    /**
     * assign ownerId to opportunity
     *
     * @return bool
     */
    protected function _assignOwnerIdToOpp()
    {
        $_websites = $_emailArray = array();
        foreach ($this->_cache['abandonedToEmail'] as $_oid => $_email) {
            $_customerId = $this->_cache['abandonedToCustomerId'][$_oid];
            $_emailArray[$_customerId] = $_email;
            $_quote = Mage::registry('quote_cached_' . $_oid);
            $_websiteId = (array_key_exists($_oid, $this->_cache['abandonedCustomers']) && $this->_cache['abandonedCustomers'][$_oid]->getData('website_id')) ? $this->_cache['abandonedCustomers'][$_oid]->getData('website_id') : Mage::getModel('core/store')->load($_quote->getData('store_id'))->getWebsiteId();
            $_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];
        }
        // update contact lookup data
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emailArray, $_websites);
        $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($_emailArray, $_websites);
        // assign owner id to opp
        foreach ($this->_cache['opportunitiesToUpsert'] as $_quoteNumber => $_opportunityData) {
            $_email = $this->_cache['abandonedToEmail'][$_quoteNumber];
            $_quote = $this->_loadQuote($_quoteNumber);
            $_websiteId = ($this->_cache['abandonedCustomers'][$_quote->getId()]->getData('website_id')) ? $this->_cache['abandonedCustomers'][$_quote->getId()]->getData('website_id') : Mage::getModel('core/store')->load($_quote->getData('store_id'))->getWebsiteId();
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

    protected function _pushOpportunitiesToSalesforce()
    {
        if (!empty($this->_cache['opportunitiesToUpsert'])) {
            $_pushOn = $this->_magentoId;
            Mage::helper('tnw_salesforce')->log('----------Opportunity Push: Start----------');
            foreach (array_values($this->_cache['opportunitiesToUpsert']) as $_opp) {
                if (array_key_exists('Id', $_opp)) {
                    $_pushOn = 'Id';
                }
                foreach ($_opp as $_key => $_value) {
                    Mage::helper('tnw_salesforce')->log("Opportunity Object: " . $_key . " = '" . $_value . "'");
                }
                Mage::helper('tnw_salesforce')->log("--------------------------");
            }

            // assign owner id to opportunity
            $this->_assignOwnerIdToOpp();

            try {
                Mage::dispatchEvent("tnw_salesforce_quote_send_before", array("data" => $this->_cache['opportunitiesToUpsert']));

                $_toSyncValues = array_values($this->_cache['opportunitiesToUpsert']);
                $_keys = array_keys($this->_cache['opportunitiesToUpsert']);

                $results = $this->_mySforceConnection->upsert($_pushOn, $_toSyncValues, 'Opportunity');

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
                Mage::helper('tnw_salesforce')->log('CRITICAL: Push of an quote to Salesforce failed' . $e->getMessage());
            }


            $_entityArray = array_flip($this->_cache['entitiesUpdating']);

            $_undeleteIds = array();
            foreach ($results as $_key => $_result) {
                $_quoteNum = $_keys[$_key];

                //Report Transaction
                $this->_cache['responses']['opportunities'][$_quoteNum] = $_result;

                if (!$_result->success) {
                    if ($_result->errors[0]->statusCode == "ENTITY_IS_DELETED") {
                        $_undeleteIds[] = $_quoteNum;
                    }

                    Mage::helper('tnw_salesforce')->log('Opportunity Failed: (quote: ' . $_quoteNum . ')', 1, "sf-errors");
                    $this->_processErrors($_result, 'quote', $this->_cache['opportunitiesToUpsert'][$_quoteNum]);
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Failed to upsert Opportunity for Quote #' . $_quoteNum);
                    }
                    $this->_cache['failedOpportunities'][] = $_quoteNum;
                } else {
                    $quoteBind = array(
                        'sf_sync_force' => 0,
                        'sf_insync' => 1,
                        'salesforce_id' => $_result->id,
                    );
                    $abandonedCustomer = $this->_cache['abandonedCustomers'][$_quoteNum];
                    $quoteBind['contact_salesforce_id'] = $abandonedCustomer->getSalesforceId() ? :  null;
                    $quoteBind['account_salesforce_id'] = $abandonedCustomer->getSalesforceAccountId() ? : null;
                    $connection = Mage::helper('tnw_salesforce')->getDbConnection();
                    $connection->update(
                        Mage::helper('tnw_salesforce')->getTable('sales_flat_quote'),
                        $quoteBind,
                        $connection->quoteInto('entity_id = ?', $_entityArray[$_quoteNum])
                    );

                    $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_quoteNum] = $_result->id;
                    Mage::helper('tnw_salesforce')->log('Opportunity Upserted: ' . $_result->id);
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

            Mage::helper('tnw_salesforce')->log('----------Opportunity Push: End----------');
        } else {
            Mage::helper('tnw_salesforce')->log('No Opportunities found queued for the synchronization!');
        }
    }

    protected function _prepareOpportunityLineItems()
    {
        Mage::helper('tnw_salesforce')->log('----------Prepare Cart Items: Start----------');

        // only sync all products if processing real time
        if (!$this->_isCron) {
            foreach ($this->_cache['entitiesUpdating'] as $_key => $_quoteNumber) {
                if (in_array($_quoteNumber, $this->_cache['failedOpportunities'])) {
                    Mage::helper('tnw_salesforce')->log('QUOTE (' . $_quoteNumber . '): Skipping, issues with upserting an opportunity!');
                    continue;
                }
                if (!Mage::registry('quote_cached_' . $_quoteNumber)) {
                    $_quote = $this->_loadQuote($_key);
                    Mage::register('quote_cached_' . $_quoteNumber, $_quote);
                } else {
                    $_quote = Mage::registry('quote_cached_' . $_quoteNumber);
                }

                foreach ($_quote->getAllVisibleItems() as $_item) {
                    if (Mage::getStoreConfig(TNW_Salesforce_Helper_Config_Sales::XML_PATH_ORDERS_BUNDLE_ITEM_SYNC)) {
                        if ($_item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                            $this->_prepareStoreId($_item);
                            foreach ($_quote->getAllItems() as $_childItem) {
                                if ($_childItem->getParentItemId() == $_item->getItemId()) {
                                    $this->_prepareStoreId($_childItem);
                                }
                            }
                        } else {
                            $this->_prepareStoreId($_item);
                        }
                    } else {
                        $this->_prepareStoreId($_item);
                    }
                }
            }

            // Sync Products
            if (!empty($this->_stockItems)) {
                $this->syncProducts();
            }
        }

        foreach ($this->_cache['entitiesUpdating'] as $_key => $_quoteNumber) {
            if (in_array($_quoteNumber, $this->_cache['failedOpportunities'])) {
                Mage::helper('tnw_salesforce')->log('QUOTE (' . $_quoteNumber . '): Skipping, issues with upserting an opportunity!');
                continue;
            }

            $this->_prepareOrderItem($_quoteNumber);

        }
        Mage::helper('tnw_salesforce')->log('----------Prepare Cart Items: End----------');
    }

    /**
     * Prepare Store Id for upsert
     *
     * @param Mage_Sales_Model_Quote $_item
     */
    protected function _prepareStoreId(Mage_Sales_Model_Quote $_item) {
        $itemId = $this->getProductIdFromCart($_item);
        $_quote = $_item->getQuote();
        $_storeId = $_quote->getStoreId();

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            if ($_quote->getOrderCurrencyCode() != $_quote->getStoreCurrencyCode()) {
                $_storeId = $this->_getStoreIdByCurrency($_quote->getOrderCurrencyCode());
            }
        }

        if (!array_key_exists($_storeId, $this->_stockItems)) {
            $this->_stockItems[$_storeId] = array();
        }
        // Item's stock needs to be updated in Salesforce
        if (!in_array($itemId, $this->_stockItems[$_storeId])) {
            $this->_stockItems[$_storeId][] = $itemId;
        }
    }

    /**
     * @return array
     */
    protected function getUpsertedEntityIds()
    {
        $entityIds = array();
        foreach ($this->_cache['entitiesUpdating'] as $quoteId) {
            if (!in_array($quoteId, $this->_cache['failedOpportunities'])) {
                $entityIds[] = $quoteId;
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
        if (!isset($this->_cache['abandonedToCustomerId'][$quote->getId()])
            || !$this->_cache['abandonedToCustomerId'][$quote->getId()]
        ) {
            $this->_cache['abandonedToCustomerId'][$quote->getId()] = $quote->getCustomerId();
        }

        $customerId = $this->_cache['abandonedToCustomerId'][$quote->getId()];

        //always update cache array if customer ids are the same
        if ($customerId == $quote->getCustomerId() || !$this->_cache['abandonedCustomers'][$quote->getId()]) {
            $this->_cache['abandonedCustomers'][$quote->getId()] = $quote->getCustomer();
        }

        return $this->_cache['abandonedCustomers'][$quote->getId()];
    }

    protected function _prepareContactRoles()
    {
        $helper = Mage::helper('tnw_salesforce');
        $helper->log('----------Prepare Opportunity Contact Role: Start----------');
        foreach ($this->getUpsertedEntityIds() as $quoteNumber) {
            $helper->log('******** QUOTE (' . $quoteNumber . ') ********');

            $quote = $this->_loadQuote($quoteNumber);
            $customer = $this->getQuoteCustomer($quote);

            $contactRole = new stdClass();
            $email = strtolower($customer->getEmail());
            $websiteId = $customer->getWebsiteId() ? $customer->getWebsiteId() : $quote->getStore()->getWebsiteId();

            /**
             * we use SF websiteId in lookup arrays in this class
             */
            $websiteSfId = $this->_websiteSfIds[$websiteId];

            /**
             * try to use data from lookup array for person accounts or get data from customer directly
             */
            if (
                 isset($this->_cache['accountsLookup'])
                    && isset($this->_cache['accountsLookup'][$websiteSfId])
                    && isset($this->_cache['accountsLookup'][$websiteSfId][$email])
                    && is_object($this->_cache['accountsLookup'][$websiteSfId][$email])
                    && (property_exists($this->_cache['accountsLookup'][$websiteSfId][$email], 'IsPersonAccount')
                        || (bool)$customer->getSalesforceIsPerson()
                    )

            ) {
                $contactRole->ContactId = $this->_cache['accountsLookup'][$websiteSfId][$email]->Id;
            } else {
                $contactRole->ContactId = $customer->getSalesforceId();
            }

            // Check if already exists
            $skip = false;

            $magentoQuoteNumber = TNW_Salesforce_Helper_Abandoned::ABANDONED_CART_ID_PREFIX . $quoteNumber;
            $defaultCustomerRole = Mage::helper('tnw_salesforce/abandoned')->getDefaultCustomerRole();

            if ($this->_cache['opportunityLookup']
                && array_key_exists($magentoQuoteNumber, $this->_cache['opportunityLookup'])
                && $this->_cache['opportunityLookup'][$magentoQuoteNumber]->OpportunityContactRoles
            ) {
                $opportunity = $this->_cache['opportunityLookup'][$magentoQuoteNumber];
                foreach ($opportunity->OpportunityContactRoles->records as $role) {
                    if ($role->ContactId == $contactRole->ContactId) {
                        if ($role->Role == $defaultCustomerRole) {
                            // No update required
                            $helper->log('Contact Role information is the same, no update required!');
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
                    $helper->log(
                        sprintf('OpportunityContactRole Object: %s = \'%s\'', $key, $value));
                }

                if ($contactRole->ContactId) {
                    $this->_cache['contactRolesToUpsert'][] = $contactRole;
                } else {
                    $flashMessageAllowed = !$this->isFromCLI() && !$this->isCron() && $helper->displayErrors();
                    if ($flashMessageAllowed) {
                        Mage::getSingleton('adminhtml/session')->addError('Was not able to convert customer Lead, '
                            . 'skipping Opportunity Contact Role assignment. '
                            . 'Please synchronize customer (email: ' . $email . ')');
                    }
                }
            }
        }
        $helper->log('----------Prepare Opportunity Contact Role: End----------');
    }

    protected function _pushOpportunityLineItems($chunk = array())
    {
        if (empty($this->_cache  ['upserted' . $this->getManyParentEntityType()])) {
            return false;
        }
        $_quoteNumbers = array_flip($this->_cache  ['upserted' . $this->getManyParentEntityType()]);
        try {
            $results = $this->_mySforceConnection->upsert("Id", array_values($chunk), 'OpportunityLineItem');
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach ($chunk as $_object) {
                $this->_cache['responses']['opportunityLineItems'][] = $_response;
            }
            $results = array();
            Mage::helper('tnw_salesforce')->log('CRITICAL: Push of Opportunity Line Items to SalesForce failed' . $e->getMessage());
        }

        foreach ($results as $_key => $_result) {
            $_quoteNum = $_quoteNumbers[$this->_cache['opportunityLineItemsToUpsert'][$_key]->OpportunityId];

            //Report Transaction
            $this->_cache['responses']['opportunityLineItems'][] = $_result;

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
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_quote') . "` SET sf_insync = 0, created_at = created_at WHERE salesforce_id = '" . $this->_cache['opportunityLineItemsToUpsert'][$_key]->OpportunityId . "';";
                Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

                Mage::helper('tnw_salesforce')->log('ERROR: One of the Cart Item for (quote: ' . $_quoteNum . ') failed to upsert.', 1, "sf-errors");
                $this->_processErrors($_result, 'quoteCart', $chunk[$_key]);
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('Failed to upsert one of the Cart Item for Quote #' . $_quoteNum);
                }
            } else {
                Mage::helper('tnw_salesforce')->log('Cart Item (id: ' . $_result->id . ') for (quote: ' . $_quoteNum . ') upserted.');
            }
        }
    }

    protected function _pushRemainingOpportunityData()
    {
        if (!empty($this->_cache['opportunityLineItemsToUpsert'])) {
            Mage::helper('tnw_salesforce')->log('----------Push Cart Items: Start----------');
            // Push Cart
            $_ttl = count($this->_cache['opportunityLineItemsToUpsert']);
            if ($_ttl > 199) {
                $_steps = ceil($_ttl / 199);
                for ($_i = 0; $_i < $_steps; $_i++) {
                    $_start = $_i * 200;
                    $_itemsToPush = array_slice($this->_cache['opportunityLineItemsToUpsert'], $_start, $_start + 199);
                    $this->_pushOpportunityLineItems($_itemsToPush);
                }
            } else {
                $this->_pushOpportunityLineItems($this->_cache['opportunityLineItemsToUpsert']);
            }

            Mage::helper('tnw_salesforce')->log('----------Push Cart Items: End----------');
        }

        if (!empty($this->_cache['contactRolesToUpsert'])) {
            Mage::helper('tnw_salesforce')->log('----------Push Contact Roles: Start----------');
            // Push Contact Roles
            try {
                $results = $this->_mySforceConnection->upsert("Id", $this->_cache['contactRolesToUpsert'], 'OpportunityContactRole');
            } catch (Exception $e) {
                $_response = $this->_buildErrorResponse($e->getMessage());
                foreach ($this->_cache['contactRolesToUpsert'] as $_object) {
                    $this->_cache['responses']['opportunityLineItems'][] = $_response;
                }
                $results = array();
                Mage::helper('tnw_salesforce')->log('CRITICAL: Push of contact roles to SalesForce failed' . $e->getMessage());
            }

            $_quoteNumbers = array_flip($this->_cache  ['upserted' . $this->getManyParentEntityType()]);
            foreach ($results as $_key => $_result) {
                $_quoteNum = $_quoteNumbers[$this->_cache['contactRolesToUpsert'][$_key]->OpportunityId];

                //Report Transaction
                $this->_cache['responses']['opportunityCustomerRoles'][] = $_result;

                if (!(int)$_result->success) {
                    // Reset sync status
                    $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_quote') . "` SET sf_sync_force = 0, sf_insync = 0, created_at = created_at WHERE salesforce_id = '" . $this->_cache['contactRolesToUpsert'][$_key]->OpportunityId . "';";
                    Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
                    Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

                    Mage::helper('tnw_salesforce')->log('ERROR: Contact Role (role: ' . $this->_cache['contactRolesToUpsert'][$_key]->Role . ') for (quote: ' . $_quoteNum . ') failed to upsert.', 1, "sf-errors");
                    $this->_processErrors($_result, 'quoteCart', $this->_cache['contactRolesToUpsert'][$_key]);
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Failed to upsert Contact Role (' . $this->_cache['contactRolesToUpsert'][$_key]->Role . ') for Quote #' . $_quoteNum);
                    }
                } else {
                    Mage::helper('tnw_salesforce')->log('Contact Role (role: ' . $this->_cache['contactRolesToUpsert'][$_key]->Role . ') for (quote: ' . $_quoteNum . ') upserted.');
                }
            }
            Mage::helper('tnw_salesforce')->log('----------Push Contact Roles: End----------');
        }

    }

    /**
     * @param array $ids
     * @param bool $_isCron
     * @return bool
     */
    public function massAdd($_ids = NULL, $_isCron = false)
    {
        if (!$_ids) {
            Mage::helper('tnw_salesforce')->log("Abandoned Id is not specified, don't know what to synchronize!");
            return false;
        }
        // test sf api connection
        $_client = Mage::getSingleton('tnw_salesforce/connection');
        if (!$_client->tryWsdl()
            || !$_client->tryToConnect()
            || !$_client->tryToLogin()
        ) {
            Mage::helper('tnw_salesforce')->log("error on sync quotes, sf api connection failed");

            return true;
        }

        try {
            $this->_isCron = $_isCron;
            $_guestCount = 0;
            $_quotes = $_emails = $_websites = array();

            if (!is_array($_ids)) {
                $_ids = array($_ids);
            }

            foreach ($_ids as $_id) {

                // Clear Opportunity ID
                $this->resetAbandoned($_id);

                // Load quote by ID
                $_quote = $this->_loadQuote($_id);
                // Add to cache
                if (Mage::registry('quote_cached_' . $_quote->getId())) {
                    Mage::unregister('quote_cached_' . $_quote->getId());
                }
                Mage::register('quote_cached_' . $_quote->getId(), $_quote);

                // Quote could not be loaded for some reason
                if (!$_quote->getId()) {
                    $this->logError('WARNING: Sync for abandoned cart #' . $_id . ', quote could not be loaded!');
                    $_skippedAbandoned[$_id] = $_id;
                    continue;
                }

                // Get Magento customer object
                $this->_cache['abandonedCustomers'][$_quote->getId()] = $this->_getCustomer($_quote);

                // Associate quote Number with a customer ID
                $_customerId = ($this->_cache['abandonedCustomers'][$_quote->getId()]->getId()) ? $this->_cache['abandonedCustomers'][$_quote->getId()]->getId() : $_guestCount;
                $this->_cache['abandonedToCustomerId'][$_quote->getId()] = $_customerId;

                if (!$this->_cache['abandonedCustomers'][$_quote->getId()]->getId()) {
                    $_guestCount++;
                }

                // Check if customer from this group is allowed to be synchronized
                $_customerGroup = $_quote->getData('customer_group_id');
                if ($_customerGroup === NULL) {
                    $_customerGroup = $this->_cache['abandonedCustomers'][$_quote->getId()]->getGroupId();
                }
                if ($_customerGroup === NULL && !$this->isFromCLI()) {
                    $_customerGroup = Mage::getSingleton('customer/session')->getCustomerGroupId();
                }

                if (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($_customerGroup)) {
                    $this->logNotice("SKIPPING: Sync for customer group #" . $_customerGroup . " is disabled!");
                    $_skippedAbandoned[$_id] = $_id;
                    continue;
                }

                $_emails[$_customerId] = strtolower($_quote->getCustomerEmail());

                // Associate quote Number with a customer Email
                $this->_cache['abandonedToEmail'][$_quote->getId()] = $this->_cache['abandonedCustomers'][$_quote->getId()]->getEmail();

                // Store quote number and customer Email into a variable for future use
                $_quoteEmail = strtolower($this->_cache['abandonedCustomers'][$_quote->getId()]->getEmail());
                $_quoteNumber = $_quote->getId();

                if (empty($_quoteEmail)) {
                    $this->logNotice('SKIPPED: Sync for quote #' . $_quoteNumber . ' failed, quote is missing an email address!');
                    $_skippedAbandoned[$_id] = $_id;
                    continue;
                }

                // Associate quote ID with quote Number
                $this->_cache['entitiesUpdating'][$_id] = $_quoteNumber;
                $_quotes[$_id ] = TNW_Salesforce_Helper_Abandoned::ABANDONED_CART_ID_PREFIX . $_quoteNumber;

                $_websiteId = Mage::getModel('core/store')->load($_quote->getData('store_id'))->getWebsiteId();
                $_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];
            }

            // Salesforce lookup, find all contacts/accounts by email address
            $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emails, $_websites);
            $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($_emails, $_websites);
            $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($_emails, $_websites);

            // Salesforce lookup, find all opportunities by Magento quote number
            $this->_cache['opportunityLookup'] = Mage::helper('tnw_salesforce/salesforce_data')->opportunityLookup($_quotes);

            /**
             * Force sync of the customer
             * Or if it's guest checkout: customer->getId() is empty
             * Or customer was not synchronized before: no account/contact ids ot lead not converted
             */

                $_customersToSync = array();

                foreach ($this->_cache['abandonedCustomers'] as $_quoteNumber => $customer) {
                    $customerId = $this->_cache['abandonedToCustomerId'][$_quoteNumber];
                    $websiteSfId = $_websites[$customerId];

                    $email = $this->_cache['abandonedToEmail'][$_quoteNumber];

                    /**
                     * synchronize customer if no account/contact exists or lead not converted
                     */
                    if (!isset($this->_cache['contactsLookup'][$websiteSfId][$email])
                        || !isset($this->_cache['accountsLookup'][0][$email])
                        || (
                            isset($this->_cache['leadsLookup'][$websiteSfId][$email])
                            && !$this->_cache['leadsLookup'][$websiteSfId][$email]->IsConverted
                        )
                    ) {
                        $_customersToSync[$_quoteNumber] = $customer;
                    }
                }

                if (!empty($_customersToSync)) {
                    Mage::helper("tnw_salesforce")->log('Syncronizing Guest/New customer...');

                    $helperType = 'salesforce';
                    if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
                        $helperType = 'bulk';
                    }

                    /**
                     * @var $manualSync TNW_Salesforce_Helper_Bulk_Customer|TNW_Salesforce_Helper_Salesforce_Customer
                     */
                    $manualSync = Mage::helper('tnw_salesforce/' . $helperType . '_customer');
                    if ($manualSync->reset()) {
                        $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
                        $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());

                        $manualSync->forceAdd($_customersToSync, $this->_cache['abandonedCustomers']);
                        set_time_limit(30);
                        $abandonedCustomers = $manualSync->process(true);

                        if (!empty($abandonedCustomers)) {
                            if (!is_array($abandonedCustomers)) {
                                $_quoteNumbers = array_keys($_customersToSync);
                                $abandonedCustomersArray[array_shift($_quoteNumbers)] = $abandonedCustomers;
                            } else {
                                $abandonedCustomersArray = $abandonedCustomers;
                            }

                            $this->_cache['abandonedCustomers'] = $abandonedCustomersArray + $this->_cache['abandonedCustomers'];
                            set_time_limit(30);

                            $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emails, $_websites);
                            $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($_emails, $_websites);
                        }
                    }
                }


            /**
             * define Salesforce data for order customers
             */
            foreach ($this->_cache['entitiesUpdating'] as $id => $_quoteNumber) {

                $email = $this->_cache['abandonedToEmail'][$_quoteNumber];

                if (isset($this->_cache['abandonedCustomers'][$_quoteNumber])
                    && $this->_cache['abandonedCustomers'][$_quoteNumber] instanceof Varien_Object
                    && !empty($this->_cache['accountsLookup'][0][$email])
                ) {

                    $_websiteId = $this->_cache['abandonedCustomers'][$_quoteNumber]->getData('website_id');

                    $this->_cache['abandonedCustomers'][$_quoteNumber]->setData('salesforce_id', $this->_cache['accountsLookup'][0][$email]->Id);
                    $this->_cache['abandonedCustomers'][$_quoteNumber]->setData('salesforce_account_id', $this->_cache['accountsLookup'][0][$email]->Id);

                    // Overwrite Contact Id for Person Account
                    if (property_exists($this->_cache['accountsLookup'][0][$email], 'PersonContactId')) {
                        $this->_cache['abandonedCustomers'][$_quoteNumber]->setData('salesforce_id', $this->_cache['accountsLookup'][0][$email]->PersonContactId);
                    }

                    // Overwrite from Contact Lookup if value exists there
                    if (isset($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$email])) {
                        $this->_cache['abandonedCustomers'][$_quoteNumber]->setData('salesforce_id', $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$email]->Id);
                    }

                    Mage::helper("tnw_salesforce")->log('SUCCESS: Automatic customer synchronization.');

                } else {
                    /**
                     * No customers for this order in salesforce - error
                     */
                    // Something is wrong, could not create / find Magento customer in SalesForce
                    $this->logError('CRITICAL ERROR: Contact or Lead for Magento customer (' . $email . ') could not be created / found!');
                    $_skippedAbandoned[$id] = $id;

                    continue;
                }
            }

            if (!empty($_skippedAbandoned)) {
                $chunk = array_chunk($_skippedAbandoned, TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT);

                foreach ($chunk as $_skippedAbandonedChunk) {
                    $sql = "DELETE FROM `" . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . "` WHERE object_id IN ('" . join("','", $_skippedAbandoned) . "') and mage_object_type = 'sales/quote';";
                    Mage::helper('tnw_salesforce')->getDbConnection('delete')->query($sql);
                    foreach ($_skippedAbandonedChunk as $_idToRemove) {
                        unset($this->_cache['entitiesUpdating'][$_idToRemove]);
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log("CRITICAL: " . $e->getMessage());
        }
    }

    protected function _updateQuoteStageName()
    {
        $this->_obj->StageName = 'Committed'; // if $collection is empty then we had error "CRITICAL: Failed to upsert order: Required fields are missing: [StageName]"

        if ($stage = Mage::helper('tnw_salesforce/abandoned')->getDefaultAbandonedCartStageName()) {
            $this->_obj->StageName = $stage;
        }

        return $this;
    }

    /**
     * create opportunity object
     *
     * @param $quote Mage_Sales_Model_Quote
     */
    protected function _setOpportunityInfo($quote)
    {
        $_websiteId = $quote->getStoreId();

        $this->_updateQuoteStageName($quote);
        $_quoteNumber = $quote->getId();

        if (!$this->_cache['abandonedCustomers'][$quote->getId()]) {
            $this->_cache['abandonedCustomers'][$quote->getId()] = $this->_getCustomer($quote);
        }
        $_customer = $this->_cache['abandonedCustomers'][$quote->getId()];

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

        $magentoQuoteNumber = TNW_Salesforce_Helper_Abandoned::ABANDONED_CART_ID_PREFIX . $_quoteNumber;
        // Magento Quote ID
        $this->_obj->{$this->_magentoId} = $magentoQuoteNumber;

        // Force configured pricebook
        $this->_assignPricebookToOrder($quote);

        // Close Date
        if ($quote->getUpdatedAt()) {

            $closeDate = new Zend_Date($quote->getUpdatedAt(), Varien_Date::DATETIME_INTERNAL_FORMAT);
            $closeDate->addDay(Mage::helper('tnw_salesforce/abandoned')->getAbandonedCloseTimeAfter($quote));

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

        // Get Account Name from Salesforce
        $_accountName = (
            $this->_cache['accountsLookup']
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
            && array_key_exists($_customer->getEmail(), $this->_cache['accountsLookup'][0])
            && $this->_cache['accountsLookup'][0][$_customer->getEmail()]->AccountName
        ) ? $this->_cache['accountsLookup'][0][$_customer->getEmail()]->AccountName : NULL;
        if (!$_accountName) {
            $_accountName = ($quote->getBillingAddress()->getCompany()) ? $quote->getBillingAddress()->getCompany() : NULL;
            if (!$_accountName) {
                $_accountName = ($_accountName && !$quote->getShippingAddress()->getCompany()) ? $_accountName && !$quote->getShippingAddress()->getCompany() : NULL;
                if (!$_accountName) {
                    $_accountName = $_customer->getFirstname() . " " . $_customer->getLastname();
                }
            }
        }

        $this->_setOpportunityName($_quoteNumber, $_accountName);
    }

    /**
     * @param $orderNumber
     * @param $accountName
     */
    protected function _setOpportunityName($orderNumber, $accountName)
    {
        $this->_obj->Name = "Abandoned Cart #" . $orderNumber;
    }

    /**
     * Sync customer w/ SF before creating the quote
     *
     * @param $quote Mage_Sales_Model_Quote
     * @return false|Mage_Core_Model_Abstract
     */
    protected function _getCustomer($quote)
    {
        $customer_id = $quote->getCustomerId();

        if ($customer_id) {
            $_customer = Mage::getModel("customer/customer");
            if (Mage::helper('tnw_salesforce')->getMagentoVersion() < 1500) {
                $sql = "SELECT website_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('customer_entity') . "` WHERE entity_id = '" . $customer_id . "'";

                if (!$this->_read) {
                    $this->_read = Mage::getSingleton('core/resource')->getConnection('core_read');
                }

                $row = $this->_read->query($sql)->fetch();

                if (!$row) {
                    $_customer->setWebsiteId($row['website_id']);
                }
            }
            $_customer = $_customer->load($customer_id);
            unset($customer_id);
        } else {
            // Guest most likely
            $_customer = Mage::getModel('customer/customer');

            $_websiteId = Mage::getModel('core/store')->load($quote->getStoreId())->getWebsiteId();
            $_storeId = $quote->getStoreId();
            if ($_customer->getSharingConfig()->isWebsiteScope()) {
                $_customer->setWebsiteId($_websiteId);
            }
            $_customer->loadByEmail($quote->getCustomerEmail());

            if (!$_customer->getId()) {
                //Guest
                $_customer = Mage::getModel("customer/customer");
                $_customer->setGroupId(0); // NOT LOGGED IN
                $_customer->setFirstname($quote->getBillingAddress()->getFirstname());
                $_customer->setLastname($quote->getBillingAddress()->getLastname());
                $_customer->setEmail($quote->getCustomerEmail());
                $_customer->setStoreId($_storeId);
                if (isset($_websiteId)) {
                    $_customer->setWebsiteId($_websiteId);
                }

                $_customer->setCreatedAt(gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($quote->getCreatedAt()))));
                //TODO: Extract as much as we can from the quote

            } else {
                //UPDATE quote to record Customer Id
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_quote') . "` SET customer_id = " . $_customer->getId() . ", created_at = created_at WHERE entity_id = " . $quote->getId() . ";";
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_quote_address') . "` SET customer_id = " . $_customer->getId() . " WHERE parent_id = " . $quote->getId() . ";";
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
                Mage::helper("tnw_salesforce")->log('Guest user found in Magento, updating abandoned cart #' . $quote->getId() . ' attaching cusomter ID: ' . $_customer->getId());
            }
        }
        if (
            !$_customer->getDefaultBillingAddress()
            && is_object($quote->getBillingAddress())
            && $quote->getBillingAddress()->getData()
        ) {
            $_billingAddress = Mage::getModel('customer/address');
            $_billingAddress->setCustomerId(0)
                ->setIsDefaultBilling('1')
                ->setSaveInAddressBook('0')
                ->addData($quote->getBillingAddress()->getData());
            $_customer->setBillingAddress($_billingAddress);
        }
        if (
            !$_customer->getDefaultShippingAddress()
            && is_object($quote->getShippingAddress())
            && $quote->getShippingAddress()->getData()
        ) {
            $_shippingAddress = Mage::getModel('customer/address');
            $_shippingAddress->setCustomerId(0)
                ->setIsDefaultShipping('1')
                ->setSaveInAddressBook('0')
                ->addData($quote->getShippingAddress()->getData());
            $_customer->setShippingAddress($_shippingAddress);
        }
        return $_customer;
    }

    public function reset()
    {
        parent::reset();

        // Clean abandoned cache
        if (is_array($this->_cache['entitiesUpdating'])) {
            foreach ($this->_cache['entitiesUpdating'] as $_key => $_abandonedNumber) {
                if (Mage::registry('quote_cached_' . $_abandonedNumber)) {
                    Mage::unregister('quote_cached_' . $_abandonedNumber);
                }
            }
        }

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
            'abandonedCustomers' => array(),
            'toSaveInMagento' => array(),
            'contactsLookup' => array(),
            'failedOpportunities' => array(),
            'abandonedToEmail' => array(),
            'convertedLeads' => array(),
            'abandonedToCustomerId' => array(),
            'responses' => array(
                'leadsToConvert' => array(),
                'opportunities' => array(),
                'opportunityLineItems' => array(),
                'opportunityCustomerRoles' => array()
            ),
            'abandonedCustomersToSync' => array(),
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

    public function resetAbandoned($_id)
    {
        $quoteTable = Mage::getResourceSingleton('sales/quote')->getMainTable();

        $sql = "UPDATE `" . $quoteTable . "` SET sf_insync = 0, created_at = created_at WHERE entity_id = " . $_id . ";";
        Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
    }

}