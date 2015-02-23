<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Opportunity
 */
class TNW_Salesforce_Helper_Salesforce_Abandoned_Opportunity extends TNW_Salesforce_Helper_Salesforce_Opportunity
{

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

    protected function _convertLeads()
    {
        // Make sure that leadsToConvert cache has unique leads (by email)
        $_emailsForConvertedLeads = array();
        foreach ($this->_cache['leadsToConvert'] as $_quoteNum => $_objToConvert) {
            if (!in_array($this->_cache['abandonedCustomers'][$_quoteNum]->getEmail(), $_emailsForConvertedLeads)) {
                $_emailsForConvertedLeads[] = $this->_cache['abandonedCustomers'][$_quoteNum]->getEmail();
            } else {
                unset($this->_cache['leadsToConvert'][$_quoteNum]);
            }
        }

        $results = $this->_mySforceConnection->convertLead(array_values($this->_cache['leadsToConvert']));
        $_keys = array_keys($this->_cache['leadsToConvert']);
        foreach ($results->result as $_key => $_result) {
            $_quoteNum = $_keys[$_key];

            //Report Transaction
            $this->_cache['responses']['leadsToConvert'][$_quoteNum] = $_result;

            $_customerId = $this->_cache['abandonedCustomers'][$_quoteNum]->getId();
            if (!$_result->success) {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Failed to convert Lead for Customer Email (' . $this->_cache['abandonedCustomers'][$_quoteNum]->getEmail() . ')');
                }
                Mage::helper('tnw_salesforce')->log('Convert Failed: (email: ' . $this->_cache['abandonedCustomers'][$_quoteNum]->getEmail() . ')', 1, "sf-errors");
                if ($_customerId) {
                    // Update Sync Status
                    Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, 0, 'sf_insync', 'customer_entity_int');
                }
                $this->_processErrors($_result, 'convertLead', $this->_cache['leadsToConvert'][$_quoteNum]);
            } else {
                $_email = strtolower($this->_cache['abandonedCustomers'][$_quoteNum]->getEmail());
                $_websiteId = $this->_cache['abandonedCustomers'][$_quoteNum]->getData('website_id');
                if ($_customerId) {
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

                    $this->_cache['abandonedCustomers'][$_quoteNum] = Mage::getModel("customer/customer")->load($_customerId);
                } else {
                    // For the guest
                    $this->_cache['abandonedCustomers'][$_quoteNum]->setSalesforceLeadId(NULL);
                    $this->_cache['abandonedCustomers'][$_quoteNum]->setSalesforceId($_result->contactId);
                    $this->_cache['abandonedCustomers'][$_quoteNum]->setSalesforceAccountId($_result->accountId);
                    // Update Sync Status
                    $this->_cache['abandonedCustomers'][$_quoteNum]->setSfInsync(0);
                }

                $this->_cache['convertedLeads'][$_quoteNum] = new stdClass();
                $this->_cache['convertedLeads'][$_quoteNum]->contactId = $_result->contactId;
                $this->_cache['convertedLeads'][$_quoteNum]->accountId = $_result->accountId;
                $this->_cache['convertedLeads'][$_quoteNum]->email = $_email;

                Mage::helper('tnw_salesforce')->log('Converted: (account: ' . $this->_cache['convertedLeads'][$_quoteNum]->accountId . ') and (contact: ' . $this->_cache['convertedLeads'][$_quoteNum]->contactId . ')');

                // Apply updates to after conversion to the cached queue (Not needed if only doing 1 quote, which means 1 customer)
                /*
                foreach ($this->_cache['leadsToConvert'] as $_oid => $_obj) {
                    if ($_email == strtolower($this->_cache['abandonedCustomers'][$_oid]->getEmail())) {
                        $this->_cache['abandonedCustomers'][$_oid] = $this->_cache['abandonedCustomers'][$_quoteNum];
                    }
                }
                */
            }
        }
    }

    protected function _prepareOpportunities()
    {
        Mage::helper('tnw_salesforce')->log('----------Opportunity Preparation: Start----------');
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_quoteNumber) {
            if (!Mage::registry('abandoned_cached_' . $_quoteNumber)) {
                $_quote = $this->_loadQuote($_key);
                Mage::register('abandoned_cached_' . $_quoteNumber, $_quote);
            } else {
                $_quote = Mage::registry('abandoned_cached_' . $_quoteNumber);
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
                // Delete all OpportunityProducts
                //$this->_cache['opportunityLineItemsToDelete'][] = $_quoteNumber;
                Mage::helper('tnw_salesforce')->log("SKIPPED Quote: " . $_quoteNumber . " - Opportunity uses a different pricebook(" . $this->_cache['opportunityLookup'][$_quoteNumber]->Pricebook2Id . "), please change it in Salesforce.");
                unset($this->_cache['entitiesUpdating'][$_key]);
                unset($this->_cache['abandonedToEmail'][$_quoteNumber]);
                $this->_allResults['opportunities_skipped']++;
            } else {
                $this->_cache['opportunitiesToUpsert'][$_quoteNumber] = $this->_obj;
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
        foreach($this->_cache['abandonedToEmail'] as $_oid => $_email) {
            $_customerId = $this->_cache['abandonedToCustomerId'][$_oid];
            $_emailArray[$_customerId] = $_email;
            $_quote = Mage::registry('abandoned_cached_' . $_oid);
            $_websiteId = (array_key_exists($_oid, $this->_cache['abandonedCustomers']) && $this->_cache['abandonedCustomers'][$_oid]->getData('website_id')) ? $this->_cache['abandonedCustomers'][$_oid]->getData('website_id') : Mage::getModel('core/store')->load($_quote->getData('store_id'))->getWebsiteId();
            $_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];
        }
        // update contact lookup data
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emailArray, $_websites);
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
                Mage::dispatchEvent("tnw_salesforce_quote_send_before",array("data" => $this->_cache['opportunitiesToUpsert']));

                $_toSyncValues = array_values($this->_cache['opportunitiesToUpsert']);
                $_keys = array_keys($this->_cache['opportunitiesToUpsert']);

                $results = $this->_mySforceConnection->upsert($_pushOn, $_toSyncValues, 'Opportunity');

                Mage::dispatchEvent("tnw_salesforce_opportunity_send_after",array(
                    "data" => $this->_cache['opportunitiesToUpsert'],
                    "result" => $results
                ));
            } catch (Exception $e) {
                $_response = $this->_buildErrorResponse($e->getMessage());
                foreach($_keys as $_id) {
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

                    // set opp owner
                    // $this->_updateOppOwner($_result->id); // frozen until we get other working solution

                    $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_quote') . "` SET sf_sync_force = 0, sf_insync = 1, salesforce_id = '" . $_result->id . "', created_at = created_at WHERE entity_id = " . $_entityArray[$_quoteNum] . ";";

                    Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
                    $this->_write->query($sql . ' commit;');
                    $this->_cache['upsertedOpportunities'][$_quoteNum] = $_result->id;
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
                if (!Mage::registry('abandoned_cached_' . $_quoteNumber)) {
                    $_quote = $this->_loadQuote($_key);
                    Mage::register('abandoned_cached_' . $_quoteNumber, $_quote);
                } else {
                    $_quote = Mage::registry('abandoned_cached_' . $_quoteNumber);
                }

                foreach ($_quote->getAllVisibleItems() as $_item) {
                    $id = $this->getProductIdFromCart($_item);
                    $_storeId = $_quote->getStoreId();

                    if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
                        if ($_quote->getData('quote_currency_code') != $_quote->getData('store_currency_code')) {
                            $_storeId = $this->_getStoreIdByCurrency($_quote->getData('quote_currency_code'));
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

        foreach ($this->_cache['entitiesUpdating'] as $_key => $_quoteNumber) {
            if (in_array($_quoteNumber, $this->_cache['failedOpportunities'])) {
                Mage::helper('tnw_salesforce')->log('QUOTE (' . $_quoteNumber . '): Skipping, issues with upserting an opportunity!');
                continue;
            }
            Mage::helper('tnw_salesforce')->log('******** QUOTE (' . $_quoteNumber . ') ********');
            //$_quote = $this->_loadQuote($_key);
            $_quote = Mage::registry('abandoned_cached_' . $_quoteNumber);
            $_currencyCode = Mage::app()->getStore($_quote->getStoreId())->getCurrentCurrencyCode();


            if (Mage::helper('tnw_salesforce')->useTaxFeeProduct()) {
                if (Mage::helper('tnw_salesforce')->getTaxProduct()) {
                    $this->addTaxProduct($_quote, $_quoteNumber);
                } else {
                    Mage::helper('tnw_salesforce')->log("CRITICAL ERROR: Opportunity Tax product is not set!", 1, "sf-errors");
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Could not add Tax Fee product to the Opportunity!');
                    }
                }
            }

            if (Mage::helper('tnw_salesforce')->useDiscountFeeProduct()) {
                if (Mage::helper('tnw_salesforce')->getDiscountProduct()) {
                    $this->addDiscountProduct($_quote, $_quoteNumber);
                } else {
                    Mage::helper('tnw_salesforce')->log("CRITICAL ERROR: Discount product is not configured!", 1, "sf-errors");
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Could not add Discount Fee product to the Quote!');
                    }
                }
            }

            foreach ($_quote->getAllVisibleItems() as $_item) {
                if ((int) $_item->getQty() == 0) {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice("Product w/ SKU (" . $_item->getSku() . ") for quote #" . $_quoteNumber . " is not synchronized, quoteed quantity is zero!");
                    }
                    Mage::helper('tnw_salesforce')->log("NOTE: Product w/ SKU (" . $_item->getSku() . ") is not synchronized, quoteed quantity is zero!");
                    continue;
                }
                // Load by product Id only if bundled OR simple with options
                $id = $this->getProductIdFromCart($_item);

                $_storeId = $_quote->getStoreId();
                if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
                    if ($_quote->getData('quote_currency_code') != $_quote->getData('store_currency_code')) {
                        $_storeId = $this->_getStoreIdByCurrency($_quote->getData('quote_currency_code'));
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
                /* Load mapping for OpportunityLineItem */
                foreach ($this->_opportunityLineItemMapping as $_map) {
                    $this->_processCartMapping($_product, $_map, $_item);
                }
                unset($collection, $_map);
                // Check if already exists

                $_cartItemFound = $this->doesCartItemExistInOpportunity($_quoteNumber, $_item, $_sku);
                if ($_cartItemFound) {
                    $this->_obj->Id = $_cartItemFound;
                }

                $this->_obj->OpportunityId = $this->_cache['upsertedOpportunities'][$_quoteNumber];
                //$subtotal = number_format((($item->getPrice() * $item->getQty()) + $item->getTaxAmount()), 2, ".", "");
                //$subtotal = number_format(($_item->getPrice() * $_item->getQty()), 2, ".", "");
                //$netTotal = number_format(($subtotal - $_item->getDiscountAmount()), 2, ".", "");

                if (!Mage::helper('tnw_salesforce')->useTaxFeeProduct()) {
                    $netTotal = number_format($_item->getData('row_total_incl_tax'), 2, ".", "");
                } else {
                    $netTotal = number_format($_item->getData('row_total'), 2, ".", "");
                }

                if (!Mage::helper('tnw_salesforce')->useDiscountFeeProduct()) {
                    $netTotal = number_format(($netTotal - $_item->getData('discount_amount')), 2, ".", "");
                    $this->_obj->UnitPrice = number_format($netTotal / $_item->getQty(), 2, ".", "");;
                } else {
                    if ((int) $_item->getQty() == 0) {
                        $this->_obj->UnitPrice = $netTotal;
                    } else {
                        $this->_obj->UnitPrice = $netTotal / $_item->getQty();
                    }
                }

                if (!property_exists($this->_obj, "Id")) {
                    $this->_obj->PricebookEntryId = $_product->getSalesforcePricebookId();
                }

                //$this->_obj->ProductCode = $_item->getSku();
                $defaultServiceDate = Mage::helper('tnw_salesforce/shipment')->getDefaultServiceDate();
                if ($defaultServiceDate) {
                    $this->_obj->ServiceDate = $defaultServiceDate;
                }
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
                    $syncParam = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix() . "Product_Options__c";
                    $this->_obj->$syncParam = $_prefix . join("", $opt) . '</tbody></table>';
                    $this->_obj->Description = join(", ", $_summary);
                    if (strlen($this->_obj->Description) > 200) {
                        $this->_obj->Description = substr($this->_obj->Description, 0, 200) . '...';
                    }
                }

                $this->_obj->Quantity = $_item->getQty();

                /* Dump OpportunityLineItem object into the log */
                foreach ($this->_obj as $key => $_item) {
                    Mage::helper('tnw_salesforce')->log("OpportunityLineItem Object: " . $key . " = '" . $_item . "'");
                }

                $this->_cache['opportunityLineItemsToUpsert'][] = $this->_obj;
                Mage::helper('tnw_salesforce')->log('-----------------');
            }
        }
        Mage::helper('tnw_salesforce')->log('----------Prepare Cart Items: End----------');
    }

    protected function addTaxProduct($_quote, $_quoteNumber)
    {
        $_storeId = $_quote->getStoreId();
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            if ($_quote->getData('quote_currency_code') != $_quote->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($_quote->getData('quote_currency_code'));
            }
        }
        $this->_obj = new stdClass();
        $_helper = Mage::helper('tnw_salesforce');
        $_taxProductPricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::QUOTE_TAX_PRODUCT);

        $_cartItemFound = false;
        if (
            is_array($this->_cache['opportunityLookup']) &&
            array_key_exists($_quoteNumber, $this->_cache['opportunityLookup']) &&
            property_exists($this->_cache['opportunityLookup'][$_quoteNumber], 'OpportunityLineItems')
            && is_object($this->_cache['opportunityLookup'][$_quoteNumber]->OpportunityLineItems)
            && property_exists($this->_cache['opportunityLookup'][$_quoteNumber]->OpportunityLineItems, 'records')
        ) {
            foreach ($this->_cache['opportunityLookup'][$_quoteNumber]->OpportunityLineItems->records as $_cartItem) {
                if ($_cartItem->PricebookEntryId == $_taxProductPricebookEntryId) {
                    $_cartItemFound = $_cartItem->Id;
                    break;
                }
            }
        }

        if ($_cartItemFound) {
            $this->_obj->Id = $_cartItemFound;
        }

        $this->_obj->OpportunityId = $this->_cache['upsertedOpportunities'][$_quoteNumber];
        $this->_obj->UnitPrice = number_format(($_quote->getTaxAmount()), 2, ".", "");
        if (!property_exists($this->_obj, "Id")) {
            if ($_quote->getData('quote_currency_code') != $_quote->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($_quote->getData('quote_currency_code'));
            } else {
                $_storeId = $_quote->getStoreId();
            }

            $this->_obj->PricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::QUOTE_TAX_PRODUCT);
        }
        $defaultServiceDate = Mage::helper('tnw_salesforce/shipment')->getDefaultServiceDate();
        if ($defaultServiceDate) {
            $this->_obj->ServiceDate = $defaultServiceDate;
        }
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
     * @param $_quote
     * @param $_quoteNumber
     * Prepare Shipping fee to Saleforce quote
     */
    protected function addDiscountProduct($_quote, $_quoteNumber)
    {
        $_storeId = $_quote->getStoreId();
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            if ($_quote->getData('quote_currency_code') != $_quote->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($_quote->getData('quote_currency_code'));
            }
        }
        // Add Shipping Fee to the quote
        $this->_obj = new stdClass();

        $_helper = Mage::helper('tnw_salesforce');
        $_discountProductPricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::QUOTE_DISCOUNT_PRODUCT);

        $_cartItemFound = false;
        if (
            is_array($this->_cache['opportunityLookup']) &&
            array_key_exists($_quoteNumber, $this->_cache['opportunityLookup']) &&
            property_exists($this->_cache['opportunityLookup'][$_quoteNumber], 'OpportunityLineItems')
            && is_object($this->_cache['opportunityLookup'][$_quoteNumber]->OpportunityLineItems)
            && property_exists($this->_cache['opportunityLookup'][$_quoteNumber]->OpportunityLineItems, 'records')
        ) {
            foreach ($this->_cache['opportunityLookup'][$_quoteNumber]->OpportunityLineItems->records as $_cartItem) {
                if ($_cartItem->PricebookEntryId == $_discountProductPricebookEntryId) {
                    $_cartItemFound = $_cartItem->Id;
                    break;
                }
            }
        }
        if ($_cartItemFound) {
            $this->_obj->Id = $_cartItemFound;
        }

        $this->_obj->OpportunityId = $this->_cache['upsertedOpportunities'][$_quoteNumber];
        $this->_obj->UnitPrice = number_format(($_quote->getData('discount_amount')), 2, ".", "");
        if (!property_exists($this->_obj, "Id")) {
            if ($_quote->getData('quote_currency_code') != $_quote->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($_quote->getData('quote_currency_code'));
            } else {
                $_storeId = $_quote->getStoreId();
            }

            $this->_obj->PricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::QUOTE_DISCOUNT_PRODUCT);
        }
        $this->_obj->Description = 'Discount';
        $this->_obj->Quantity = 1;

        /* Dump QuoteItem object into the log */
        foreach ($this->_obj as $key => $_item) {
            Mage::helper('tnw_salesforce')->log("OpportunityLineItem Object: " . $key . " = '" . $_item . "'");
        }
        $this->_cache['opportunityLineItemsToUpsert'][] = $this->_obj;
        Mage::helper('tnw_salesforce')->log('-----------------');
    }

    protected function doesCartItemExistInOpportunity($_quoteNumber, $_item, $_sku)
    {
        $_cartItemFound = false;
        if ($this->_cache['opportunityLookup'] && array_key_exists($_quoteNumber, $this->_cache['opportunityLookup']) && $this->_cache['opportunityLookup'][$_quoteNumber]->OpportunityLineItems) {
            foreach ($this->_cache['opportunityLookup'][$_quoteNumber]->OpportunityLineItems->records as $_cartItem) {
                if (
                    property_exists($_cartItem, 'PricebookEntry')
                    && property_exists($_cartItem->PricebookEntry, 'ProductCode')
                    && $_cartItem->PricebookEntry->ProductCode == $_sku
                    && $_cartItem->Quantity == (float)$_item->getQty()
                ) {
                    $_cartItemFound = $_cartItem->Id;
                    break;
                }
            }
        }
        return $_cartItemFound;
    }


    protected function _prepareContactRoles()
    {
        Mage::helper('tnw_salesforce')->log('----------Prepare Opportunity Contact Role: Start----------');
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_quoteNumber) {
            if (in_array($_quoteNumber, $this->_cache['failedOpportunities'])) {
                Mage::helper('tnw_salesforce')->log('QUOTE (' . $_quoteNumber . '): Skipping, issues with upserting an opportunity!');
                continue;
            }
            Mage::helper('tnw_salesforce')->log('******** QUOTE (' . $_quoteNumber . ') ********');
            $_customerId = $this->_cache['abandonedToCustomerId'][$_quoteNumber];
            if (!Mage::registry('customer_cached_' . $_customerId)) {
                $_customer = $this->_cache['abandonedCustomers'][$_quoteNumber];
            } else {
                $_customer = Mage::registry('customer_cached_' . $_customerId);
            }

            $this->_obj = new stdClass();
            $_quote = $this->_loadQuote($_key);
            $_email = strtolower($_customer->getEmail());
            $_websiteId = ($this->_cache['abandonedCustomers'][$_quote->getId()]->getData('website_id')) ? $this->_cache['abandonedCustomers'][$_quote->getId()]->getData('website_id') : Mage::getModel('core/store')->load($_quote->getData('store_id'))->getWebsiteId();

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
                $this->_obj->ContactId = $this->_cache['accountsLookup'][$_websiteId][$_email]->Id;
            } else {
                $this->_obj->ContactId = $_customer->getSalesforceId();
            }

            // Check if already exists
            $_roleFound = $_skip = false;
            if ($this->_cache['opportunityLookup'] && array_key_exists($_quoteNumber, $this->_cache['opportunityLookup']) && $this->_cache['opportunityLookup'][$_quoteNumber]->OpportunityContactRoles) {
                foreach ($this->_cache['opportunityLookup'][$_quoteNumber]->OpportunityContactRoles->records as $_role) {
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
                $this->_obj->OpportunityId = $this->_cache['upsertedOpportunities'][$_quoteNumber];

                $this->_obj->Role = Mage::helper('tnw_salesforce')->getDefaultCustomerRole();

                foreach ($this->_obj as $key => $_item) {
                    Mage::helper('tnw_salesforce')->log("OpportunityContactRole Object: " . $key . " = '" . $_item . "'");
                }

                if ($this->_obj->ContactId) {
                    $this->_cache['contactRolesToUpsert'][] = $this->_obj;
                } else {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('Was not able to convert customer Lead, skipping Opportunity Contact Role assignment. Please synchronize customer (email: ' . $this->_cache['abandonedCustomers'][$_quoteNumber]->getEmail() . ')');
                    }
                }
            }
        }
        Mage::helper('tnw_salesforce')->log('----------Prepare Opportunity Contact Role: End----------');
    }

    protected function _pushOpportunityLineItems($chunk = array())
    {
        if (empty($this->_cache['upsertedOpportunities'])) {
            return false;
        }
        $_quoteNumbers = array_flip($this->_cache['upsertedOpportunities']);
        try {
            $results = $this->_mySforceConnection->upsert("Id", $chunk, 'OpportunityLineItem');
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach($chunk as $_object) {
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
                // Reset sync status
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_quote') . "` SET sf_insync = 0, created_at = created_at WHERE salesforce_id = '" . $this->_cache['opportunityLineItemsToUpsert'][$_key]->OpportunityId . "';";
                Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
                $this->_write->query($sql . ' commit;');

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
                foreach($this->_cache['contactRolesToUpsert'] as $_object) {
                    $this->_cache['responses']['opportunityLineItems'][] = $_response;
                }
                $results = array();
                Mage::helper('tnw_salesforce')->log('CRITICAL: Push of contact roles to SalesForce failed' . $e->getMessage());
            }

            $_quoteNumbers = array_flip($this->_cache['upsertedOpportunities']);
            foreach ($results as $_key => $_result) {
                $_quoteNum = $_quoteNumbers[$this->_cache['contactRolesToUpsert'][$_key]->OpportunityId];

                //Report Transaction
                $this->_cache['responses']['opportunityCustomerRoles'][] = $_result;

                if (!$_result->success) {
                    // Reset sync status
                    $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_quote') . "` SET sf_sync_force = 0, sf_insync = 0, created_at = created_at WHERE salesforce_id = '" . $this->_cache['contactRolesToUpsert'][$_key]->OpportunityId . "';";
                    Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
                    $this->_write->query($sql . ' commit;');

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
    public function massAdd($_id = NULL, $_isCron = false)
    {
        if (!$_id) {
            Mage::helper('tnw_salesforce')->log("Abandoned Id is not specified, don't know what to synchronize!");
            return false;
        }
        // test sf api connection
        $_client = Mage::getSingleton('tnw_salesforce/connection');
        if (!$_client->tryWsdl()
            || !$_client->tryToConnect()
            || !$_client->tryToLogin()) {
            Mage::helper('tnw_salesforce')->log("error on sync quotes, sf api connection failed");

            return true;
        }

        try {
            $this->_isCron = $_isCron;

            // Clear Opportunity ID
            $this->resetAbandoned($_id);

            // Load quote by ID
            $_quote = $this->_loadQuote($_id);
            // Add to cache
            if (!Mage::registry('abandoned_cached_' . $_quote->getId())) {
                Mage::register('abandoned_cached_' . $_quote->getId(), $_quote);
            } else {
                Mage::unregister('abandoned_cached_' . $_quote->getId());
                Mage::register('abandoned_cached_' . $_quote->getId(), $_quote);
            }

            // Quote could not be loaded for some reason
            if (!$_quote->getId()) {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Sync for abandoned cart #' . $_id . ', quote could not be loaded!');
                }
                Mage::helper("tnw_salesforce")->log("SKIPPING: Sync for abandoned cart #" . $_id . ", quote could not be loaded!", 1, "sf-errors");
                return false;
            }

            // Get Magento customer object
            $this->_cache['abandonedCustomers'][$_quote->getId()] = $this->_getCustomer($_quote);
            // Associate quote Number with a customer ID
            $this->_cache['abandonedToCustomerId'][$_quote->getId()] = ($this->_cache['abandonedCustomers'][$_quote->getId()]->getId()) ? $this->_cache['abandonedCustomers'][$_quote->getId()]->getId() : 'guest-0';

            // Associate quote Number with a customer Email
            $this->_cache['abandonedToEmail'][$_quote->getId()] = $this->_cache['abandonedCustomers'][$_quote->getId()]->getEmail();

            // Check if customer from this group is allowed to be synchronized
            $_customerGroup = $_quote->getData('customer_group_id');
            if ($_customerGroup === NULL) {
                $_customerGroup = $this->_cache['abandonedCustomers'][$_quote->getId()]->getGroupId();
            }
            if ($_customerGroup === NULL && !$this->isFromCLI()) {
                $_customerGroup = Mage::getSingleton('customer/session')->getCustomerGroupId();
            }
            if (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($_customerGroup)) {
                Mage::helper("tnw_salesforce")->log("SKIPPING: Sync for customer group #" . $_customerGroup . " is disabled!", 1, "sf-errors");
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for abandoned #' . $_quote->getId() . ', sync for customer group #' . $_customerGroup . ' is disabled!');
                }
                return false;
            }

            // Store quote number and customer Email into a variable for future use
            $_quoteEmail = strtolower($this->_cache['abandonedCustomers'][$_quote->getId()]->getEmail());
            $_customerId = ($this->_cache['abandonedCustomers'][$_quote->getId()]->getId()) ? $this->_cache['abandonedCustomers'][$_quote->getId()]->getId() : 'guest-0';
            $_websiteId = ($this->_cache['abandonedCustomers'][$_quote->getId()]->getData('website_id')) ? $this->_cache['abandonedCustomers'][$_quote->getId()]->getData('website_id') : Mage::getModel('core/store')->load($_quote->getData('store_id'))->getWebsiteId();
            $_quoteNumber = $_quote->getId();

            if (empty($_quoteEmail)) {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::helper("tnw_salesforce")->log("SKIPPED: Sync for quote #' . $_quoteNumber . ' failed, quote is missing an email address!");
                    Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for quote #' . $_quoteNumber . ' failed, quote is missing an email address!');
                }
                return false;
            }

            // Force sync of the customer if Account Rename is turned on
            if (Mage::helper('tnw_salesforce')->canRenameAccount()) {
                Mage::helper("tnw_salesforce")->log('Syncronizing Guest/New customer...');
                $manualSync = Mage::helper('tnw_salesforce/salesforce_customer');
                if ($manualSync->reset()) {
                    $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
                    $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());

                    $manualSync->forceAdd($this->_cache['abandonedCustomers'][$_quote->getId()]);
                    set_time_limit(30);
                    $this->_cache['abandonedCustomers'][$_quoteNumber] = $manualSync->process(true);
                    set_time_limit(30);
                }
            }

            // Associate quote ID with quote Number
            $this->_cache['entitiesUpdating'] = array($_id => $_quoteNumber);
            // Salesforce lookup, find all contacts/accounts by email address
            $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup(array($_customerId => $_quoteEmail), array($_customerId => $this->_websiteSfIds[$_websiteId]));
            // Salesforce lookup, find all opportunities by Magento quote number
            $this->_cache['opportunityLookup'] = Mage::helper('tnw_salesforce/salesforce_data')->opportunityLookup($this->_cache['entitiesUpdating']);

            // Check if we need to look for a Lead, since customer Contact/Account could not be found
            $_leadsToLookup = NULL;
            $_customerToSync = NULL;
            if (!is_array($this->_cache['accountsLookup'])
                || !array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
                || !array_key_exists($_quoteEmail, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])) {
                $_quote = Mage::registry('abandoned_cached_' . $_quoteNumber);
                $_leadsToLookup[$_customerId] = $_quoteEmail;
                $this->_cache['abandonedCustomersToSync'][] = $_quoteNumber;
            }

            // If customer exists as a Lead
            if ($_leadsToLookup) {
                $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($_leadsToLookup, array($_customerId => $this->_websiteSfIds[$_websiteId]));
                // If Lead is converted, update the lookup data
                $this->_cache['abandonedCustomers'][$_quote->getId()] = $this->_updateAccountLookupData($this->_cache['abandonedCustomers'][$_quote->getId()]);

                $_foundAccounts = array();
                // If Lead not found, potentially a guest
                if (!is_array($this->_cache['leadLookup']) || !array_key_exists($_websiteId, $this->_cache['leadLookup']) || !array_key_exists($_quoteEmail, $this->_cache['leadLookup'][$_websiteId])) {
                    Mage::helper("tnw_salesforce")->log('Syncronizing Guest/New customer...');
                    $manualSync = Mage::helper('tnw_salesforce/salesforce_customer');
                    if ($manualSync->reset()) {
                        $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
                        $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());

                        $manualSync->forceAdd($this->_cache['abandonedCustomers'][$_quote->getId()]);
                        set_time_limit(30);
                        $this->_cache['abandonedCustomers'][$_quoteNumber] = $manualSync->process(true);
                        set_time_limit(30);

                        // Returns Email to Account association so we don't create duplicate Accounts
                        $_foundAccounts = $manualSync->getCustomerAccounts();
                    }
                    Mage::helper("tnw_salesforce")->log('Updating lookup cache...');
                    // update Lookup values
                    $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup(array($_customerId => $_quoteEmail), array($_customerId => $this->_websiteSfIds[$_websiteId]));
                    if (!is_array($this->_cache['accountsLookup'])
                        || !array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
                        || !array_key_exists($_quoteEmail, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])) {
                        $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($_leadsToLookup, array($_customerId => $this->_websiteSfIds[$_websiteId]));
                        // If Lead is converted, update the lookup data
                        $this->_cache['abandonedCustomers'][$_quote->getId()] = $this->_updateAccountLookupData($this->_cache['abandonedCustomers'][$_quote->getId()]);
                    }
                }

                if (is_array($this->_cache['leadLookup'])
                    && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['leadLookup'])
                    && array_key_exists($_quoteEmail, $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]])) {
                    // Need to convert a Lead
                    $_queueList = Mage::helper('tnw_salesforce/salesforce_data_queue')->getAllQueues();
                    $this->_prepareLeadConversionObject($_quoteNumber, $_foundAccounts, $_queueList);
                    Mage::helper("tnw_salesforce")->log('SUCCESS: Automatic customer Lead prepared to be converted.');
                } elseif (is_array($this->_cache['accountsLookup'])
                    && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
                    && array_key_exists($_quoteEmail, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])) {
                    // Found Contact & Account
                    Mage::helper("tnw_salesforce")->log('SUCCESS: Automatic customer synchronization.');
                } else {
                    // Something is wrong, could not create / find Magento customer in SalesForce
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for quote #' . $_quote->getId() . ', could not locate / create Magento customer (' . $_quoteEmail . ') in Salesforce!');
                    }
                    Mage::helper("tnw_salesforce")->log('CRITICAL ERROR: Contact or Lead for Magento customer (' . $_quoteEmail . ') could not be created / found!', 1, "sf-errors");
                    return false;
                }
            } else {
                if (is_array($this->_cache['accountsLookup'])
                    && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
                    && array_key_exists($_quoteEmail, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])) {
                    $this->_cache['abandonedCustomers'][$_quoteNumber]->setSalesforceId($this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_quoteEmail]->Id);
                    $this->_cache['abandonedCustomers'][$_quoteNumber]->setSalesforceAccountId($this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_quoteEmail]->AccountId);
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
     * @param $_quoteNumber
     * @param array $_accounts
     * @return bool
     */
    protected function _prepareLeadConversionObject($_quoteNumber, $_accounts = array(), $_queueList = NULL)
    {
        if (!Mage::helper("tnw_salesforce")->getLeadConvertedStatus()) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: Converted Lead status is not set in the configuration, cannot proceed!');
            }
            Mage::helper("tnw_salesforce")->log('Converted Lead status is not set in the configuration, cannot proceed!', 1, "sf-errors");
            return false;
        }

        $_email = strtolower($this->_cache['abandonedToEmail'][$_quoteNumber]);
        $_quote = $this->_loadQuote($_quoteNumber);;
        $_websiteId = Mage::getModel('core/store')->load($_quote->getData('store_id'))->getWebsiteId();
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
                    !is_array($_queueList)
                    && !in_array($this->_cache['leadLookup'][$_salesforceWebsiteId][$_email]->OwnerId, $_queueList)
                )
            ) {
                $leadConvert->ownerId = $this->_cache['leadLookup'][$_salesforceWebsiteId][$_email]->OwnerId;
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

            if ($leadConvert->leadId) {
                $this->_cache['leadsToConvert'][$_quoteNumber] = $leadConvert;
            } else {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Quote #' . $_quoteNumber . ' - customer (email: ' . $this->_cache['abandonedCustomers'][$_quoteNumber]->getEmail() . ') needs to be synchronized first, aborting!');
                }
                Mage::helper("tnw_salesforce")->log('Quote #' . $_quoteNumber . ' - customer (email: ' . $this->_cache['abandonedCustomers'][$_quoteNumber]->getEmail() . ') needs to be synchronized first, aborting!', 1, "sf-errors");
                return false;
            }
        }
    }

    /**
     * Gets field mapping from Magento and creates OpportunityLineItem object
     */
    protected function _processCartMapping($prod = NULL, $_map = NULL, $cartItem = NULL)
    {
        $value = false;
        $conf = explode(" : ", $_map->local_field);
        $sf_field = $_map->sf_field;

        switch ($conf[0]) {
            case "Cart":
                if ($cartItem) {
                    if ($conf[1] == "total_product_price") {
                        $subtotal = number_format((($cartItem->getPrice() + $cartItem->getTaxAmount()) * $cartItem->getQty()), 2, ".", "");
                        $value = number_format(($subtotal - $cartItem->getDiscountAmount()), 2, ".", "");
                    } else {
                        $value = $cartItem->getData($conf[1]);

                        // Reformat date fields
                        if ($conf[1] == 'created_at' || $conf[1] == 'updated_at') {
                            $_doSkip = false;
                            if ($cartItem->getData($conf[1])) {
                                $timestamp = strtotime($cartItem->getData($conf[1]));
                                $newAttribute = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp($timestamp));
                            } else {
                                $_doSkip = true; //Skip this filed if empty
                            }
                        }
                        if (!$_doSkip) {
                            $value = $newAttribute;
                        }
                    }
                }
                break;
            case "Product Inventory":
                $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($prod);
                $value = ($stock) ? (int)$stock->getQty() : NULL;
                break;
            case "Product":
                $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                $value = ($prod->getAttributeText($conf[1])) ? $prod->getAttributeText($conf[1]) : $prod->$attr();
                break;
            case "Custom":
                if ($conf[1] == "current_url") {
                    $value = Mage::helper('core/url')->getCurrentUrl();
                } elseif ($conf[1] == "todays_date") {
                    $value = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
                } elseif ($conf[1] == "todays_timestamp") {
                    $value = gmdate(DATE_ATOM, strtotime(Mage::getModel('core/date')->timestamp(time())));
                } elseif ($conf[1] == "end_of_month") {
                    $lastday = mktime(0, 0, 0, date("n") + 1, 0, date("Y"));
                    $value = date("Y-m-d", Mage::getModel('core/date')->timestamp($lastday));
                } elseif ($conf[1] == "store_view_name") {
                    $value = Mage::app()->getStore()->getName();
                } elseif ($conf[1] == "store_group_name") {
                    $value = Mage::app()->getStore()->getGroup()->getName();
                } elseif ($conf[1] == "website_name") {
                    $value = Mage::app()->getWebsite()->getName();
                } else {
                    $value = $_map->default_value;
                    if ($value == "{{url}}") {
                        $value = Mage::helper('core/url')->getCurrentUrl();
                    } elseif ($value == "{{today}}") {
                        $value = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
                    } elseif ($value == "{{end of month}}") {
                        $lastday = mktime(0, 0, 0, date("n") + 1, 0, date("Y"));
                        $value = date("Y-m-d", $lastday);
                    } elseif ($value == "{{contact id}}") {
                        $value = $this->_contactId;
                    } elseif ($value == "{{store view name}}") {
                        $value = Mage::app()->getStore()->getName();
                    } elseif ($value == "{{store group name}}") {
                        $value = Mage::app()->getStore()->getGroup()->getName();
                    } elseif ($value == "{{website name}}") {
                        $value = Mage::app()->getWebsite()->getName();
                    }
                }
                break;
            default:
                break;
        }
        if ($value) {
            $this->_obj->$sf_field = trim($value);
        } else {
            Mage::helper('tnw_salesforce')->log('OPPORTUNITY LINE ITEM MAPPING: attribute ' . $sf_field . ' does not have a value in Magento, SKIPPING!');
        }
    }

    /**
     * @param $quote Mage_Sales_Model_Quote
     * @throws Mage_Core_Exception
     */
    protected function _processMapping($quote)
    {

        if (is_array($this->_cache['abandonedCustomers']) && array_key_exists($quote->getId(), $this->_cache['abandonedCustomers'])) {
            $_customer = $this->_cache['abandonedCustomers'][$quote->getId()];
        } else {
            $this->_cache['abandonedCustomers'][$quote->getId()] = $this->_getCustomer($quote);
            $_customer = $this->_cache['abandonedCustomers'][$quote->getId()];
        }

        /**
         * @var $_customer Mage_Customer_Model_Customer
         */
        if ($_customer->getGroupId()) {
            $this->_customerGroupModel->load($_customer->getGroupId());
        }

        foreach ($this->_opportunityMapping as $_map) {
            $_doSkip = $value = false;
            $conf = explode(" : ", $_map->local_field);
            $sf_field = $_map->sf_field;
            switch ($conf[0]) {
                case "Customer":

                    $attrName = str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                    if ($attrName == "Email") {
                        $email = $quote->getCustomerEmail();
                        if (!$email) {
                            //TODO: add email
                            $email = $_customer->getEmail();
                        }
                        $value = $email;
                    } else {
                        $attr = "get" . $attrName;

                        // Make sure getAttribute is called on the object
                        if ($_customer->getAttribute($conf[1])->getFrontendInput() == "select" && is_object($_customer->getResource())) {
                            $newAttribute = $_customer->getResource()->getAttribute($conf[1])->getSource()->getOptionText($_customer->$attr());
                        } else {
                            $newAttribute = $_customer->$attr();
                        }

                        // Reformat date fields
                        if ($_map->getBackendType() == "datetime" || $conf[1] == 'created_at') {
                            if ($_customer->$attr()) {
                                $timestamp = Mage::getModel('core/date')->timestamp(strtotime($_customer->$attr()));
                                if ($conf[1] == 'created_at') {
                                    $newAttribute = gmdate(DATE_ATOM, $timestamp);
                                } else {
                                    $newAttribute = date("Y-m-d", $timestamp);
                                }
                            } else {
                                $_doSkip = true; //Skip this filed if empty
                            }
                        }
                        if (!$_doSkip) {
                            $value = $newAttribute;
                        }
                        unset($attributeInfo);
                    }
                    break;
                case "Billing":
                case "Shipping":
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                    $var = 'get' . $conf[0] . 'Address';
                    if (is_object($quote->$var())) {
                        $value = $quote->$var()->$attr();
                        if (is_array($value)) {
                            $value = implode(", ", $value);
                        }
                    }
                    break;
                case "Custom":
                    $store = ($quote->getStoreId()) ? Mage::getModel('core/store')->load($quote->getStoreId()) : NULL;
                    if ($conf[1] == "current_url") {
                        $value = Mage::helper('core/url')->getCurrentUrl();
                    } elseif ($conf[1] == "todays_date") {
                        $value = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
                    } elseif ($conf[1] == "todays_timestamp") {
                        $value = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(time()));
                    } elseif ($conf[1] == "end_of_month") {
                        $lastday = mktime(0, 0, 0, date("n") + 1, 0, date("Y"));
                        $value = date("Y-m-d", $lastday);
                    } elseif ($conf[1] == "store_view_name") {
                        $value = (is_object($store)) ? $store->getName() : NULL;
                    } elseif ($conf[1] == "store_group_name") {
                        $value = (
                            is_object($store)
                            && is_object($store->getGroup())
                        ) ? $store->getGroup()->getName() : NULL;
                    } elseif ($conf[1] == "website_name") {
                        $value = (
                            is_object($store)
                            && is_object($store->getWebsite())
                        ) ? $store->getWebsite()->getName() : NULL;
                    } else {
                        $value = $_map->default_value;
                        if ($value == "{{url}}") {
                            $value = Mage::helper('core/url')->getCurrentUrl();
                        } elseif ($value == "{{today}}") {
                            $value = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
                        } elseif ($value == "{{end of month}}") {
                            $lastday = mktime(0, 0, 0, date("n") + 1, 0, date("Y"));
                            $value = date("Y-m-d", $lastday);
                        } elseif ($value == "{{contact id}}") {
                            $value = $this->_contactId;
                        } elseif ($value == "{{store view name}}") {
                            $value = Mage::app()->getStore()->getName();
                        } elseif ($value == "{{store group name}}") {
                            $value = Mage::app()->getStore()->getGroup()->getName();
                        } elseif ($value == "{{website name}}") {
                            $value = Mage::app()->getWebsite()->getName();
                        }
                    }
                    break;
                case "Quote":
                    if ($conf[1] == "cart_all") {
                        $value = $this->_getDescriptionCart($quote);
                    } elseif ($conf[1] == "number") {
                        $value = $quote->getId();
                    } elseif ($conf[1] == "created_at") {
                        $value = ($quote->getCreatedAt()) ? gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($quote->getCreatedAt()))) : date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
                    } elseif ($conf[1] == "payment_method") {
                        if (is_object($quote->getPayment())) {
                            $paymentMethods = Mage::helper('payment')->getPaymentMethodList(true);
                            $method = $quote->getPayment()->getMethod();
                            if (array_key_exists($method, $paymentMethods)) {
                                $value = $paymentMethods[$method];
                            } else {
                                $value = $method;
                            }
                        } else {
                            Mage::helper('tnw_salesforce')->log('OPPORTUNITY MAPPING: Payment Method is not set in magento for the quote: ' . $quote->getId() . ', SKIPPING!');
                        }
                    } elseif ($conf[1] == "notes") {
                        $allNotes = NULL;
                        foreach ($quote->getStatusHistoryCollection() as $_comment) {
                            $comment = trim(strip_tags($_comment->getComment()));
                            if (!$comment || empty($comment)) {
                                continue;
                            }
                            if (!$allNotes) {
                                $allNotes = "";
                            }
                            $allNotes .= Mage::helper('core')->formatTime($_comment->getCreatedAtDate(), 'medium') . " | " . $_comment->getStatusLabel() . "\n";
                            $allNotes .= strip_tags($_comment->getComment()) . "\n";
                            $allNotes .= "-----------------------------------------\n\n";
                        }
                        $value = $allNotes;
                    } else {
                        //Common attributes
                        $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                        $value = ($quote->getAttributeText($conf[1])) ? $quote->getAttributeText($conf[1]) : $quote->$attr();
                        break;
                    }
                    break;
                case "Customer Group":
                    //Common attributes
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                    $value = $this->_customerGroupModel->$attr();
                    break;
                case "Payment":
                    //Common attributes
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                    $value = $quote->getPayment()->$attr();
                    break;
                case "Aitoc":
                    $modules = Mage::getConfig()->getNode('modules')->children();
                    $value = NULL;
                    if (property_exists($modules, 'Aitoc_Aitcheckoutfields')) {
                        $aCustomAtrrList = Mage::getModel('aitcheckoutfields/transport')->loadByQuoteId($quote->getId());
                        foreach ($aCustomAtrrList->getData() as $_key => $_data) {
                            if ($_data['code'] == $conf[1]) {
                                $value = $_data['value'];
                                if ($_data['type'] == "date") {
                                    $value = date("Y-m-d", strtotime($value));
                                }
                                break;
                            }
                        }
                        unset($aCustomAtrrList);
                    }
                    break;
                default:
                    break;
            }
            if ($value) {
                $this->_obj->$sf_field = trim($value);
            } else {
                Mage::helper('tnw_salesforce')->log('OPPORTUNITY MAPPING: attribute ' . $sf_field . ' does not have a value in Magento, SKIPPING!');
            }
        }
        unset($collection, $_map, $quote);
    }

    protected function _getDescriptionCart($quote)
    {
        $_currencyCode = '';
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $_currencyCode = $quote->getData('quote_currency_code') . " ";
        }

        ## Put Products into Single field
        $descriptionCart = "";
        $descriptionCart .= "Items quoteed:\n";
        $descriptionCart .= "=======================================\n";
        $descriptionCart .= "SKU, Qty, Name";
        $descriptionCart .= ", Price";
        $descriptionCart .= ", Tax";
        $descriptionCart .= ", Subtotal";
        $descriptionCart .= ", Net Total";
        $descriptionCart .= "\n";
        $descriptionCart .= "=======================================\n";

        //foreach ($quote->getAllItems() as $itemId=>$item) {
        foreach ($quote->getAllVisibleItems() as $itemId => $item) {
            $descriptionCart .= $item->getSku() . ", " . number_format($item->getQty()) . ", " . $item->getName();
            //Price
            $unitPrice = number_format(($item->getPrice()), 2, ".", "");
            $descriptionCart .= ", " . $_currencyCode . $unitPrice;
            //Tax
            $tax = number_format(($item->getTaxAmount()), 2, ".", "");
            $descriptionCart .= ", " . $_currencyCode . $tax;
            //Subtotal
            $subtotal = number_format((($item->getPrice() + $item->getTaxAmount()) * $item->getQty()), 2, ".", "");
            $descriptionCart .= ", " . $_currencyCode . $subtotal;
            //Net Total
            $netTotal = number_format(($subtotal - $item->getDiscountAmount()), 2, ".", "");
            $descriptionCart .= ", " . $_currencyCode . $netTotal;
            $descriptionCart .= "\n";
        }
        $descriptionCart .= "=======================================\n";
        $descriptionCart .= "Sub Total: " . $_currencyCode . number_format(($quote->getSubtotal()), 2, ".", "") . "\n";
        $descriptionCart .= "Tax: " . $_currencyCode . number_format(($quote->getTaxAmount()), 2, ".", "") . "\n";
        $descriptionCart .= "Shipping (" . $quote->getShippingDescription() . "): " . $_currencyCode . number_format(($quote->getShippingAmount()), 2, ".", "") . "\n";
        $descriptionCart .= "Discount Amount : " . $_currencyCode . number_format($quote->getGrandTotal() - ($quote->getShippingAmount() + $quote->getTaxAmount() + $quote->getSubtotal()), 2, ".", "") . "\n";
        $descriptionCart .= "Total: " . $_currencyCode . number_format(($quote->getGrandTotal()), 2, ".", "");
        $descriptionCart .= "\n";
        unset($quote);
        return $descriptionCart;
    }

    /**
     * @param $order
     */
    protected function _updateQuoteStageName($quote)
    {
        ## Status integration

        $this->_obj->StageName = 'Committed'; // if $collection is empty then we had error "CRITICAL: Failed to upsert order: Required fields are missing: [StageName]"

        if ($stage = Mage::getStoreConfig('', $quote->getStore())) {
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
//        $_websiteId = Mage::getModel('core/store')->load($quote->getStoreId())->getWebsiteId();
        $_websiteId = $quote->getStoreId();

        $this->_updateQuoteStageName($quote);
        $_quoteNumber = $quote->getId();
        $_customer = $this->_cache['abandonedCustomers'][$_quoteNumber];

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $quote->getData('quote_currency_code');
        }

        // Link to a Website
        if (
            $_websiteId != NULL
            && array_key_exists($_websiteId, $this->_websiteSfIds)
            && $this->_websiteSfIds[$_websiteId]
        ) {
            $this->_obj->{$this->_prefix . 'Website__c'} = $this->_websiteSfIds[$_websiteId];
        }

        $syncParam = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix() . "disableMagentoSync__c";
        $this->_obj->$syncParam = true;

        $magentoQuoteNumber = TNW_Salesforce_Helper_Abandoned::ABANDONED_CART_ID_PREFIX . $_quoteNumber;
        // Magento Quote ID
        $this->_obj->{$this->_magentoId} = $magentoQuoteNumber;

        // Use existing Opportunity if creating from Quote
        $modules = Mage::getConfig()->getNode('modules')->children();

        // Force configured pricebook
        $this->_assignPricebookToQuote($quote);

        // Close Date
        if ($quote->getCreatedAt()) {
            // Always use quote date as closing date if quote already exists
            $this->_obj->CloseDate = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($quote->getCreatedAt())));
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

        $this->_processMapping($quote, "Opportunity");

        // Get Account Name from Salesforce
        $_accountName = (
            $this->_cache['accountsLookup']
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
            && array_key_exists($_customer->getEmail(), $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])
            && $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_customer->getEmail()]->AccountName
        ) ? $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_customer->getEmail()]->AccountName : NULL;
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
        unset($quote);
    }

    /**
     * @param $_quote Mage_Sales_Model_Quote
     */
    protected function _assignPricebookToQuote($_quote)
    {
        try {
            $_storeId = $_quote->getStoreId();
            $_helper = Mage::helper('tnw_salesforce');
            if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
                if ($_quote->getData('quote_currency_code') != $_quote->getData('store_currency_code')) {
                    $_storeId = $this->_getStoreIdByCurrency($_quote->getData('quote_currency_code'));
                }
            }

            $this->_obj->Pricebook2Id = Mage::app()->getStore($_storeId)->getConfig($_helper::PRODUCT_PRICEBOOK);

        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("INFO: Could not load pricebook based on the quote ID. Loading default pricebook based on current store ID.");
            Mage::helper('tnw_salesforce')->log("ERROR: " . $e->getMessage());
            if ($this->_defaultPriceBook) {
                $this->_obj->Pricebook2Id = $this->_defaultPriceBook;
            }
        }
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
                if (isset($_websiteId)){
                    $_customer->setWebsiteId($_websiteId);
                }

                $_customer->setCreatedAt(gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($quote->getCreatedAt()))));
                //TODO: Extract as much as we can from the quote

            } else {
                //UPDATE quote to record Customer Id
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_quote') . "` SET customer_id = " . $_customer->getId() . ", created_at = created_at WHERE entity_id = " . $quote->getId() . ";";
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_quote_address') . "` SET customer_id = " . $_customer->getId() . " WHERE parent_id = " . $quote->getId() . ";";
                $this->_write->query($sql);
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
                if (Mage::registry('abandoned_cached_' . $_abandonedNumber)) {
                    Mage::unregister('abandoned_cached_' . $_abandonedNumber);
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

        $this->_opportunityMapping = Mage::getModel('tnw_salesforce/mapping')->getCollection()->addObjectToFilter('Opportunity');
        $this->_opportunityLineItemMapping = Mage::getModel('tnw_salesforce/mapping')->getCollection()->addObjectToFilter('OpportunityLineItem');

        return $this->check();
    }

    public function resetAbandoned($_id)
    {
        if (!is_object($this->_write)) {
            $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        }

        $quoteTable = Mage::getResourceSingleton('sales/quote')->getMainTable();

        $sql = "UPDATE `" . $quoteTable . "` SET sf_insync = 0, created_at = created_at WHERE entity_id = " . $_id . ";";
        $this->_write->query($sql);
    }

}