<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Opportunity
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
     * @param string $type
     * @return bool
     */
    public function process($type = 'soft')
    {
        try {
            if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
                if (!$this->isFromCLI() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addWarning('WARNING: SKIPPING synchronization, could not establish Salesforce connection.');
                }

                return false;
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ MASS SYNC: START ================");

            if (!is_array($this->_cache) || empty($this->_cache['entitiesUpdating'])) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("WARNING: Sync orders, cache is empty!");
                $this->_dumpObjectToLog($this->_cache, "Cache", true);

                return false;
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
                if (Mage::helper('tnw_salesforce')->isOrderNotesEnabled()) {
                    $this->_prepareNotes();
                }
                if (Mage::helper('tnw_salesforce')->isEnabledCustomerRole()) {
                    $this->_prepareContactRoles();
                }
                $this->_pushRemainingOpportunityData();
                $this->clearMemory();
            }

            $this->_onComplete();

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================= MASS SYNC: END =================");
            return true;
        } catch (Exception $e) {

            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: " . $e->getMessage());
        }
    }

    protected function _onComplete()
    {
        parent::_onComplete();

        if (Mage::helper('tnw_salesforce')->isRemoteLogEnabled()) {
            $logger = Mage::helper('tnw_salesforce/report');
            $logger->reset();

            $logger->add('Salesforce', 'leadsToConvert', $this->_cache['leadsToConvert'], $this->_cache['responses']['leadsToConvert']);
            $logger->add('Salesforce', 'Opportunity', $this->_cache['opportunitiesToUpsert'], $this->_cache['responses']['opportunities']);
            $logger->add('Salesforce', 'OpportunityLineItem', $this->_cache['opportunityLineItemsToUpsert'], $this->_cache['responses']['opportunityLineItems']);
            $logger->add('Salesforce', 'OpportunityContactRole', $this->_cache['contactRolesToUpsert'], $this->_cache['responses']['opportunityCustomerRoles']);

            $logger->send();
        }

        // Logout
        $this->reset();
        $this->clearMemory();
    }

    protected function syncProducts()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ INVENTORY SYNC: START ================");

        $manualSync = Mage::helper('tnw_salesforce/bulk_product');

        $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
        $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SF Domain: " . $this->getSalesforceServerDomain());
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SF Session: " . $this->getSalesforceSessionId());

        foreach ($this->_stockItems as $_storeId => $_products) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Store Id: " . $_storeId);
            $manualSync->setOrderStoreId($_storeId);
            if ($manualSync->reset()) {
                $manualSync->massAdd($this->_stockItems[$_storeId]);
                $manualSync->process();
                if (!$this->isFromCLI()) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Product inventory was synchronized with Salesforce'));
                }
            } else {
                if (!$this->isFromCLI() && !$this->isCron()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Salesforce Connection could not be established!');
                }
            }
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ INVENTORY SYNC: END ================");
    }

    protected function _updateMagento()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- Start: Magento Update ----------");
        $_websites = $_emailsArray = array();
        foreach ($this->_cache['toSaveInMagento'] as $_websiteId => $_contacts) {
            foreach ($_contacts as $_id => $_contact) {
                $_emailsArray[$_id] = $_contact->Email;
                $_websites[$_id] = $_contact->WebsiteId;
            }
        }

        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emailsArray, $_websites);
        $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($_emailsArray, $_websites);
        if (!$this->_cache['contactsLookup']) {
            $this->_dumpObjectToLog($_emailsArray, "Magento Emails", true);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Failed to look up a contact after Lead was converted.");
            return false;
        }

        foreach ($this->_cache['contactsLookup'] as $accounts) {
            foreach ($accounts as $_customer) {
                $_customer->IsPersonAccount = isset($_customer->IsPersonAccount) ? $_customer->IsPersonAccount : NULL;

                if ($_customer->IsPersonAccount !== NULL) {
                    Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customer->MagentoId, $_customer->IsPersonAccount, 'salesforce_is_person');
                }
                Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customer->MagentoId, 1, 'sf_insync', 'customer_entity_int');
                // Reset Lead Value
                Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customer->MagentoId, NULL, 'salesforce_lead_id');
            }

        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Updated: " . count($this->_cache['toSaveInMagento']) . " customers!");
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- End: Magento Update ----------");
    }

    /**
     * @comment call leads convertation method
     */
    protected function _convertLeads()
    {
        return Mage::helper('tnw_salesforce/salesforce_data_lead')->setParent($this)->convertLeads('order');
    }

    protected function _prepareOpportunities()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Opportunity Preparation: Start----------');
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (array_key_exists('leadsFailedToConvert', $this->_cache) && is_array($this->_cache['leadsFailedToConvert']) && array_key_exists($_orderNumber, $this->_cache['leadsFailedToConvert'])) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPED: Order (' . $_orderNumber . '), lead failed to convert');
                unset($this->_cache['entitiesUpdating'][$_key]);
                unset($this->_cache['orderToEmail'][$_orderNumber]);
                $this->_allResults['opportunities_skipped']++;
                continue;
            }

            if (!Mage::registry('order_cached_' . $_orderNumber)) {
                $_order = Mage::getModel('sales/order')->load($_key);
                Mage::register('order_cached_' . $_orderNumber, $_order);
            } else {
                $_order = Mage::registry('order_cached_' . $_orderNumber);
            }

            $this->_obj = new stdClass();
            $this->_setOpportunityInfo($_order);
            // Check if Pricebook Id does not match
            if (
                is_array($this->_cache['opportunityLookup'])
                && array_key_exists($_orderNumber, $this->_cache['opportunityLookup'])
                && property_exists($this->_cache['opportunityLookup'][$_orderNumber], 'Pricebook2Id')
                && $this->_obj->Pricebook2Id != $this->_cache['opportunityLookup'][$_orderNumber]->Pricebook2Id
            ) {
                // Delete all OpportunityProducts
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SKIPPED Order: " . $_orderNumber . " - Opportunity uses a different pricebook(" . $this->_cache['opportunityLookup'][$_orderNumber]->Pricebook2Id . "), please change it in Salesforce.");
                unset($this->_cache['entitiesUpdating'][$_key]);
                unset($this->_cache['orderToEmail'][$_orderNumber]);
                $this->_allResults['opportunities_skipped']++;
            } else {
                $this->_cache['opportunitiesToUpsert'][$_orderNumber] = $this->_obj;
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
        foreach($this->_cache['orderToEmail'] as $_oid => $_email) {
            $_customerId = $this->_cache['orderToCustomerId'][$_oid];
            $_emailArray[$_customerId] = $_email;
            $_order = Mage::registry('order_cached_' . $_oid);
            $_websiteId = Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
            $_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];
        }
        // update contact lookup data
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emailArray, $_websites);
        $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($_emailArray, $_websites);
        // assign owner id to opp
        foreach ($this->_cache['opportunitiesToUpsert'] as $_orderNumber => $_opportunityData) {
            $_email = $this->_cache['orderToEmail'][$_orderNumber];
            $_order = Mage::getModel('sales/order')->loadByIncrementId($_orderNumber);
            $_websiteId = Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
            $websiteSfId = $this->_websiteSfIds[$_websiteId];

            // Default Owner ID as configured in Magento
            $_opportunityData->OwnerId = Mage::helper('tnw_salesforce')->getDefaultOwner();
            if (
                is_array($this->_cache['opportunityLookup'])
                && array_key_exists($_orderNumber, $this->_cache['opportunityLookup'])
                && is_object($this->_cache['opportunityLookup'][$_orderNumber])
                && property_exists($this->_cache['opportunityLookup'][$_orderNumber], 'OwnerId')
                && $this->_cache['opportunityLookup'][$_orderNumber]->OwnerId
            ) {
                // Overwrite Owner ID if Opportuinity already exists, use existing owner
                $_opportunityData->OwnerId = $this->_cache['opportunityLookup'][$_orderNumber]->OwnerId;
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
            $this->_cache['opportunitiesToUpsert'][$_orderNumber] = $_opportunityData;
        }
        return true;
    }

    protected function _pushOpportunitiesToSalesforce()
    {
        if (!empty($this->_cache['opportunitiesToUpsert'])) {
            $_pushOn = $this->_magentoId;
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Opportunity Push: Start----------');
            foreach (array_values($this->_cache['opportunitiesToUpsert']) as $_opp) {
                if (array_key_exists('Id', $_opp)) {
                    $_pushOn = 'Id';
                }
                foreach ($_opp as $_key => $_value) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Opportunity Object: " . $_key . " = '" . $_value . "'");
                }
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("--------------------------");
            }

            // assign owner id to opportunity
            $this->_assignOwnerIdToOpp();

            try {
                Mage::dispatchEvent("tnw_salesforce_order_send_before",array("data" => $this->_cache['opportunitiesToUpsert']));

                $_toSyncValues = array_values($this->_cache['opportunitiesToUpsert']);
                $_keys = array_keys($this->_cache['opportunitiesToUpsert']);
                $results = $this->_mySforceConnection->upsert($_pushOn, $_toSyncValues, 'Opportunity');

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


            $_entityArray = array_flip($this->_cache['entitiesUpdating']);

            $_undeleteIds = array();
            foreach ($results as $_key => $_result) {
                $_orderNum = $_keys[$_key];

                //Report Transaction
                $this->_cache['responses']['opportunities'][$_orderNum] = $_result;

                if (!$_result->success) {
                    if ($_result->errors[0]->statusCode == "ENTITY_IS_DELETED") {
                        $_undeleteIds[] = $_orderNum;
                    }

                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Opportunity Failed: (order: ' . $_orderNum . ')');
                    $this->_processErrors($_result, 'order', $this->_cache['opportunitiesToUpsert'][$_orderNum]);
                    $this->_cache['failedOpportunities'][] = $_orderNum;
                } else {
                    $_contactId = ($this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_id')) ? "'" . $this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_id') . "'" : 'NULL';
                    $_accountId = ($this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_account_id')) ? "'" . $this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_account_id') . "'" : 'NULL';
                    $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET contact_salesforce_id = " . $_contactId . ", account_salesforce_id = " . $_accountId . ", sf_insync = 1, salesforce_id = '" . $_result->id . "' WHERE entity_id = " . $_entityArray[$_orderNum] . ";";

                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                    Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
                    $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_orderNum] = $_result->id;
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
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('No Opportunities found queued for the synchronization!');
        }
    }

    protected function _prepareOpportunityLineItems()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Cart Items: Start----------');

        // only sync all products if processing real time
        if (!$this->_isCron) {
            foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
                if (in_array($_orderNumber, $this->_cache['failedOpportunities'])) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('ORDER (' . $_orderNumber . '): Skipping, issues with upserting an opportunity!');
                    continue;
                }
                if (!Mage::registry('order_cached_' . $_orderNumber)) {
                    $_order = Mage::getModel('sales/order')->load($_key);
                    Mage::register('order_cached_' . $_orderNumber, $_order);
                } else {
                    $_order = Mage::registry('order_cached_' . $_orderNumber);
                }

                foreach ($_order->getAllVisibleItems() as $_item) {
                    if (Mage::getStoreConfig(TNW_Salesforce_Helper_Config_Sales::XML_PATH_ORDERS_BUNDLE_ITEM_SYNC)) {
                        if ($_item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                            $this->_prepareStoreId($_item);
                            foreach ($_order->getAllItems() as $_childItem) {
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

        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (in_array($_orderNumber, $this->_cache['failedOpportunities'])) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('ORDER (' . $_orderNumber . '): Skipping, issues with upserting an opportunity!');
                continue;
            }

            $this->_prepareOrderItem($_orderNumber);

        }
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Cart Items: End----------');
    }

    /**
     * Prepare Store Id for upsert
     *
     * @param Mage_Sales_Model_Order_Item $_item
     */
    protected function _prepareStoreId($_item) {
        $itemId = $this->getProductIdFromCart($_item);
        $_order = $_item->getOrder();
        $_storeId = $_order->getStoreId();

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            if ($_order->getOrderCurrencyCode() != $_order->getStoreCurrencyCode()) {
                $_storeId = $this->_getStoreIdByCurrency($_order->getOrderCurrencyCode());
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

    protected function _prepareNotes()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Notes: Start----------');

        // Get all products from each order and decide if all needs to me synced prior to inserting them
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (in_array($_orderNumber, $this->_cache['failedOpportunities'])) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('ORDER (' . $_orderNumber . '): Skipping, issues with upserting an opportunity!');
                continue;
            }
            $_order = (Mage::registry('order_cached_' . $_orderNumber)) ? Mage::registry('order_cached_' . $_orderNumber) : Mage::getModel('sales/order')->loadByIncrementId($_orderNumber);

            // TODO: need to add this feature
            foreach($_order->getAllStatusHistory() as $_note) {
                // Only sync notes for the order
                if ($_note->getData('entity_name') == 'order' &&  !$_note->getData('salesforce_id') && $_note->getData('comment')) {
                    $this->_obj = new stdClass();
                    $this->_obj->ParentId = $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_orderNumber];
                    $this->_obj->IsPrivate = 0;
                    $this->_obj->Body = utf8_encode($_note->getData('comment'));
                    $this->_obj->Title = utf8_encode($_note->getData('comment'));

                    if (strlen($this->_obj->Title) > 75) {
                        $this->_obj->Title = utf8_encode(substr($_note->getData('comment'), 0, 75) . '...');
                    } else {
                        $this->_obj->Title = utf8_encode($_note->getData('comment'));
                    }
                    $this->_cache['notesToUpsert'][$_note->getData('entity_id')] = $this->_obj;

                    foreach ($this->_obj as $key => $_value) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Note Object: " . $key . " = '" . $_value . "'");
                    }
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('+++++++++++++++++++++++++++++');
                }
            }
        }
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Notes: End----------');
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
            $_order = Mage::getModel('sales/order')->load($_key);
            $_customer = $this->_getCustomer($_order);

            if ($_customer->getData('salesforce_id')) {
                $this->_obj->ContactId = $_customer->getData('salesforce_id');
            }

            // Check if already exists
            $_skip = false;
            if ($this->_cache['opportunityLookup'] && array_key_exists($_orderNumber, $this->_cache['opportunityLookup']) && $this->_cache['opportunityLookup'][$_orderNumber]->OpportunityContactRoles) {
                foreach ($this->_cache['opportunityLookup'][$_orderNumber]->OpportunityContactRoles->records as $_role) {
                    if ($_role->ContactId == $this->_obj->ContactId) {
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
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Was not able to convert customer Lead, skipping Opportunity Contact Role assignment. Please synchronize customer (email: ' . $this->_cache['orderCustomers'][$_orderNumber]->getEmail() . ')');
                }
            }
        }
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Prepare Opportunity Contact Role: End----------');
    }

    protected function _pushOpportunityLineItems($chunk = array())
    {
        $_orderNumbers = array_flip($this->_cache  ['upserted' . $this->getManyParentEntityType()]);
        $_chunkKeys = array_keys($chunk);
        try {
            $results = $this->_mySforceConnection->upsert("Id", array_values($chunk), 'OpportunityLineItem');
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach($chunk as $_object) {
                $this->_cache['responses']['opportunityLineItems'][] = $_response;
            }
            $results = array();
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of Opportunity Line Items to SalesForce failed' . $e->getMessage());
        }

        $this->_cache['responses']['opportunityLineItems'] = $results;

        $_sql = "";
        foreach ($results as $_key => $_result) {
            $_orderNum = $_orderNumbers[$this->_cache['opportunityLineItemsToUpsert'][$_chunkKeys[$_key]]->OpportunityId];

            //Report Transaction
            $this->_cache['responses']['opportunityLineItems'][] = $_result;

            if (!$_result->success) {
                // Hide errors when product has been archived
                foreach ($_result->errors as $_error) {
                    if ($_error->statusCode == 'FIELD_INTEGRITY_EXCEPTION'
                        && $_error->message == 'field integrity exception: PricebookEntryId (pricebook entry has been archived)'
                    ) {
                        Mage::getSingleton('adminhtml/session')
                            ->addWarning('A product in Order #'
                                . $_orderNum
                                . ' have not been synchronized. Pricebook entry has been archived.'
                            );
                        continue 2;
                    }
                }
                // Reset sync status
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 0 WHERE salesforce_id = '" . $this->_cache['opportunityLineItemsToUpsert'][$_chunkKeys[$_key]]->OpportunityId . "';";
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: One of the Cart Item for (order: ' . $_orderNum . ') failed to upsert.');
                $this->_processErrors($_result, 'orderCart', $chunk[$_chunkKeys[$_key]]);
            } else {
                $_cartItemId = $_chunkKeys[$_key];
                if ($_cartItemId && strrpos($_cartItemId, 'cart_', -strlen($_cartItemId)) !== FALSE) {
                    $_sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_item') . "` SET salesforce_id = '" . $_result->id . "' WHERE item_id = '" . str_replace('cart_','',$_cartItemId) . "';";
                }
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Cart Item (id: ' . $_result->id . ') for (order: ' . $_orderNum . ') upserted.');
            }
        }
        if (!empty($_sql)) {
            Mage::helper('tnw_salesforce')->getDbConnection()->query($_sql);
        }
    }

    protected function _pushRemainingOpportunityData()
    {
        if (!empty($this->_cache['opportunityLineItemsToUpsert'])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Cart Items: Start----------');

            Mage::dispatchEvent("tnw_salesforce_order_products_send_before",array("data" => $this->_cache['opportunityLineItemsToUpsert']));

            // Push Cart
            $_opportunityItemsChunk = array_chunk($this->_cache['opportunityLineItemsToUpsert'], TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);
            foreach ($_opportunityItemsChunk as $_itemsToPush) {
                $this->_pushOpportunityLineItems($_itemsToPush);
            }

            Mage::dispatchEvent("tnw_salesforce_order_products_send_after",array(
                "data" => $this->_cache['opportunityLineItemsToUpsert'],
                "result" => $this->_cache['responses']['opportunityLineItems']
            ));

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Cart Items: End----------');
        }

        if (!empty($this->_cache['contactRolesToUpsert'])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Contact Roles: Start----------');

            Mage::dispatchEvent("tnw_salesforce_opportunity_contact_roles_send_before",array("data" => $this->_cache['contactRolesToUpsert']));

            // Push Contact Roles
            try {
                $results = $this->_mySforceConnection->upsert("Id", $this->_cache['contactRolesToUpsert'], 'OpportunityContactRole');
            } catch (Exception $e) {
                $_response = $this->_buildErrorResponse($e->getMessage());
                foreach($this->_cache['contactRolesToUpsert'] as $_object) {
                    $this->_cache['responses']['customerRoles'][] = $_response;
                }
                $results = array();
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of contact roles to SalesForce failed' . $e->getMessage());
            }

            $this->_cache['responses']['customerRoles'] = $results;

            $_orderNumbers = array_flip($this->_cache  ['upserted' . $this->getManyParentEntityType()]);
            foreach ($results as $_key => $_result) {
                $_orderNum = $_orderNumbers[$this->_cache['contactRolesToUpsert'][$_key]->OpportunityId];

                //Report Transaction
                $this->_cache['responses']['opportunityCustomerRoles'][] = $_result;

                if (!$_result->success) {
                    // Reset sync status
                    $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 0 WHERE salesforce_id = '" . $this->_cache['contactRolesToUpsert'][$_key]->OpportunityId . "';";
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
                "result" => $this->_cache['responses']['customerRoles']
            ));

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Contact Roles: End----------');
        }

        // Push Notes
        if (!empty($this->_cache['notesToUpsert'])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Notes: Start----------');

            Mage::dispatchEvent("tnw_salesforce_order_notes_send_before",array("data" => $this->_cache['notesToUpsert']));

            $_notesChunk = array_chunk($this->_cache['notesToUpsert'], TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);
            foreach ($_notesChunk as $_itemsToPush) {
                $this->_pushNotes($_itemsToPush);
            }

            Mage::dispatchEvent("tnw_salesforce_order_notes_send_after",array(
                "data" => $this->_cache['notesToUpsert'],
                "result" => $this->_cache['responses']['notes']
            ));

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Notes: End----------');
        }

        // Kick off the event to allow additional data to be pushed into salesforce
        Mage::dispatchEvent("tnw_salesforce_order_sync_after_final",array(
            "all" => $this->_cache['entitiesUpdating'],
            "failed" => $this->_cache['failedOpportunities']
        ));
    }

    /**
     * @comment This method is not necessary for Opportunity
     * @param $quotes
     */
    protected function _findAbandonedCart($quotes)
    {

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
     * create opportunity object
     *
     * @param $order
     */
    protected function _setOpportunityInfo($order)
    {
        $_websiteId = Mage::getModel('core/store')->load($order->getStoreId())->getWebsiteId();

        // Set StageName
        $this->_updateOrderStageName($order);

        $_orderNumber = $order->getRealOrderId();
        $_email = $this->_cache['orderToEmail'][$_orderNumber];
        $_orderNumber = $order->getRealOrderId();

        // For some reason some customers are removed from this list (some guests, not all)
        // Following logic loads the missing customer again
        if (!array_key_exists($_orderNumber, $this->_cache['orderCustomers']) || !is_object($this->_cache['orderCustomers'][$_orderNumber])) {
            $_customer = $this->_getCustomer($order);
            $this->_cache['orderCustomers'][$_orderNumber] = $_customer;
            if (is_array($this->_cache['accountsLookup'])
                && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
                && array_key_exists($_email, $this->_cache['accountsLookup'][0])) {
                $this->_cache['orderCustomers'][$_orderNumber]->setSalesforceId($this->_cache['accountsLookup'][0][$_email]->Id);
                $this->_cache['orderCustomers'][$_orderNumber]->setSalesforceAccountId($this->_cache['accountsLookup'][0][$_email]->AccountId);
            }
        } else {
            $_customer = $this->_cache['orderCustomers'][$_orderNumber];
        }

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $order->getData('order_currency_code');
        }

        // Link to a Website
        if (
            $_websiteId != NULL
            && array_key_exists($_websiteId, $this->_websiteSfIds)
            && $this->_websiteSfIds[$_websiteId]
        ) {
            $this->_obj->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()} = $this->_websiteSfIds[$_websiteId];
        }

        // Magento Order ID
        $this->_obj->{$this->_magentoId} = $_orderNumber;

        // Use existing Opportunity if creating from Quote
        $modules = Mage::getConfig()->getNode('modules')->children();
        if (
            property_exists($modules, 'Ophirah_Qquoteadv')
            && (string)$modules->Ophirah_Qquoteadv->active == "true"
            && $order->getData('c2q_internal_quote_id')
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Quote Id: " . $order->getData('c2q_internal_quote_id'));
            $_quote = Mage::getModel('qquoteadv/qqadvcustomer')->load($order->getData('c2q_internal_quote_id'));
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

        // Force configured pricebook
        $this->_assignPricebookToOrder($order);

        // Close Date
        if ($order->getCreatedAt()) {
            // Always use order date as closing date if order already exists
            $this->_obj->CloseDate = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($order->getCreatedAt())));
        } else {
            // this should never happen
            $this->_obj->CloseDate = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
        }

        // Account ID
        $this->_obj->AccountId = ($_customer->getSalesforceAccountId()) ? $_customer->getSalesforceAccountId() : NULL;
        // For guest, extract converted Account Id
        if (!$this->_obj->AccountId) {
            $this->_obj->AccountId = (
                array_key_exists($_orderNumber, $this->_cache['convertedLeads'])
                && property_exists($this->_cache['convertedLeads'][$_orderNumber], 'accountId')
            ) ? $this->_cache['convertedLeads'][$_orderNumber]->accountId : NULL;
        }

        //Process mapping
        Mage::getSingleton('tnw_salesforce/sync_mapping_order_opportunity')
            ->setSync($this)
            ->processMapping($order);

        // Get Account Name from Salesforce
        $_accountName = (
            $this->_cache['accountsLookup']
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
            && array_key_exists($_customer->getEmail(), $this->_cache['accountsLookup'][0])
            && $this->_cache['accountsLookup'][0][$_customer->getEmail()]->AccountName
        ) ? $this->_cache['accountsLookup'][0][$_customer->getEmail()]->AccountName : NULL;
        if (!$_accountName) {
            $_accountName = ($order->getBillingAddress()->getCompany()) ? $order->getBillingAddress()->getCompany() : NULL;
            if (!$_accountName) {
                $_accountName = ($_accountName && !$order->getShippingAddress()->getCompany()) ? $_accountName && !$order->getShippingAddress()->getCompany() : NULL;
                if (!$_accountName) {
                    $_accountName = $_customer->getFirstname() . " " . $_customer->getLastname();
                }
            }
        }

        $this->_setOpportunityName($_orderNumber, $_accountName);
        unset($order);
    }

    /**
     * @param $orderNumber
     * @param $accountName
     */
    protected function _setOpportunityName($orderNumber, $accountName)
    {
        $this->_obj->Name = "Request #" . $orderNumber;
    }

    /**
     * @param $order
     */
    protected function _updateOrderStageName($order)
    {
        ## Status integration
        ## implemented in v.1.14
        $collection = Mage::getModel('tnw_salesforce/order_status')->getCollection();
        $collection->getSelect()
            ->where("main_table.status = ?", $order->getStatus());

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Mapping status: " . $order->getStatus());

        $this->_obj->StageName = 'Committed'; // if $collection is empty then we had error "CRITICAL: Failed to upsert order: Required fields are missing: [StageName]"
        foreach ($collection as $_item) {
            $this->_obj->StageName = ($_item->getSfOpportunityStatusCode()) ? $_item->getSfOpportunityStatusCode() : 'Committed';

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Order status: " . $this->_obj->StageName);
            break;
        }
        unset($collection, $_item);
    }

    protected function _updateAccountLookupData($_customersToSync)
    {
        if (is_array($this->_cache['leadLookup'])) {
            foreach ($this->_cache['leadLookup'] as $website => $websiteLeads){
                foreach ($websiteLeads as $_orderNum => $_lead) {
                    $_email = $_lead->Email;
                    if (
                        $_lead->IsConverted
                        && is_array($this->_cache['accountsLookup'])
                        && !array_key_exists($_email, $this->_cache['accountsLookup'][$website])
                    ) {
                        $this->_cache['accountsLookup'][$website][$_email] = new stdClass();
                        $this->_cache['accountsLookup'][$website][$_email]->Id = $_lead->ConvertedContactId;
                        $this->_cache['accountsLookup'][$website][$_email]->Email = $_email;
                        $this->_cache['accountsLookup'][$website][$_email]->OwnerId = $_lead->OwnerId;
                        $this->_cache['accountsLookup'][$website][$_email]->AccountId = $_lead->ConvertedAccountId;
                        $this->_cache['accountsLookup'][$website][$_email]->AccountName = NULL;
                        $this->_cache['accountsLookup'][$website][$_email]->AccountOwnerId = $_lead->OwnerId;
                        $this->_cache['accountsLookup'][$website][$_email]->MagentoId = $_lead->MagentoId;
                        unset($websiteLeads[$_email]);
                        unset($_customersToSync[$_orderNum]);
                    }
                }
            }
        }
        return $_customersToSync;
    }

    public function reset()
    {
        parent::reset();

        // Clean order cache
        if (is_array($this->_cache['entitiesUpdating'])) {
            foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
                if (Mage::registry('order_cached_' . $_orderNumber)) {
                    Mage::unregister('order_cached_' . $_orderNumber);
                }
            }
        }

        $this->_standardPricebookId = Mage::helper('tnw_salesforce/salesforce_data')->getStandardPricebookId();
        $this->_defaultPriceBook = (Mage::helper('tnw_salesforce')->getDefaultPricebook()) ? Mage::helper('tnw_salesforce')->getDefaultPricebook() : $this->_standardPricebookId;

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
     * Get order object and update Order Status in Salesforce
     *
     * @param $order
     */
    public function updateStatus($order)
    {
        if (Mage::getModel('tnw_salesforce/localstorage')->getObject($order->getId())) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice("SKIPPING: Order #" . $order->getRealOrderId() . " is already queued for update.");
            return true;
        }

        $this->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
        $this->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));
        $this->reset();
        $this->massAdd($order->getId());

        $this->_obj = new stdClass();
        // Magento Order ID
        $orderIdParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
        $this->_obj->$orderIdParam = $order->getRealOrderId();

        // Update mapped fields
        //Process mapping
        Mage::getSingleton('tnw_salesforce/sync_mapping_order_opportunity')
            ->setSync($this)
            ->processMapping($order);
        // Update order status
        $this->_updateOrderStageName($order);

        if ($order->getSalesforceId()) {
            $this->_cache['opportunitiesToUpsert'][$order->getRealOrderId()] = $this->_obj;

            $this->_pushOpportunitiesToSalesforce();
        } else {
            // Need to do full sync instead
            $res = $this->process('full');
            if ($res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SUCCESS: Updating Order #" . $order->getRealOrderId());
            }
        }
    }

    /**
     * Return parent entity items and bundle items
     *
     * @param $parentEntity Mage_Sales_Model_Quote|Mage_Sales_Model_Order
     * @return mixed
     */
    public function getItems($parentEntity)
    {
        if (Mage::getStoreConfig(TNW_Salesforce_Helper_Config_Sales::XML_PATH_ORDERS_BUNDLE_ITEM_SYNC)) {
            $_items = array();
            foreach ($parentEntity->getAllVisibleItems() as $_item) {
                if ($_item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                    $_items[] = $_item;
                    foreach ($parentEntity->getAllItems() as $_childItem) {
                        if ($_childItem->getParentItemId() == $_item->getItemId()){
                            $_childItem->setRowTotalInclTax(null)
                                ->setRowTotal(null)
                                ->setDiscountAmount(null)
                                ->setBundleItemToSync(TNW_Salesforce_Helper_Config_Sales::BUNDLE_ITEM_MARKER
                                    . $_item->getSku());
                            $_items[] = $_childItem;
                        }
                    }
                } else {
                    $_items[] = $_item;
                }
            }
        } else {
            $_items = $parentEntity->getAllVisibleItems();
        }
        return $_items;
    }
}