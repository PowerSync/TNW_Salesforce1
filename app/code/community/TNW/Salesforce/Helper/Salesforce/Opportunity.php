<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Opportunity
 */
class TNW_Salesforce_Helper_Salesforce_Opportunity extends TNW_Salesforce_Helper_Salesforce_Abstract
{

    /**
     * @var array
     */
    protected $_stockItems = array();

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
    protected $_allowedOrderStatuses = array();

    /**
     * @var array
     */
    protected $_allResults = array(
        'opportunities_skipped' => 0,
    );

    /**
     * @var array
     */
    protected $_alternativeKeys = array();

    /**
     * @return array
     */
    public function getAlternativeKeys() {
        return $this->_alternativeKeys;
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
                Mage::helper('tnw_salesforce')->log("WARNING: Sync orders, cache is empty!", 1, "sf-errors");
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

            Mage::helper('tnw_salesforce')->log("================= MASS SYNC: END =================");
            return true;
        } catch (Exception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log("CRITICAL: " . $e->getMessage());
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
        Mage::helper('tnw_salesforce')->log("================ INVENTORY SYNC: START ================");

        $manualSync = Mage::helper('tnw_salesforce/bulk_product');

        $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
        $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());

        Mage::helper('tnw_salesforce')->log("SF Domain: " . $this->getSalesforceServerDomain());
        Mage::helper('tnw_salesforce')->log("SF Session: " . $this->getSalesforceSessionId());

        foreach ($this->_stockItems as $_storeId => $_products) {
            Mage::helper('tnw_salesforce')->log("Store Id: " . $_storeId);
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

        Mage::helper('tnw_salesforce')->log("================ INVENTORY SYNC: END ================");
    }

    protected function _updateMagento()
    {
        Mage::helper('tnw_salesforce')->log("---------- Start: Magento Update ----------");
        $_websites = $_emailsArray = array();
        foreach ($this->_cache['toSaveInMagento'] as $_websiteId => $_contacts) {
            foreach ($_contacts as $_id => $_contact) {
                $_emailsArray[$_id] = $_contact->Email;
                $_websites[$_id] = $_contact->WebsiteId;
            }
        }

        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emailsArray, $_websites);
        if (!$this->_cache['contactsLookup']) {
            $this->_dumpObjectToLog($_emailsArray, "Magento Emails", true);
            Mage::helper('tnw_salesforce')->log("ERROR: Failed to look up a contact after Lead was converted.", 1, "sf-errors");
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

        Mage::helper('tnw_salesforce')->log("Updated: " . count($this->_cache['toSaveInMagento']) . " customers!");
        Mage::helper('tnw_salesforce')->log("---------- End: Magento Update ----------");
    }

    protected function _convertLeads()
    {
        // Make sure that leadsToConvert cache has unique leads (by email)
        $_emailsForConvertedLeads = array();
        foreach ($this->_cache['leadsToConvert'] as $_orderNum => $_objToConvert) {
            if (!in_array($this->_cache['orderCustomers'][$_orderNum]->getEmail(), $_emailsForConvertedLeads)) {
                $_emailsForConvertedLeads[] = $this->_cache['orderCustomers'][$_orderNum]->getEmail();
            } else {
                unset($this->_cache['leadsToConvert'][$_orderNum]);
            }
        }

        $results = $this->_mySforceConnection->convertLead(array_values($this->_cache['leadsToConvert']));
        $_keys = array_keys($this->_cache['leadsToConvert']);
        foreach ($results->result as $_key => $_result) {
            $_orderNum = $_keys[$_key];

            //Report Transaction
            $this->_cache['responses']['leadsToConvert'][$_orderNum] = $_result;

            $_email = strtolower($this->_cache['orderToEmail'][$_orderNum]);
            $_order = (Mage::registry('order_cached_' . $_orderNum)) ? Mage::registry('order_cached_' . $_orderNum) : Mage::getModel('sales/order')->loadByIncrementId($_orderNum);
            $_customerId = (is_object($_order) && $_order->getCustomerId()) ? $_order->getCustomerId() : NULL;

            if (!$_result->success) {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Failed to convert Lead for Customer Email (' . $this->_cache['orderCustomers'][$_orderNum]->getEmail() . ')');
                }
                Mage::helper('tnw_salesforce')->log('Convert Failed: (email: ' . $this->_cache['orderCustomers'][$_orderNum]->getEmail() . ')', 1, "sf-errors");
                if ($_customerId) {
                    // Update Sync Status
                    Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, 0, 'sf_insync', 'customer_entity_int');
                }
                $this->_processErrors($_result, 'convertLead', $this->_cache['leadsToConvert'][$_orderNum]);
            } else {
                Mage::helper('tnw_salesforce')->log('Lead Converted for: (email: ' . $_email . ')');

                $_email = strtolower($this->_cache['orderCustomers'][$_orderNum]->getEmail());
                $_websiteId = $this->_cache['orderCustomers'][$_orderNum]->getData('website_id');
                if ($_customerId) {
                    Mage::helper('tnw_salesforce')->log('Converted customer: (magento id: ' . $_customerId . ')');

                    $this->_cache['toSaveInMagento'][$_websiteId][$_customerId] = new stdClass();
                    $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->Email = $_email;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->ContactId = $_result->contactId;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->AccountId = $_result->accountId;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->WebsiteId = $this->_websiteSfIds[$_websiteId];

                    // Update Salesforce Id
                    Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, $_result->contactId, 'salesforce_id');
                    // Update Account Id
                    Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, $_result->accountId, 'salesforce_account_id');
                    // Reset Lead Value
                    Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, NULL, 'salesforce_lead_id');
                    // Update Sync Status
                    Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, 1, 'sf_insync', 'customer_entity_int');

                    $this->_cache['orderCustomers'][$_orderNum] = Mage::getModel("customer/customer")->load($_customerId);
                } else {
                    Mage::helper('tnw_salesforce')->log('Converted customer: (guest)');
                    // For the guest
                    $this->_cache['orderCustomers'][$_orderNum]->setSalesforceLeadId(NULL);
                    $this->_cache['orderCustomers'][$_orderNum]->setSalesforceId($_result->contactId);
                    $this->_cache['orderCustomers'][$_orderNum]->setSalesforceAccountId($_result->accountId);
                    // Update Sync Status
                    $this->_cache['orderCustomers'][$_orderNum]->setSfInsync(0);
                }

                $this->_cache['convertedLeads'][$_orderNum] = new stdClass();
                $this->_cache['convertedLeads'][$_orderNum]->contactId = $_result->contactId;
                $this->_cache['convertedLeads'][$_orderNum]->accountId = $_result->accountId;
                $this->_cache['convertedLeads'][$_orderNum]->email = $_email;

                Mage::helper('tnw_salesforce')->log('Converted: (account: ' . $this->_cache['convertedLeads'][$_orderNum]->accountId . ') and (contact: ' . $this->_cache['convertedLeads'][$_orderNum]->contactId . ')');

                // Apply updates to after conversion to the cached queue (Not needed if only doing 1 order, which means 1 customer)
                /*
                foreach ($this->_cache['leadsToConvert'] as $_oid => $_obj) {
                    if ($_email == strtolower($this->_cache['orderCustomers'][$_oid]->getEmail())) {
                        $this->_cache['orderCustomers'][$_oid] = $this->_cache['orderCustomers'][$_orderNum];
                    }
                }
                */
            }
        }
    }

    protected function _prepareOpportunities()
    {
        Mage::helper('tnw_salesforce')->log('----------Opportunity Preparation: Start----------');
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (array_key_exists('leadsFailedToConvert', $this->_cache) && is_array($this->_cache['leadsFailedToConvert']) && array_key_exists($_orderNumber, $this->_cache['leadsFailedToConvert'])) {
                Mage::helper('tnw_salesforce')->log('SKIPPED: Order (' . $_orderNumber . '), lead failed to convert');
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
                //$this->_cache['opportunityLineItemsToDelete'][] = $_orderNumber;
                Mage::helper('tnw_salesforce')->log("SKIPPED Order: " . $_orderNumber . " - Opportunity uses a different pricebook(" . $this->_cache['opportunityLookup'][$_orderNumber]->Pricebook2Id . "), please change it in Salesforce.");
                unset($this->_cache['entitiesUpdating'][$_key]);
                unset($this->_cache['orderToEmail'][$_orderNumber]);
                $this->_allResults['opportunities_skipped']++;
            } else {
                $this->_cache['opportunitiesToUpsert'][$_orderNumber] = $this->_obj;
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
        foreach($this->_cache['orderToEmail'] as $_oid => $_email) {
            $_customerId = $this->_cache['orderToCustomerId'][$_oid];
            $_emailArray[$_customerId] = $_email;
            $_order = Mage::registry('order_cached_' . $_oid);
            //$_websiteId = (array_key_exists($_oid, $this->_cache['orderCustomers']) && $this->_cache['orderCustomers'][$_oid]->getData('website_id')) ? $this->_cache['orderCustomers'][$_oid]->getData('website_id') : Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
            $_websiteId = Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
            $_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];
        }
        // update contact lookup data
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emailArray, $_websites);
        // assign owner id to opp
        foreach ($this->_cache['opportunitiesToUpsert'] as $_orderNumber => $_opportunityData) {
            $_email = $this->_cache['orderToEmail'][$_orderNumber];
            $_order = Mage::getModel('sales/order')->loadByIncrementId($_orderNumber);
            //$_websiteId = ($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getData('website_id')) ? $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getData('website_id') : Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
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
                Mage::helper('tnw_salesforce')->log('CRITICAL: Push of an order to Salesforce failed' . $e->getMessage());
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

                    Mage::helper('tnw_salesforce')->log('Opportunity Failed: (order: ' . $_orderNum . ')', 1, "sf-errors");
                    $this->_processErrors($_result, 'order', $this->_cache['opportunitiesToUpsert'][$_orderNum]);
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Failed to upsert Opportunity for Order #' . $_orderNum);
                    }
                    $this->_cache['failedOpportunities'][] = $_orderNum;
                } else {

                    // set opp owner
                    // $this->_updateOppOwner($_result->id); // frozen until we get other working solution

                    $_contactId = ($this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_id')) ? "'" . $this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_id') . "'" : 'NULL';
                    $_accountId = ($this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_account_id')) ? "'" . $this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_account_id') . "'" : 'NULL';
                    $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET contact_salesforce_id = " . $_contactId . ", account_salesforce_id = " . $_accountId . ", sf_insync = 1, salesforce_id = '" . $_result->id . "' WHERE entity_id = " . $_entityArray[$_orderNum] . ";";

                    Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
                    Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
                    $this->_cache['upsertedOpportunities'][$_orderNum] = $_result->id;
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
            foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
                if (in_array($_orderNumber, $this->_cache['failedOpportunities'])) {
                    Mage::helper('tnw_salesforce')->log('ORDER (' . $_orderNumber . '): Skipping, issues with upserting an opportunity!');
                    continue;
                }
                if (!Mage::registry('order_cached_' . $_orderNumber)) {
                    $_order = Mage::getModel('sales/order')->load($_key);
                    Mage::register('order_cached_' . $_orderNumber, $_order);
                } else {
                    $_order = Mage::registry('order_cached_' . $_orderNumber);
                }

                foreach ($_order->getAllVisibleItems() as $_item) {
                    $id = $this->getProductIdFromCart($_item);
                    $_storeId = $_order->getStoreId();

                    if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
                        if ($_order->getData('order_currency_code') != $_order->getData('store_currency_code')) {
                            $_storeId = $this->_getStoreIdByCurrency($_order->getData('order_currency_code'));
                        }
                    }

                    if (!array_key_exists($_storeId, $this->_stockItems)) {
                        $this->_stockItems[$_storeId] = array();
                    }
                    // Item's stock needs to be updated in Salesforce
                    if (!in_array($id, $this->_stockItems[$_storeId])) {
                        $this->_stockItems[$_storeId][] = $id;
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
                Mage::helper('tnw_salesforce')->log('ORDER (' . $_orderNumber . '): Skipping, issues with upserting an opportunity!');
                continue;
            }
            Mage::helper('tnw_salesforce')->log('******** ORDER (' . $_orderNumber . ') ********');
            //$_order = Mage::getModel('sales/order')->load($_key);
            $_order = Mage::registry('order_cached_' . $_orderNumber);
            $_currencyCode = Mage::app()->getStore($_order->getStoreId())->getCurrentCurrencyCode();


            if (Mage::helper('tnw_salesforce')->useTaxFeeProduct() && $_order->getTaxAmount() > 0) {
                if (Mage::helper('tnw_salesforce')->getTaxProduct()) {
                    $this->addTaxProduct($_order, $_orderNumber);
                } else {
                    Mage::helper('tnw_salesforce')->log("CRITICAL ERROR: Opportunity Tax product is not set!", 1, "sf-errors");
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Could not add Tax Fee product to the Opportunity!');
                    }
                }
            }
            if (Mage::helper('tnw_salesforce')->useShippingFeeProduct() && $_order->getShippingAmount() > 0) {
                if (Mage::helper('tnw_salesforce')->getShippingProduct()) {
                    $this->addShippingProduct($_order, $_orderNumber);
                } else {
                    Mage::helper('tnw_salesforce')->log("CRITICAL ERROR: Opportunity Shipping product is not set!", 1, "sf-errors");
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Could not add Shipping Fee product to the Opportunity!');
                    }
                }
            }
            if (Mage::helper('tnw_salesforce')->useDiscountFeeProduct() && $_order->getData('discount_amount') != 0) {
                if (Mage::helper('tnw_salesforce')->getDiscountProduct()) {
                    $this->addDiscountProduct($_order, $_orderNumber);
                } else {
                    Mage::helper('tnw_salesforce')->log("CRITICAL ERROR: Discount product is not configured!", 1, "sf-errors");
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Could not add Discount Fee product to the Order!');
                    }
                }
            }

            foreach ($_order->getAllVisibleItems() as $_item) {
                if ((int) $_item->getQtyOrdered() == 0) {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice("Product w/ SKU (" . $_item->getSku() . ") for order #" . $_orderNumber . " is not synchronized, ordered quantity is zero!");
                    }
                    Mage::helper('tnw_salesforce')->log("NOTE: Product w/ SKU (" . $_item->getSku() . ") is not synchronized, ordered quantity is zero!");
                    continue;
                }
                // Load by product Id only if bundled OR simple with options
                $id = $this->getProductIdFromCart($_item);

                $_storeId = $_order->getStoreId();
                if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
                    if ($_order->getData('order_currency_code') != $_order->getData('store_currency_code')) {
                        $_storeId = $this->_getStoreIdByCurrency($_order->getData('order_currency_code'));
                    }
                }

                $_productModel = Mage::getModel('catalog/product')->setStoreId($_storeId);
                $_product = $_productModel->load($id);

                $_sku = ($_item->getSku() != $_product->getSku()) ? $_product->getSku() : $_item->getSku();

                $this->_obj = new stdClass();
                if (!$_product->getSalesforcePricebookId()) {
                    Mage::helper('tnw_salesforce')->log("ERROR: Product w/ SKU (" . $_item->getSku() . ") is not synchronized, could not add to Opportunity!");
                    continue;
                }

                //Process mapping
                Mage::getSingleton('tnw_salesforce/sync_mapping_order_opportunity_item')
                    ->setSync($this)
                    ->processMapping($_item, $_product);

                // Check if already exists

                $_cartItemFound = $this->doesCartItemExistInOpportunity($_orderNumber, $_item, $_sku);
                if ($_cartItemFound) {
                    $this->_obj->Id = $_cartItemFound;
                }

                $this->_obj->OpportunityId = $this->_cache['upsertedOpportunities'][$_orderNumber];
                //$subtotal = number_format((($item->getPrice() * $item->getQtyOrdered()) + $item->getTaxAmount()), 2, ".", "");
                //$subtotal = number_format(($_item->getPrice() * $_item->getQtyOrdered()), 2, ".", "");
                //$netTotal = number_format(($subtotal - $_item->getDiscountAmount()), 2, ".", "");

                if (!Mage::helper('tnw_salesforce')->useTaxFeeProduct()) {
                    $netTotal = number_format($_item->getData('row_total_incl_tax'), 2, ".", "");
                } else {
                    $netTotal = number_format($_item->getData('row_total'), 2, ".", "");
                }

                if (!Mage::helper('tnw_salesforce')->useDiscountFeeProduct()) {
                    $netTotal = number_format(($netTotal - $_item->getData('discount_amount')), 2, ".", "");
                    $this->_obj->UnitPrice = number_format($netTotal / $_item->getQtyOrdered(), 2, ".", "");;
                } else {
                    if ((int) $_item->getQtyOrdered() == 0) {
                        $this->_obj->UnitPrice = $netTotal;
                    } else {
                        $this->_obj->UnitPrice = $netTotal / $_item->getQtyOrdered();
                    }
                }

                if (!property_exists($this->_obj, "Id")) {
                    $this->_obj->PricebookEntryId = $_product->getSalesforcePricebookId();
                }

                //$this->_obj->ProductCode = $_item->getSku();
                //$defaultServiceDate = Mage::helper('tnw_salesforce/shipment')->getDefaultServiceDate();
                //if ($defaultServiceDate) {
                //    $this->_obj->ServiceDate = $defaultServiceDate;
                //}
                $opt = array();
                $options = $_item->getProductOptions();
                $_summary = array();
                if (
                    is_array($options)
                    && array_key_exists('options', $options)
                ) {
                    $_prefix = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th></tr></thead><tbody>';
                    foreach ($options['options'] as $_option) {
                        $opt[] = '<tr><td align="left">' . $_option['label'] . '</td><td align="left">' . $_option['print_value'] . '</td></tr>';
                        $_summary[] = $_option['print_value'];
                    }
                }
                if (
                    is_array($options)
                    && $_item->getData('product_type') == 'bundle'
                    && array_key_exists('bundle_options', $options)
                ) {
                    $_prefix = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th><th>Qty</th><th align="left">Fee<th></tr><tbody>';
                    foreach ($options['bundle_options'] as $_option) {
                        $_string = '<td align="left">' . $_option['label'] . '</td>';
                        if (is_array($_option['value'])) {
                            $_tmp = array();
                            foreach ($_option['value'] as $_value) {
                                $_tmp[] = '<td align="left">' . $_value['title'] . '</td><td align="center">' . $_value['qty'] . '</td><td align="left">' . $_currencyCode . ' ' . number_format($_value['price'], 2) . '</td>';
                                $_summary[] = $_value['title'];
                            }
                            if (count($_tmp) > 0) {
                                $_string .= join(", ", $_tmp);
                            }
                        }

                        $opt[] = '<tr>' . $_string . '</tr>';
                    }
                }
                if (count($opt) > 0) {
                    $syncParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Product_Options__c";
                    $this->_obj->$syncParam = $_prefix . join("", $opt) . '</tbody></table>';
                    $this->_obj->Description = join(", ", $_summary);
                    if (strlen($this->_obj->Description) > 200) {
                        $this->_obj->Description = substr($this->_obj->Description, 0, 200) . '...';
                    }
                }

                $this->_obj->Quantity = $_item->getQtyOrdered();

                $this->_cache['opportunityLineItemsToUpsert']['cart_' . $_item->getId()] = $this->_obj;

                /* Dump OpportunityLineItem object into the log */
                foreach ($this->_obj as $key => $_item) {
                    Mage::helper('tnw_salesforce')->log("OpportunityLineItem Object: " . $key . " = '" . $_item . "'");
                }


                Mage::helper('tnw_salesforce')->log('-----------------');
            }
        }
        Mage::helper('tnw_salesforce')->log('----------Prepare Cart Items: End----------');
    }

    public function getProductIdFromCart($_item) {
        $_options = unserialize($_item->getData('product_options'));
        if(
            $_item->getData('product_type') == 'bundle'
            || (is_array($_options) && array_key_exists('options', $_options))
        ) {
            $id = $_item->getData('product_id');
        } else {
            $id = (int)Mage::getModel('catalog/product')->getIdBySku($_item->getSku());
        }
        return $id;
    }

    protected function addTaxProduct($_order, $_orderNumber)
    {
        $_storeId = $_order->getStoreId();
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            if ($_order->getData('order_currency_code') != $_order->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($_order->getData('order_currency_code'));
            }
        }
        $this->_obj = new stdClass();
        $_helper = Mage::helper('tnw_salesforce');
        $_taxProductPricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::ORDER_TAX_PRODUCT);

        $_cartItemFound = false;
        if (
            is_array($this->_cache['opportunityLookup']) &&
            array_key_exists($_orderNumber, $this->_cache['opportunityLookup']) &&
            property_exists($this->_cache['opportunityLookup'][$_orderNumber], 'OpportunityLineItems')
            && is_object($this->_cache['opportunityLookup'][$_orderNumber]->OpportunityLineItems)
            && property_exists($this->_cache['opportunityLookup'][$_orderNumber]->OpportunityLineItems, 'records')
        ) {
            foreach ($this->_cache['opportunityLookup'][$_orderNumber]->OpportunityLineItems->records as $_cartItem) {
                if ($_cartItem->PricebookEntryId == $_taxProductPricebookEntryId) {
                    $_cartItemFound = $_cartItem->Id;
                    break;
                }
            }
        }

        if ($_cartItemFound) {
            $this->_obj->Id = $_cartItemFound;
        }

        $this->_obj->OpportunityId = $this->_cache['upsertedOpportunities'][$_orderNumber];
        $this->_obj->UnitPrice = number_format(($_order->getTaxAmount()), 2, ".", "");
        if (!property_exists($this->_obj, "Id")) {
            if ($_order->getData('order_currency_code') != $_order->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($_order->getData('order_currency_code'));
            } else {
                $_storeId = $_order->getStoreId();
            }

            $this->_obj->PricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::ORDER_TAX_PRODUCT);
        }
        //$defaultServiceDate = Mage::helper('tnw_salesforce/shipment')->getDefaultServiceDate();
        //if ($defaultServiceDate) {
        //    $this->_obj->ServiceDate = $defaultServiceDate;
        //}
        $this->_obj->Description = 'Total Tax';
        $this->_obj->Quantity = 1;

        /* Dump OpportunityLineItem object into the log */
        foreach ($this->_obj as $key => $_item) {
            Mage::helper('tnw_salesforce')->log("OpportunityLineItem Object: " . $key . " = '" . $_item . "'");
        }

        $this->_cache['opportunityLineItemsToUpsert'][] = $this->_obj;
        Mage::helper('tnw_salesforce')->log('-----------------');
    }

    /**
     * @param $_order
     * @param $_orderNumber
     * Prepare Shipping fee to Saleforce order
     */
    protected function addDiscountProduct($_order, $_orderNumber)
    {
        $_storeId = $_order->getStoreId();
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            if ($_order->getData('order_currency_code') != $_order->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($_order->getData('order_currency_code'));
            }
        }
        // Add Shipping Fee to the order
        $this->_obj = new stdClass();

        $_helper = Mage::helper('tnw_salesforce');
        $_discountProductPricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::ORDER_DISCOUNT_PRODUCT);

        $_cartItemFound = false;
        if (
            is_array($this->_cache['opportunityLookup']) &&
            array_key_exists($_orderNumber, $this->_cache['opportunityLookup']) &&
            property_exists($this->_cache['opportunityLookup'][$_orderNumber], 'OpportunityLineItems')
            && is_object($this->_cache['opportunityLookup'][$_orderNumber]->OpportunityLineItems)
            && property_exists($this->_cache['opportunityLookup'][$_orderNumber]->OpportunityLineItems, 'records')
        ) {
            foreach ($this->_cache['opportunityLookup'][$_orderNumber]->OpportunityLineItems->records as $_cartItem) {
                if ($_cartItem->PricebookEntryId == $_discountProductPricebookEntryId) {
                    $_cartItemFound = $_cartItem->Id;
                    break;
                }
            }
        }
        if ($_cartItemFound) {
            $this->_obj->Id = $_cartItemFound;
        }

        $this->_obj->OpportunityId = $this->_cache['upsertedOpportunities'][$_orderNumber];
        $this->_obj->UnitPrice = number_format(($_order->getData('discount_amount')), 2, ".", "");
        if (!property_exists($this->_obj, "Id")) {
            if ($_order->getData('order_currency_code') != $_order->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($_order->getData('order_currency_code'));
            } else {
                $_storeId = $_order->getStoreId();
            }

            $this->_obj->PricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::ORDER_DISCOUNT_PRODUCT);
        }
        $this->_obj->Description = 'Discount';
        $this->_obj->Quantity = 1;

        /* Dump OrderItem object into the log */
        foreach ($this->_obj as $key => $_item) {
            Mage::helper('tnw_salesforce')->log("OpportunityLineItem Object: " . $key . " = '" . $_item . "'");
        }
        $this->_cache['opportunityLineItemsToUpsert'][] = $this->_obj;
        Mage::helper('tnw_salesforce')->log('-----------------');
    }

    protected function addShippingProduct($_order, $_orderNumber)
    {
        $_storeId = $_order->getStoreId();
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            if ($_order->getData('order_currency_code') != $_order->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($_order->getData('order_currency_code'));
            }
        }
        // Add Shipping
        $this->_obj = new stdClass();

        $_helper = Mage::helper('tnw_salesforce');
        $_shippingProductPricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::ORDER_SHIPPING_PRODUCT);

        $_cartItemFound = false;
        if (
            is_array($this->_cache['opportunityLookup']) &&
            array_key_exists($_orderNumber, $this->_cache['opportunityLookup']) &&
            property_exists($this->_cache['opportunityLookup'][$_orderNumber], 'OpportunityLineItems')
            && is_object($this->_cache['opportunityLookup'][$_orderNumber]->OpportunityLineItems)
            && property_exists($this->_cache['opportunityLookup'][$_orderNumber]->OpportunityLineItems, 'records')
        ) {
            foreach ($this->_cache['opportunityLookup'][$_orderNumber]->OpportunityLineItems->records as $_cartItem) {
                if ($_cartItem->PricebookEntryId == $_shippingProductPricebookEntryId) {
                    $_cartItemFound = $_cartItem->Id;
                    break;
                }
            }
        }
        if ($_cartItemFound) {
            $this->_obj->Id = $_cartItemFound;
        }

        $this->_obj->OpportunityId = $this->_cache['upsertedOpportunities'][$_orderNumber];
        $this->_obj->UnitPrice = number_format(($_order->getShippingAmount()), 2, ".", "");
        if (!property_exists($this->_obj, "Id")) {
            //$_currentStoreId = Mage::app()->getStore()->getStoreId();
            if ($_order->getData('order_currency_code') != $_order->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($_order->getData('order_currency_code'));
            } else {
                $_storeId = $_order->getStoreId();
            }

            $this->_obj->PricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::ORDER_SHIPPING_PRODUCT);
        }
        //$defaultServiceDate = Mage::helper('tnw_salesforce/shipment')->getDefaultServiceDate();
        //if ($defaultServiceDate) {
        //    $this->_obj->ServiceDate = $defaultServiceDate;
        //}
        $this->_obj->Description = 'Shipping & Handling';
        $this->_obj->Quantity = 1;

        /* Dump OpportunityLineItem object into the log */
        foreach ($this->_obj as $key => $_item) {
            Mage::helper('tnw_salesforce')->log("OpportunityLineItem Object: " . $key . " = '" . $_item . "'");
        }
        $this->_cache['opportunityLineItemsToUpsert'][] = $this->_obj;
        Mage::helper('tnw_salesforce')->log('-----------------');
    }

    protected function doesCartItemExistInOpportunity($_orderNumber, $_item, $_sku)
    {
        $_cartItemFound = false;
        if ($this->_cache['opportunityLookup'] && array_key_exists($_orderNumber, $this->_cache['opportunityLookup']) && $this->_cache['opportunityLookup'][$_orderNumber]->OpportunityLineItems) {
            foreach ($this->_cache['opportunityLookup'][$_orderNumber]->OpportunityLineItems->records as $_cartItem) {
                if (
                    property_exists($_cartItem, 'PricebookEntry')
                    && property_exists($_cartItem->PricebookEntry, 'ProductCode')
                    && $_cartItem->PricebookEntry->ProductCode == trim($_sku)
                    && $_cartItem->Quantity == (float)$_item->getQtyOrdered()
                ) {
                    $_cartItemFound = $_cartItem->Id;
                    break;
                }
            }
        }
        return $_cartItemFound;
    }

    protected function _prepareNotes()
    {
        Mage::helper('tnw_salesforce')->log('----------Prepare Notes: Start----------');

        // Get all products from each order and decide if all needs to me synced prior to inserting them
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (in_array($_orderNumber, $this->_cache['failedOpportunities'])) {
                Mage::helper('tnw_salesforce')->log('ORDER (' . $_orderNumber . '): Skipping, issues with upserting an opportunity!');
                continue;
            }
            $_order = (Mage::registry('order_cached_' . $_orderNumber)) ? Mage::registry('order_cached_' . $_orderNumber) : Mage::getModel('sales/order')->loadByIncrementId($_orderNumber);

            // TODO: need to add this feature
            foreach($_order->getAllStatusHistory() as $_note) {
                // Only sync notes for the order
                if ($_note->getData('entity_name') == 'order' &&  !$_note->getData('salesforce_id') && $_note->getData('comment')) {
                    $this->_obj = new stdClass();
                    $this->_obj->ParentId = $this->_cache['upsertedOpportunities'][$_orderNumber];
                    //$note->OwnerId = $customerId; //Needs to be Salesforce User
                    $this->_obj->IsPrivate = 0;
                    $this->_obj->Body = $_note->getData('comment');
                    $this->_obj->Title = $_note->getData('comment');

                    if (strlen($this->_obj->Title) > 75) {
                        $this->_obj->Title = substr($_note->getData('comment'), 0, 75) . '...';
                    } else {
                        $this->_obj->Title = $_note->getData('comment');
                    }
                    $this->_cache['notesToUpsert'][$_note->getData('entity_id')] = $this->_obj;

                    foreach ($this->_obj as $key => $_value) {
                        Mage::helper('tnw_salesforce')->log("Note Object: " . $key . " = '" . $_value . "'");
                    }
                    Mage::helper('tnw_salesforce')->log('+++++++++++++++++++++++++++++');
                }
            }
        }
        Mage::helper('tnw_salesforce')->log('----------Prepare Notes: End----------');
    }

    protected function _prepareContactRoles()
    {
        Mage::helper('tnw_salesforce')->log('----------Prepare Opportunity Contact Role: Start----------');
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (in_array($_orderNumber, $this->_cache['failedOpportunities'])) {
                Mage::helper('tnw_salesforce')->log('ORDER (' . $_orderNumber . '): Skipping, issues with upserting an opportunity!');
                continue;
            }
            Mage::helper('tnw_salesforce')->log('******** ORDER (' . $_orderNumber . ') ********');
            $_customerId = $this->_cache['orderToCustomerId'][$_orderNumber];
            if (!Mage::registry('customer_cached_' . $_customerId)) {
                $_customer = $this->_cache['orderCustomers'][$_orderNumber];
            } else {
                $_customer = Mage::registry('customer_cached_' . $_customerId);
            }

            $this->_obj = new stdClass();
            $_order = Mage::getModel('sales/order')->load($_key);
            $_email = strtolower($_customer->getEmail());
            $_websiteId = ($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getData('website_id')) ? $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getData('website_id') : Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();

            if (
                (bool)$_customer->getSalesforceIsPerson()
                || (array_key_exists('accountsLookup', $this->_cache)
                    && is_array($this->_cache['accountsLookup'])
                    && array_key_exists($_websiteId, $this->_cache['accountsLookup'])
                    && array_key_exists($_email, $this->_cache['accountsLookup'][$_websiteId])
                    && is_object($this->_cache['accountsLookup'][$_websiteId][$_email])
                    && property_exists($this->_cache['accountsLookup'][$_websiteId][$_email], 'IsPersonAccount')
                )
            ) {
                $this->_obj->ContactId = (
                    is_array($this->_cache['accountsLookup'])
                    && array_key_exists($_websiteId, $this->_cache['accountsLookup'])
                    && is_array($this->_cache['accountsLookup'][$_websiteId])
                    && array_key_exists($_email, $this->_cache['accountsLookup'][$_websiteId])
                    && is_object($this->_cache['accountsLookup'][$_websiteId][$_email])
                    && property_exists($this->_cache['accountsLookup'][$_websiteId][$_email], 'Id')
                ) ? $this->_cache['accountsLookup'][$_websiteId][$_email]->Id : $_customer->getSalesforceId();
            } else {
                $this->_obj->ContactId = $_customer->getSalesforceId();
            }

            // Check if already exists
            $_roleFound = $_skip = false;
            if ($this->_cache['opportunityLookup'] && array_key_exists($_orderNumber, $this->_cache['opportunityLookup']) && $this->_cache['opportunityLookup'][$_orderNumber]->OpportunityContactRoles) {
                foreach ($this->_cache['opportunityLookup'][$_orderNumber]->OpportunityContactRoles->records as $_role) {
                    if ($_role->ContactId == $this->_obj->ContactId) {
                        if ($_role->Role == Mage::helper('tnw_salesforce')->getDefaultCustomerRole()) {
                            // No update required
                            Mage::helper('tnw_salesforce')->log('Contact Role information is the same, no update required!');
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
                $this->_obj->OpportunityId = $this->_cache['upsertedOpportunities'][$_orderNumber];

                $this->_obj->Role = Mage::helper('tnw_salesforce')->getDefaultCustomerRole();

                foreach ($this->_obj as $key => $_item) {
                    Mage::helper('tnw_salesforce')->log("OpportunityContactRole Object: " . $key . " = '" . $_item . "'");
                }

                if ($this->_obj->ContactId) {
                    $this->_cache['contactRolesToUpsert'][] = $this->_obj;
                } else {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('Was not able to convert customer Lead, skipping Opportunity Contact Role assignment. Please synchronize customer (email: ' . $this->_cache['orderCustomers'][$_orderNumber]->getEmail() . ')');
                    }
                }
            }
        }
        Mage::helper('tnw_salesforce')->log('----------Prepare Opportunity Contact Role: End----------');
    }

    protected function _pushOpportunityLineItems($chunk = array())
    {
        $_orderNumbers = array_flip($this->_cache['upsertedOpportunities']);
        $_chunkKeys = array_keys($chunk);
        try {
            $results = $this->_mySforceConnection->upsert("Id", array_values($chunk), 'OpportunityLineItem');
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach($chunk as $_object) {
                $this->_cache['responses']['opportunityLineItems'][] = $_response;
            }
            $results = array();
            Mage::helper('tnw_salesforce')->log('CRITICAL: Push of Opportunity Line Items to SalesForce failed' . $e->getMessage());
        }

        $this->_cache['responses']['opportunityLineItems'] = $results;

        $_sql = "";
        foreach ($results as $_key => $_result) {
            $_orderNum = $_orderNumbers[$this->_cache['opportunityLineItemsToUpsert'][$_chunkKeys[$_key]]->OpportunityId];

            //Report Transaction
            $this->_cache['responses']['opportunityLineItems'][] = $_result;

            if (!$_result->success) {
                // Reset sync status
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 0 WHERE salesforce_id = '" . $this->_cache['opportunityLineItemsToUpsert'][$_chunkKeys[$_key]]->OpportunityId . "';";
                Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
                $this->_write->query($sql . ' commit;');

                Mage::helper('tnw_salesforce')->log('ERROR: One of the Cart Item for (order: ' . $_orderNum . ') failed to upsert.', 1, "sf-errors");
                $this->_processErrors($_result, 'orderCart', $chunk[$_chunkKeys[$_key]]);
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('Failed to upsert one of the Cart Item for Order #' . $_orderNum);
                }
            } else {
                $_cartItemId = $_chunkKeys[$_key];
                if ($_cartItemId && strrpos($_cartItemId, 'cart_', -strlen($_cartItemId)) !== FALSE) {
                    $_sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_item') . "` SET salesforce_id = '" . $_result->id . "' WHERE item_id = '" . str_replace('cart_','',$_cartItemId) . "';";
                }
                Mage::helper('tnw_salesforce')->log('Cart Item (id: ' . $_result->id . ') for (order: ' . $_orderNum . ') upserted.');
            }
        }
        if (!empty($_sql)) {
            Mage::helper('tnw_salesforce')->getDbConnection()->query($_sql);
        }
    }

    protected function _pushRemainingOpportunityData()
    {
        if (!empty($this->_cache['opportunityLineItemsToUpsert'])) {
            Mage::helper('tnw_salesforce')->log('----------Push Cart Items: Start----------');

            Mage::dispatchEvent("tnw_salesforce_order_products_send_before",array("data" => $this->_cache['opportunityLineItemsToUpsert']));

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

            Mage::dispatchEvent("tnw_salesforce_order_products_send_after",array(
                "data" => $this->_cache['opportunityLineItemsToUpsert'],
                "result" => $this->_cache['responses']['opportunityLineItems']
            ));

            Mage::helper('tnw_salesforce')->log('----------Push Cart Items: End----------');
        }

        if (!empty($this->_cache['contactRolesToUpsert'])) {
            Mage::helper('tnw_salesforce')->log('----------Push Contact Roles: Start----------');

            Mage::dispatchEvent("tnw_salesforce_order_contact_roles_send_before",array("data" => $this->_cache['contactRolesToUpsert']));

            // Push Contact Roles
            try {
                $results = $this->_mySforceConnection->upsert("Id", $this->_cache['contactRolesToUpsert'], 'OpportunityContactRole');
            } catch (Exception $e) {
                $_response = $this->_buildErrorResponse($e->getMessage());
                foreach($this->_cache['contactRolesToUpsert'] as $_object) {
                    $this->_cache['responses']['customerRoles'][] = $_response;
                }
                $results = array();
                Mage::helper('tnw_salesforce')->log('CRITICAL: Push of contact roles to SalesForce failed' . $e->getMessage());
            }

            $this->_cache['responses']['customerRoles'] = $results;

            $_orderNumbers = array_flip($this->_cache['upsertedOpportunities']);
            foreach ($results as $_key => $_result) {
                $_orderNum = $_orderNumbers[$this->_cache['contactRolesToUpsert'][$_key]->OpportunityId];

                //Report Transaction
                $this->_cache['responses']['opportunityCustomerRoles'][] = $_result;

                if (!$_result->success) {
                    // Reset sync status
                    $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 0 WHERE salesforce_id = '" . $this->_cache['contactRolesToUpsert'][$_key]->OpportunityId . "';";
                    Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
                    $this->_write->query($sql . ' commit;');

                    Mage::helper('tnw_salesforce')->log('ERROR: Contact Role (role: ' . $this->_cache['contactRolesToUpsert'][$_key]->Role . ') for (order: ' . $_orderNum . ') failed to upsert.', 1, "sf-errors");
                    $this->_processErrors($_result, 'orderCart', $this->_cache['contactRolesToUpsert'][$_key]);
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Failed to upsert Contact Role (' . $this->_cache['contactRolesToUpsert'][$_key]->Role . ') for Order #' . $_orderNum);
                    }
                } else {
                    Mage::helper('tnw_salesforce')->log('Contact Role (role: ' . $this->_cache['contactRolesToUpsert'][$_key]->Role . ') for (order: ' . $_orderNum . ') upserted.');
                }
            }

            Mage::dispatchEvent("tnw_salesforce_order_contact_roles_send_after",array(
                "data" => $this->_cache['contactRolesToUpsert'],
                "result" => $this->_cache['responses']['customerRoles']
            ));

            Mage::helper('tnw_salesforce')->log('----------Push Contact Roles: End----------');
        }

        // Push Notes
        if (!empty($this->_cache['notesToUpsert'])) {
            Mage::helper('tnw_salesforce')->log('----------Push Notes: Start----------');

            Mage::dispatchEvent("tnw_salesforce_order_notes_send_before",array("data" => $this->_cache['notesToUpsert']));

            // Push Cart
            $_ttl = count($this->_cache['notesToUpsert']);
            if ($_ttl > 199) {
                $_steps = ceil($_ttl / 199);
                for ($_i = 0; $_i < $_steps; $_i++) {
                    $_start = $_i * 200;
                    $_itemsToPush = array_slice($this->_cache['notesToUpsert'], $_start, $_start + 199);
                    $this->_pushNotes($_itemsToPush);
                }
            } else {
                $this->_pushNotes($this->_cache['notesToUpsert']);
            }

            Mage::dispatchEvent("tnw_salesforce_order_notes_send_after",array(
                "data" => $this->_cache['notesToUpsert'],
                "result" => $this->_cache['responses']['notes']
            ));

            Mage::helper('tnw_salesforce')->log('----------Push Notes: End----------');
        }

        // Kick off the event to allow additional data to be pushed into salesforce
        Mage::dispatchEvent("tnw_salesforce_order_sync_after_final",array(
            "all" => $this->_cache['entitiesUpdating'],
            "failed" => $this->_cache['failedOpportunities']
        ));
    }

    /**
     * @param array $ids
     * @param bool $_isCron
     * @return bool
     */
    public function massAdd($_id = NULL, $_isCron = false)
    {
        if (!$_id) {
            Mage::helper('tnw_salesforce')->log("Order Id is not specified, don't know what to synchronize!");
            return;
        }
        // test sf api connection
        $_client = Mage::getSingleton('tnw_salesforce/connection');
        if (!$_client->tryWsdl()
            || !$_client->tryToConnect()
            || !$_client->tryToLogin()) {
            Mage::helper('tnw_salesforce')->log("error on sync orders, sf api connection failed");

            return true;
        }
        try {
            $this->_isCron = $_isCron;

            // Clear Opportunity ID
            $this->resetOrder($_id);

            // Load order by ID
            $_order = Mage::getModel('sales/order')->load($_id);
            // Add to cache
            if (!Mage::registry('order_cached_' . $_order->getRealOrderId())) {
                Mage::register('order_cached_' . $_order->getRealOrderId(), $_order);
            } else {
                Mage::unregister('order_cached_' . $_order->getRealOrderId());
                Mage::register('order_cached_' . $_order->getRealOrderId(), $_order);
            }

            /**
             * @comment check zero orders sync
             */
            if (!Mage::helper('tnw_salesforce/order')->isEnabledZeroOrderSync() && $_order->getGrandTotal() == 0) {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for order #' . $_order->getRealOrderId() . ', grand total is zero and synchronization for these order is disabled in configuration!');
                }
                Mage::helper("tnw_salesforce")->log('SKIPPED: Sync for order #' . $_order->getRealOrderId() . ', grand total is zero and synchronization for these order is disabled in configuration!');
                return;
            }

            if (
                !Mage::helper('tnw_salesforce')->syncAllOrders()
                && !in_array($_order->getStatus(), $this->_allowedOrderStatuses)
            ) {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for order #' . $_order->getId() . ', sync for order status "' . $_order->getStatus() . '" is disabled!');
                }
                Mage::helper("tnw_salesforce")->log('SKIPPED: Sync for order #' . $_order->getId() . ', sync for order status "' . $_order->getStatus() . '" is disabled!');
                return;
            }
            // Order could not be loaded for some reason
            if (!$_order->getId() || !$_order->getRealOrderId()) {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Sync for order #' . $_id . ', order could not be loaded!');
                }
                Mage::helper("tnw_salesforce")->log("SKIPPING: Sync for order #" . $_id . ", order could not be loaded!", 1, "sf-errors");
                return;
            }

            // Get Magento customer object
            $this->_cache['orderCustomers'][$_order->getRealOrderId()] = $this->_getCustomer($_order);
            // Associate order Number with a customer ID
            $this->_cache['orderToCustomerId'][$_order->getRealOrderId()] = ($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId()) ? $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId() : 'guest-0';

            // Associate order Number with a customer Email
            $this->_cache['orderToEmail'][$_order->getRealOrderId()] = $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getEmail();

            // Check if customer from this group is allowed to be synchronized
            $_customerGroup = $_order->getData('customer_group_id');
            if ($_customerGroup === NULL) {
                $_customerGroup = $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getGroupId();
            }
            if ($_customerGroup === NULL && !$this->isFromCLI()) {
                $_customerGroup = Mage::getSingleton('customer/session')->getCustomerGroupId();
            }
            if (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($_customerGroup)) {
                Mage::helper("tnw_salesforce")->log("SKIPPING: Sync for customer group #" . $_customerGroup . " is disabled!", 1, "sf-errors");
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for order #' . $_order->getId() . ', sync for customer group #' . $_customerGroup . ' is disabled!');
                }
                return;
            }

            // Store order number and customer Email into a variable for future use
            $_orderEmail = ($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getEmail()) ? strtolower($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getEmail()) : strtolower($_order->getCustomerEmail());
            $_customerId = ($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId()) ? $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId() : 'guest-0';
            $_websiteId = ($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getData('website_id')) ? $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getData('website_id') : Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
            $_orderNumber = $_order->getRealOrderId();

            if (empty($_orderEmail)) {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::helper("tnw_salesforce")->log("SKIPPED: Sync for order #' . $_orderNumber . ' failed, order is missing an email address!");
                    Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for order #' . $_orderNumber . ' failed, order is missing an email address!');
                }
                return;
            }

            // Force sync of the customer if Account Rename is turned on
            if (Mage::helper('tnw_salesforce')->canRenameAccount()) {
                Mage::helper("tnw_salesforce")->log('Syncronizing Guest/New customer...');
                $manualSync = Mage::helper('tnw_salesforce/salesforce_customer');
                if ($manualSync->reset()) {
                    $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
                    $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());

                    $manualSync->forceAdd($this->_cache['orderCustomers'][$_order->getRealOrderId()]);
                    set_time_limit(30);
                    $this->_cache['orderCustomers'][$_orderNumber] = $manualSync->process(true);
                    set_time_limit(30);
                }
            }

            // Associate order ID with order Number
            $this->_cache['entitiesUpdating'] = array($_id => $_orderNumber);
            // Salesforce lookup, find all contacts/accounts by email address
            $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup(array($_customerId => $_orderEmail), array($_customerId => $this->_websiteSfIds[$_websiteId]));
            // Salesforce lookup, find all opportunities by Magento order number
            $this->_cache['opportunityLookup'] = Mage::helper('tnw_salesforce/salesforce_data')->opportunityLookup($this->_cache['entitiesUpdating']);

            // Check if we need to look for a Lead, since customer Contact/Account could not be found
            $_leadsToLookup = NULL;
            $_customerToSync = NULL;
            if (!is_array($this->_cache['accountsLookup'])
                || !array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
                || !array_key_exists($_orderEmail, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])) {
                $_order = Mage::registry('order_cached_' . $_orderNumber);
                $_leadsToLookup[$_customerId] = $_orderEmail;
                $this->_cache['orderCustomersToSync'][] = $_orderNumber;
            }

            // If customer exists as a Lead
            if ($_leadsToLookup) {
                $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($_leadsToLookup, array($_customerId => $this->_websiteSfIds[$_websiteId]));
                // If Lead is converted, update the lookup data
                $this->_cache['orderCustomers'][$_order->getRealOrderId()] = $this->_updateAccountLookupData($this->_cache['orderCustomers'][$_order->getRealOrderId()]);

                $_foundAccounts = array();
                // If Lead not found, potentially a guest
                if (!is_array($this->_cache['leadLookup']) || !array_key_exists($_websiteId, $this->_cache['leadLookup']) || !array_key_exists($_orderEmail, $this->_cache['leadLookup'][$_websiteId])) {
                    Mage::helper("tnw_salesforce")->log('Syncronizing Guest/New customer...');
                    $manualSync = Mage::helper('tnw_salesforce/salesforce_customer');
                    if ($manualSync->reset()) {
                        $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
                        $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());

                        $manualSync->forceAdd($this->_cache['orderCustomers'][$_order->getRealOrderId()]);
                        set_time_limit(30);
                        $this->_cache['orderCustomers'][$_orderNumber] = $manualSync->process(true);
                        set_time_limit(30);

                        // Returns Email to Account association so we don't create duplicate Accounts
                        $_foundAccounts = $manualSync->getCustomerAccounts();
                    }
                    Mage::helper("tnw_salesforce")->log('Updating lookup cache...');
                    // update Lookup values
                    $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup(array($_customerId => $_orderEmail), array($_customerId => $this->_websiteSfIds[$_websiteId]));
                    if (!is_array($this->_cache['accountsLookup'])
                        || !array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
                        || !array_key_exists($_orderEmail, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])) {
                        $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($_leadsToLookup, array($_customerId => $this->_websiteSfIds[$_websiteId]));
                        // If Lead is converted, update the lookup data
                        $this->_cache['orderCustomers'][$_order->getRealOrderId()] = $this->_updateAccountLookupData($this->_cache['orderCustomers'][$_order->getRealOrderId()]);
                    }
                }

                if (is_array($this->_cache['leadLookup'])
                    && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['leadLookup'])
                    && array_key_exists($_orderEmail, $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]])) {
                    // Need to convert a Lead
                    $_queueList = Mage::helper('tnw_salesforce/salesforce_data_queue')->getAllQueues();
                    $this->_prepareLeadConversionObject($_orderNumber, $_foundAccounts, $_queueList);
                    Mage::helper("tnw_salesforce")->log('SUCCESS: Automatic customer Lead prepared to be converted.');
                } elseif (is_array($this->_cache['accountsLookup'])
                    && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
                    && array_key_exists($_orderEmail, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])) {
                    // Found Contact & Account
                    Mage::helper("tnw_salesforce")->log('SUCCESS: Automatic customer synchronization.');
                } else {
                    // Something is wrong, could not create / find Magento customer in SalesForce
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for order #' . $_order->getId() . ', could not locate / create Magento customer (' . $_orderEmail . ') in Salesforce!');
                    }
                    Mage::helper("tnw_salesforce")->log('CRITICAL ERROR: Contact or Lead for Magento customer (' . $_orderEmail . ') could not be created / found!', 1, "sf-errors");
                    return false;
                }
            } else {
                if (is_array($this->_cache['accountsLookup'])
                    && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
                    && array_key_exists($_orderEmail, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])) {
                    $this->_cache['orderCustomers'][$_orderNumber]->setSalesforceId($this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_orderEmail]->Id);
                    $this->_cache['orderCustomers'][$_orderNumber]->setSalesforceAccountId($this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_orderEmail]->AccountId);
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

    /**
     * @param $_orderNumber
     * @param array $_accounts
     * @return bool
     */
    protected function _prepareLeadConversionObject($_orderNumber, $_accounts = array(), $_queueList = NULL)
    {
        if (!Mage::helper("tnw_salesforce")->getLeadConvertedStatus()) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: Converted Lead status is not set in the configuration, cannot proceed!');
            }
            Mage::helper("tnw_salesforce")->log('Converted Lead status is not set in the configuration, cannot proceed!', 1, "sf-errors");
            return false;
        }

        $_email = strtolower($this->_cache['orderToEmail'][$_orderNumber]);
        $_order = Mage::getModel('sales/order')->loadByIncrementId($_orderNumber);;
        $_websiteId = Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
        $_salesforceWebsiteId = $this->_websiteSfIds[$_websiteId];

        if (is_array($this->_cache['leadLookup'])
            && array_key_exists($_salesforceWebsiteId, $this->_cache['leadLookup'])
            && array_key_exists($_email, $this->_cache['leadLookup'][$_salesforceWebsiteId])) {
            $leadConvert = new stdClass;
            $leadConvert->convertedStatus = Mage::helper("tnw_salesforce")->getLeadConvertedStatus();
            $leadConvert->doNotCreateOpportunity = 'true';
            $leadConvert->leadId = $this->_cache['leadLookup'][$_salesforceWebsiteId][$_email]->Id;
            $leadConvert->overwriteLeadSource = 'false';
            $leadConvert->sendNotificationEmail = 'false';

            // Retain OwnerID if Lead is already assigned
            // If not, pull default Owner from Magento configuration
            if (
                is_object($this->_cache['leadLookup'][$_salesforceWebsiteId][$_email])
                && property_exists($this->_cache['leadLookup'][$_salesforceWebsiteId][$_email], 'OwnerId')
                && $this->_cache['leadLookup'][$_salesforceWebsiteId][$_email]->OwnerId
                && (
                    is_array($_queueList)
                    && !in_array($this->_cache['leadLookup'][$_salesforceWebsiteId][$_email]->OwnerId, $_queueList)
                )
            ) {
                $leadConvert->ownerId = $this->_cache['leadLookup'][$_salesforceWebsiteId][$_email]->OwnerId;
            } elseif (!array_key_exists($_salesforceWebsiteId, $this->_cache['leadLookup'])) {

            } elseif (Mage::helper('tnw_salesforce')->getLeadDefaultOwner()) {
                $leadConvert->ownerId = Mage::helper('tnw_salesforce')->getLeadDefaultOwner();
            }

            // If inactive, reassign
            if (!$this->_isUserActive($leadConvert->ownerId)) {
                $leadConvert->ownerId = Mage::helper('tnw_salesforce')->getLeadDefaultOwner();
            }

            // Attach to existing account
            if (array_key_exists($_email, $_accounts) && $_accounts[$_email]) {
                $leadConvert->accountId = $_accounts[$_email];
            }
            // logs
            foreach ($leadConvert as $key => $value) {
                Mage::helper('tnw_salesforce')->log("Lead Conversion: " . $key . " = '" . $value . "'");
            }

            if ($leadConvert->leadId && !$this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->IsConverted) {
                $this->_cache['leadsToConvert'][$_orderNumber] = $leadConvert;
            } else {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Order #' . $_orderNumber . ' - customer (email: ' . $this->_cache['orderCustomers'][$_orderNumber]->getEmail() . ') needs to be synchronized first, aborting!');
                }
                Mage::helper("tnw_salesforce")->log('Order #' . $_orderNumber . ' - customer (email: ' . $this->_cache['orderCustomers'][$_orderNumber]->getEmail() . ') needs to be synchronized first, aborting!', 1, "sf-errors");
                return false;
            }
        }
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
        $_customer = $this->_cache['orderCustomers'][$_orderNumber];

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

        //$syncParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . "disableMagentoSync__c";
        //$this->_obj->$syncParam = true;

        // Magento Order ID
        $this->_obj->{$this->_magentoId} = $_orderNumber;

        // Use existing Opportunity if creating from Quote
        $modules = Mage::getConfig()->getNode('modules')->children();
        if (
            property_exists($modules, 'Ophirah_Qquoteadv')
            && (string)$modules->Ophirah_Qquoteadv->active == "true"
            && $order->getData('c2q_internal_quote_id')
        ) {
            Mage::helper('tnw_salesforce')->log("Quote Id: " . $order->getData('c2q_internal_quote_id'));
            $_quote = Mage::getModel('qquoteadv/qqadvcustomer')->load($order->getData('c2q_internal_quote_id'));
            Mage::helper('tnw_salesforce')->log("Opportunity Id: " . $_quote->getData('opportunity_id'));
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
            && array_key_exists($_customer->getEmail(), $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])
            && $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_customer->getEmail()]->AccountName
        ) ? $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_customer->getEmail()]->AccountName : NULL;
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

    protected function _assignPricebookToOrder($_order)
    {
        try {
            $_storeId = $_order->getStoreId();
            $_helper = Mage::helper('tnw_salesforce');
            if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
                if ($_order->getData('order_currency_code') != $_order->getData('store_currency_code')) {
                    $_storeId = $this->_getStoreIdByCurrency($_order->getData('order_currency_code'));
                }
            }

            $this->_obj->Pricebook2Id = Mage::app()->getStore($_storeId)->getConfig($_helper::PRODUCT_PRICEBOOK);

        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("INFO: Could not load pricebook based on the order ID. Loading default pricebook based on current store ID.");
            Mage::helper('tnw_salesforce')->log("ERROR: " . $e->getMessage());
            if ($this->_defaultPriceBook) {
                $this->_obj->Pricebook2Id = $this->_defaultPriceBook;
            }
        }
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
     * @param array $chunk
     * push Notes chunk into Salesforce
     */
    protected function _pushNotes($chunk = array())
    {
        $_noteIds = array_keys($this->_cache['notesToUpsert']);

        try {
            $results = $this->_mySforceConnection->upsert("Id", array_values($chunk), 'Note');
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach($chunk as $_object) {
                $this->_cache['responses']['notes'][] = $_response;
            }
            $results = array();
            Mage::helper('tnw_salesforce')->log('CRITICAL: Push of Notes to SalesForce failed' . $e->getMessage());
        }

        $sql = "";

        foreach ($results as $_key => $_result) {
            $_noteId = $_noteIds[$_key];

            //Report Transaction
            $this->_cache['responses']['notes'][$_noteId] = $_result;

            if (!$_result->success) {
                Mage::helper('tnw_salesforce')->log('ERROR: Note (id: ' . $_noteId . ') failed to upsert', 1, "sf-errors");
                $this->_processErrors($_result, 'orderNote', $chunk[$_noteId]);

                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('Note (id: ' . $_noteId . ') failed to upsert');
                }
            } else {
                $_orderSalesforceId = $this->_cache['notesToUpsert'][$_noteId]->ParentId;
                $_orderId = array_search($_orderSalesforceId, $this->_cache['upsertedOpportunities']);

                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_status_history') . "` SET salesforce_id = '" . $_result->id . "' WHERE entity_id = '" . $_noteId . "';";
                Mage::helper('tnw_salesforce')->log('Note (id: ' . $_noteId . ') upserted for order #' . $_orderId . ')');
            }
        }

        if (!empty($sql)) {
            Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
            $this->_write->query($sql . ' commit;');
        }
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

        Mage::helper('tnw_salesforce')->log("Mapping status: " . $order->getStatus());

        $this->_obj->StageName = 'Committed'; // if $collection is empty then we had error "CRITICAL: Failed to upsert order: Required fields are missing: [StageName]"
        foreach ($collection as $_item) {
            $this->_obj->StageName = ($_item->getSfOpportunityStatusCode()) ? $_item->getSfOpportunityStatusCode() : 'Committed';

            Mage::helper('tnw_salesforce')->log("Order status: " . $this->_obj->StageName);
            break;
        }
        unset($collection, $_item);
    }

    /**
     * Sync customer w/ SF before creating the order
     *
     * @param $order
     * @return false|Mage_Customer_Model_Customer
     */
    protected function _getCustomer($order)
    {
        $customer_id = $order->getCustomerId();
        if (!$customer_id && !$this->isFromCLI()) {
            Mage::getSingleton('customer/session')->getCustomerId();
        }

        if ($customer_id) {
            $_customer = Mage::getModel("customer/customer");
            if (Mage::helper('tnw_salesforce')->getMagentoVersion() < 1500) {
                $sql = "SELECT website_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('customer_entity') . "` WHERE entity_id = '" . $customer_id . "'";
                $row = $this->_write->query($sql)->fetch();
                if (!$row) {
                    $_customer->setWebsiteId($row['website_id']);
                }
            }
            $_customer = $_customer->load($customer_id);
            unset($customer_id);
        } else {
            // Guest most likely
            $_customer = Mage::getModel('customer/customer');

            $_websiteId = Mage::getModel('core/store')->load($order->getStoreId())->getWebsiteId();
            $_storeId = $order->getStoreId();
            if ($_customer->getSharingConfig()->isWebsiteScope()) {
                $_customer->setWebsiteId($_websiteId);
            }
            $_customer->loadByEmail($order->getCustomerEmail());

            if (!$_customer->getId()) {
                //Guest
                $_customer = Mage::getModel("customer/customer");
                $_customer->setGroupId(0); // NOT LOGGED IN
                $_customer->setFirstname($order->getBillingAddress()->getFirstname());
                $_customer->setLastname($order->getBillingAddress()->getLastname());
                $_customer->setEmail($order->getCustomerEmail());
                $_customer->setStoreId($_storeId);
                if (isset($_websiteId)){
                    $_customer->setWebsiteId($_websiteId);
                }

                $_customer->setCreatedAt(gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($order->getCreatedAt()))));
                //TODO: Extract as much as we can from the order

            } else {
                //UPDATE order to record Customer Id
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET customer_id = " . $_customer->getId() . " WHERE entity_id = " . $order->getId() . ";";
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_grid') . "` SET customer_id = " . $_customer->getId() . " WHERE entity_id = " . $order->getId() . ";";
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_address') . "` SET customer_id = " . $_customer->getId() . " WHERE parent_id = " . $order->getId() . ";";
                $this->_write->query($sql);
                Mage::helper("tnw_salesforce")->log('Guest user found in Magento, updating order #' . $order->getRealOrderId() . ' attaching cusomter ID: ' . $_customer->getId());
            }
        }
        if (
            !$_customer->getDefaultBillingAddress()
            && is_object($order->getBillingAddress())
            && $order->getBillingAddress()->getData()
        ) {
            $_billingAddress = Mage::getModel('customer/address');
            $_billingAddress->setCustomerId(0)
                ->setIsDefaultBilling('1')
                ->setSaveInAddressBook('0')
                ->addData($order->getBillingAddress()->getData());
            $_customer->setBillingAddress($_billingAddress);
        }
        if (
            !$_customer->getDefaultShippingAddress()
            && is_object($order->getShippingAddress())
            && $order->getShippingAddress()->getData()
        ) {
            $_shippingAddress = Mage::getModel('customer/address');
            $_shippingAddress->setCustomerId(0)
                ->setIsDefaultShipping('1')
                ->setSaveInAddressBook('0')
                ->addData($order->getShippingAddress()->getData());
            $_customer->setShippingAddress($_shippingAddress);
        }
        return $_customer;
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

    public function resetOrder($_id)
    {
        if (!is_object($this->_write)) {
            $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        }
        $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 0 WHERE entity_id = " . $_id . ";";
        $this->_write->query($sql);
    }

    /**
     * Get order object and update Order Status in Salesforce
     *
     * @param $order
     */
    public function updateStatus($order)
    {
        if (Mage::getModel('tnw_salesforce/localstorage')->getObject($order->getId())) {
            Mage::helper('tnw_salesforce')->log("SKIPPING: Order #" . $order->getRealOrderId() . " is already queued for update.");
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
                Mage::helper('tnw_salesforce')->log("SUCCESS: Updating Order #" . $order->getRealOrderId());
            }
        }
    }
}