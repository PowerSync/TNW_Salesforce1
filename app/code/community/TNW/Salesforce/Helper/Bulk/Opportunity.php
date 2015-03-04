<?php

/**
 * Class TNW_Salesforce_Helper_Bulk_Opportunity
 */
class TNW_Salesforce_Helper_Bulk_Opportunity extends TNW_Salesforce_Helper_Salesforce_Opportunity
{
    /**
     * @var array
     */
    protected $_allResults = array(
        'opportunities' => array(),
        'opportunity_products' => array(),
        'opportunity_contact_roles' => array(),
    );

    /**
     * @param array $ids
     * @param bool $_isCron
     * @return bool
     */
    public function massAdd($ids = array(), $_isCron = false)
    {
        try {
            $this->_isCron = $_isCron;

            $_orderNumbers = array();
            $_websites = $_emails = array();
            $sql = '';
            foreach ($ids as $_id) {
                // Clear Opportunity ID
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET salesforce_id = '' WHERE entity_id = " . $_id . ";";
                $sql .= ";UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 0 WHERE entity_id = " . $_id . ";";
            }
            if (!empty($sql)) {
                $this->_write->query($sql);
                Mage::helper('tnw_salesforce')->log("Opportunity ID and Sync Status for order (#" . join(',', $ids) . ") were reset.");
            }
            $_guestCount = 0;
            foreach ($ids as $_count => $_id) {
                $_order = Mage::getModel('sales/order')->load($_id);
                // Add to cache
                if (!Mage::registry('order_cached_' . $_order->getRealOrderId())) {
                    Mage::register('order_cached_' . $_order->getRealOrderId(), $_order);
                }

                /**
                 * @comment check zero orders sync
                 */
                if (!age::helper('tnw_salesforce/order')->isEnabledZeroOrderSync() && $_order->getGrandTotal() == 0) {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for order #' . $_order->getRealOrderId() . ', grand total is zero and synchronization for these order is disabled in configuration!');
                    }
                    Mage::helper("tnw_salesforce")->log('SKIPPED: Sync for order #' . $_order->getRealOrderId() . ', grand total is zero and synchronization for these order is disabled in configuration!');
                    continue;
                }

                if (
                    !Mage::helper('tnw_salesforce')->syncAllOrders()
                    && !in_array($_order->getStatus(), $this->_allowedOrderStatuses)
                ) {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for order #' . $_order->getId() . ', sync for order status "' . $_order->getStatus() . '" is disabled!');
                    }
                    Mage::helper("tnw_salesforce")->log('SKIPPED: Sync for order #' . $_order->getId() . ', sync for order status "' . $_order->getStatus() . '" is disabled!');
                    continue;
                }

                if (!$_order->getId() || !$_order->getRealOrderId()) {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Sync for order #' . $_id . ', order could not be loaded!');
                    }
                    Mage::helper("tnw_salesforce")->log("SKIPPING: Sync for order #" . $_id . ", order could not be loaded!", 1, "sf-errors");
                    continue;
                }

                $this->_cache['orderCustomers'][$_order->getRealOrderId()] = $this->_getCustomer($_order);
                $this->_cache['orderToCustomerId'][$_order->getRealOrderId()] = ($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId()) ? $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId() : 'guest-' . $_guestCount;
                if (!$this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId()) {
                    $_guestCount++;
                }
                $this->_cache['orderToEmail'][$_order->getRealOrderId()] = strtolower($_order->getCustomerEmail());

                if (empty($this->_cache['orderToEmail'][$_order->getRealOrderId()]) ) {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::helper("tnw_salesforce")->log("SKIPPED: Sync for order #' . $_order->getRealOrderId() . ' failed, order is missing an email address!");
                        Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for order #' . $_order->getRealOrderId() . ' failed, order is missing an email address!');
                    }
                    continue;
                }

                $_customerGroup = $_order->getCustomerGroupId();
                if ($_customerGroup === NULL && !$this->isFromCLI()) {
                    $_customerGroup = Mage::getSingleton('customer/session')->getCustomerGroupId();
                }
                if (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($_customerGroup)) {
                    Mage::helper("tnw_salesforce")->log("SKIPPING: Sync for customer group #" . $_customerGroup . " is disabled!", 1, "sf-errors");
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for order #' . $_order->getId() . ', sync for customer group #' . $_customerGroup . ' is disabled!');
                    }
                    continue;
                }
                $_customerId = ($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId()) ? $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId() : 'guest-' . $_count;
                $_emails[$_customerId] = strtolower($_order->getCustomerEmail());
                $_orderNumbers[$_id] = $_order->getRealOrderId();

                $_websiteId = Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
                $_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];
            }

            if (empty($_orderNumbers)) {
                Mage::helper("tnw_salesforce")->log("SKIPPING: Skipping syncronization, orders array is empty!", 1, "sf-errors");

                return true;
            }
            $this->_cache['entitiesUpdating'] = $_orderNumbers;

            // Force sync of the customer if Account Rename is turned on
            if (Mage::helper('tnw_salesforce')->canRenameAccount()) {
                $_customerToSync = array();
                foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
                    // here may be potential bug where we lost some orders
                    $_customerToSync[$_orderNumber] = $this->_getCustomer(Mage::registry('order_cached_' . $_orderNumber));
                }

                Mage::helper("tnw_salesforce")->log('Syncronizing Guest accounts...');
                $manualSync = Mage::helper('tnw_salesforce/bulk_customer');
                if ($manualSync->reset()) {
                    $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
                    $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());
                    $manualSync->forceAdd($_customerToSync, $this->_cache['orderCustomers']);

                    // here we use $this->_cache['orderCustomers']
                    set_time_limit(1000);
                    $this->_cache['orderCustomers'] = $manualSync->process('bulk'); // and in process() we use $this->_toSyncOrderCustomers which is not equal to $this->_cache['orderCustomers']
                    set_time_limit(1000);
                }
            }


            $this->_cache['opportunityLookup'] = Mage::helper('tnw_salesforce/salesforce_data')->opportunityLookup($_orderNumbers);
            $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emails, $_websites);

            $_customersToSync = array();
            $_leadsToLookup = array();

            $_count = 0;
            foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
                $_order = Mage::getModel('sales/order')->load($_key);
                $_email = $this->_cache['orderToEmail'][$_orderNumber];
                $_websiteId = Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
                // here may be potential bug where we lost some orders
                if (!is_array($this->_cache['accountsLookup']) || !array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup']) || !array_key_exists($_email, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])) {
                    $_customerId = ($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId()) ? $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId() : 'guest-' . $_count;
                    $_leadsToLookup[$_customerId] = $_email;
                    $_leadsToLookupWebsites[$_customerId] = $this->_websiteSfIds[$_websiteId];

                    $this->_cache['orderCustomersToSync'][] = $_orderNumber;
                    $_customersToSync[$_orderNumber] = $this->_getCustomer($_order);
                    $_count++;
                }
            }

            // Lookup Leads we may need to convert
            if (!empty($_leadsToLookup)) {
                $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($_leadsToLookup,$_leadsToLookupWebsites);
                $_customersToSync = $this->_updateAccountLookupData($_customersToSync);
            }

            if (!empty($_customersToSync)) {
                Mage::helper("tnw_salesforce")->log('Syncronizing Guest accounts...');
                $manualSync = Mage::helper('tnw_salesforce/bulk_customer');
                if ($manualSync->reset()) {
                    $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
                    $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());
                    $manualSync->forceAdd($_customersToSync, $this->_cache['orderCustomers']);

                    // here we use $this->_cache['orderCustomers']
                    set_time_limit(1000);
                    $this->_cache['orderCustomers'] = $manualSync->process('bulk'); // and in process() we use $this->_toSyncOrderCustomers which is not equal to $this->_cache['orderCustomers']
                    set_time_limit(1000);
                }
                Mage::helper("tnw_salesforce")->log('Updating lookup cache...');
                // update Lookup values
                $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emails, $_websites);
                $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($_leadsToLookup,$_leadsToLookupWebsites);
                $_customersToSync = $this->_updateAccountLookupData($_customersToSync);
            }

            $_tmpArray = $this->_cache['orderCustomersToSync'];
            foreach ($_tmpArray as $_key => $_orderNum) {
                $_order = (Mage::registry('order_cached_' . $_orderNum)) ? Mage::registry('order_cached_' . $_orderNum) : Mage::getModel('sales/order')->load($_key);
                $_email = $this->_cache['orderToEmail'][$_orderNum];
                $_websiteId = Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();

                if (
                    (
                        is_array($this->_cache['leadLookup'])
                        && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['leadLookup'])
                        && array_key_exists($_email, $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]])
                    ) || (
                        is_array($this->_cache['accountsLookup'])
                        && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
                        && array_key_exists($_email, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])
                    ) || (
                        array_key_exists($_orderNum, $this->_cache['orderCustomers'])
                        && is_object($this->_cache['orderCustomers'][$_orderNum])
                        && $this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_id')
                        && $this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_account_id')
                    )
                ) {
                    unset($this->_cache['orderCustomersToSync'][$_key]);
                    unset($_customersToSync[$_orderNum]);
                }
            }

            // Check if any customers for orders have not been successfully synced
            if (!empty($_customersToSync)) {
                foreach ($_customersToSync as $_customer) {
                    $_email = $_customer->getEmail();
                    // Find matching orders
                    $_oIds = array_keys($this->_cache['orderToEmail'], $_email);
                    if (!empty($_oIds)) {
                        foreach ($_oIds as $_orderId) {
                            $_oIdsToRemove = array_keys($this->_cache['entitiesUpdating'], $_orderId);
                            foreach ($_oIdsToRemove as $_idToRemove) {
                                unset($this->_cache['entitiesUpdating'][$_idToRemove]);
                            }
                        }
                    }
                }
            }

            if (!isset($manualSync)) {
                $manualSync = Mage::helper('tnw_salesforce/bulk_customer');
            }
            if (is_array($this->_cache['leadLookup'])) {
                $manualSync->reset();
                $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
                $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());
                $_foundAccounts = array();
                foreach ($this->_cache['leadLookup'] as $websiteleads){
                    $_foundAccounts = array_merge($_foundAccounts, $manualSync->findCustomerAccountsForGuests(array_keys($websiteleads)));
                }
            } else {
                $_foundAccounts = array();
            }

            $_queueList = Mage::helper('tnw_salesforce/salesforce_data_queue')->getAllQueues();
            foreach ($this->_cache['orderToEmail'] as $_orderNum => $_email) {
                $this->_prepareLeadConversionObject($_orderNum, $_foundAccounts, $_queueList);
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
     * @param $_orderId
     * @param array $_accounts
     * @return bool
     */
    protected function _prepareLeadConversionObject($_orderId, $_accounts = array(), $_queueList = NULL)
    {
        if (!Mage::helper("tnw_salesforce")->getLeadConvertedStatus()) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: Converted Lead status is not set in the configuration, cannot proceed!');
            }
            Mage::helper("tnw_salesforce")->log('Converted Lead status is not set in the configuration, cannot proceed!', 1, "sf-errors");
            return false;
        }
        $_email = $this->_cache['orderToEmail'][$_orderId];
        $_order = (Mage::registry('order_cached_' . $_orderId)) ? Mage::registry('order_cached_' . $_orderId) : Mage::getModel('sales/order')->loadByIncrementId($_orderId);
        $_websiteId = Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();

        if (is_array($this->_cache['leadLookup'])
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['leadLookup'])
            && array_key_exists($_email, $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]])) {
            $leadConvert = new stdClass;
            $leadConvert->convertedStatus = Mage::helper("tnw_salesforce")->getLeadConvertedStatus();
            $leadConvert->doNotCreateOpportunity = 'true';
            $leadConvert->leadId = $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->Id;
            $leadConvert->overwriteLeadSource = 'false';
            $leadConvert->sendNotificationEmail = 'false';

            // Retain OwnerID if Lead is already assigned
            // If not, pull default Owner from Magento configuration
            if (
                is_object($this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email])
                && property_exists($this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email], 'OwnerId')
                && $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->OwnerId
                && (
                    !is_array($_queueList)
                    && !in_array($this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->OwnerId, $_queueList)
                )
            ) {
                $leadConvert->ownerId = $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->OwnerId;
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
                $this->_cache['leadsToConvert'][$_orderId] = $leadConvert;
            } else {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Order #' . $_orderId . ' - customer (email: ' . $_email . ') needs to be synchronized first, aborting!');
                }
                Mage::helper("tnw_salesforce")->log('Order #' . $_orderId . ' - customer (email: ' . $_email . ') needs to be synchronized first, aborting!', 1);
                return false;
            }
        }
    }

    protected function _convertLeads()
    {
        $_howMany = 80;
        // Make sure that leadsToConvert cache has unique leads (by email)
        $_leadsToConvert = array();
        foreach ($this->_cache['leadsToConvert'] as $_orderNum => $_objToConvert) {

            if (!in_array($_objToConvert->leadId, $_leadsToConvert)) {
                $_leadsToConvert[$_orderNum] = $_objToConvert->leadId;
            } else {
                $_source = array_search($_objToConvert->leadId, $_leadsToConvert);
                $this->_cache['duplicateLeadConversions'][$_orderNum] = $_source;
                unset($this->_cache['leadsToConvert'][$_orderNum]);
            }
        }

        $_ttl = count($this->_cache['leadsToConvert']);
        if ($_ttl > $_howMany) {
            $_steps = ceil($_ttl / $_howMany);
            if ($_steps == 0) {
                $_steps = 1;
            }
            for ($_i = 0; $_i < $_steps; $_i++) {
                $_start = $_i * $_howMany;
                $_itemsToPush = array_slice($this->_cache['leadsToConvert'], $_start, $_howMany, true);
                $this->_pushLeadSegment($_itemsToPush);
            }
        } else {
            $this->_pushLeadSegment($this->_cache['leadsToConvert']);
        }

        // Update de duped lead conversion records
        if (!empty($this->_cache['duplicateLeadConversions'])) {
            foreach($this->_cache['duplicateLeadConversions'] as $_what => $_source) {
                $this->_cache['convertedLeads'][$_what] = $this->_cache['convertedLeads'][$_source];
            }
        }
    }

    /**
     * @param null $_orderNumber
     * @return null
     */
    protected function _getCustomerAccountId($_orderNumber = NULL)
    {
        $_accountId = NULL;
        // Get email from the order object in Magento
        $_orderEmail = $this->_cache['orderToEmail'][$_orderNumber];
        // Get email from customer object in Magento
        $_customerEmail = (
            is_array($this->_cache['orderCustomers'])
            && array_key_exists($_orderNumber, $this->_cache['orderCustomers'])
            && is_object($this->_cache['orderCustomers'][$_orderNumber])
            && $this->_cache['orderCustomers'][$_orderNumber]->getData('email')
        ) ? strtolower($this->_cache['orderCustomers'][$_orderNumber]->getData('email')) : NULL;

        $_order = Mage::getModel('sales/order')->loadByIncrementId($_orderNumber);;
        $_websiteId = Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();

        if (is_array($this->_cache['accountsLookup']) && array_key_exists($_orderEmail, $this->_cache['accountsLookup'])) {
            $_accountId = $this->_cache['accountsLookup'][$_orderEmail]->AccountId;
        } elseif ($_customerEmail && $_orderEmail != $_customerEmail && is_array($this->_cache['accountsLookup']) && array_key_exists($_customerEmail, $this->_cache['accountsLookup'])) {
            $_accountId = $this->_cache['accountsLookup'][$_customerEmail]->AccountId;
        } elseif (is_array($this->_cache['convertedLeads']) && array_key_exists($_orderNumber, $this->_cache['convertedLeads'])) {
            $_accountId = $this->_cache['convertedLeads'][$_orderNumber]->accountId;
        }

        if (is_array($this->_cache['accountsLookup']) && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup']) && array_key_exists($_orderEmail, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])) {
            $_accountId = $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_orderEmail]->AccountId;
        } elseif (
            $_customerEmail
            && $_orderEmail != $_customerEmail
            && is_array($this->_cache['accountsLookup'])
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
            && array_key_exists($_customerEmail, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])
        ) {
            $_accountId = $this->_cache['accountsLookup'][$_customerEmail]->AccountId;
        } elseif (is_array($this->_cache['convertedLeads']) && array_key_exists($_orderNumber, $this->_cache['convertedLeads'])) {
            $_accountId = $this->_cache['convertedLeads'][$_orderNumber]->accountId;
        }
        return $_accountId;
    }

    /**
     * create opportunity object
     *
     * @param $order
     */
    protected function _setOpportunityInfo($order)
    {
        $_prefix = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix();
        $_websiteId = Mage::getModel('core/store')->load($order->getStoreId())->getWebsiteId();

        $this->_updateOrderStageName($order);
        $_orderNumber = $order->getRealOrderId();
        $_email = $this->_cache['orderToEmail'][$_orderNumber];

        $syncParam = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix() . "disableMagentoSync__c";
        $this->_obj->$syncParam = true;

        // Link to a Website
        if (
            $_websiteId != NULL
            && array_key_exists($_websiteId, $this->_websiteSfIds)
            && $this->_websiteSfIds[$_websiteId]
        ) {
            $this->_obj->{$_prefix . 'Website__c'} = $this->_websiteSfIds[$_websiteId];
        }

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $order->getData('order_currency_code');
        }

        // Magento Order ID
        $this->_obj->{$this->_magentoId} = $_orderNumber;
        // Force configured pricebook
        $this->_assignPricebookToOrder($order);

        // Close Date
        if ($order->getCreatedAt()) {
            // Always use order date as closing date if order already exists
            $this->_obj->CloseDate = gmdate(DATE_ATOM, strtotime($order->getCreatedAt()));
        } else {
            // this should never happen
            $this->_obj->CloseDate = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
        }

        // Account ID
        $this->_obj->AccountId = $this->_getCustomerAccountId($_orderNumber);
        // For guest, extract converted Account Id
        if (!$this->_obj->AccountId) {
            $this->_obj->AccountId = (
                array_key_exists($_orderNumber, $this->_cache['convertedLeads'])
                && property_exists($this->_cache['convertedLeads'][$_orderNumber], 'accountId')
            ) ? $this->_cache['convertedLeads'][$_orderNumber]->accountId : NULL;
        }

        $this->_processMapping($order, "Opportunity");

        //Get Account Name from Salesforce
        $_accountName = (
            $this->_cache['accountsLookup']
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
            && array_key_exists($_email, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])
            && $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->AccountName
        ) ? $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->AccountName : NULL;
        if (!$_accountName) {
            $_accountName = ($order->getBillingAddress()->getCompany()) ? $order->getBillingAddress()->getCompany() : NULL;
            if (!$_accountName) {
                $_accountName = ($_accountName && !$order->getShippingAddress()->getCompany()) ? $_accountName && !$order->getShippingAddress()->getCompany() : NULL;
                if (!$_accountName) {
                    $_accountName = $order->getCustomerFirstname() . " " . $order->getCustomerLastname();
                }
            }
        }

        $this->_setOpportunityName($_orderNumber, $_accountName);
        unset($order);
    }

    protected function _pushRemainingOpportunityData()
    {
        set_time_limit(1000);
        if (!empty($this->_cache['opportunityLineItemsToUpsert'])) {
            if (!$this->_cache['bulkJobs']['opportunityProducts']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['opportunityProducts']['Id'] = $this->_createJob('OpportunityLineItem', 'upsert', 'Id');
                Mage::helper('tnw_salesforce')->log('Syncronizing Opportunity Products, created job: ' . $this->_cache['bulkJobs']['opportunityProducts']['Id']);
            }

            Mage::dispatchEvent("tnw_salesforce_order_products_send_before",array("data" => $this->_cache['opportunityLineItemsToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['opportunityProducts']['Id'], 'opportunityProducts', $this->_cache['opportunityLineItemsToUpsert']);

            Mage::helper('tnw_salesforce')->log('Checking if Opportunity Products were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunityProducts']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                set_time_limit(1800);
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunityProducts']['Id']);
                Mage::helper('tnw_salesforce')->log('Still checking opportunityLineItemsToUpsert (job: ' . $this->_cache['bulkJobs']['opportunityProducts']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['opportunityProducts']['Id']);
            }

            if (strval($_result) != 'exception') {
                Mage::dispatchEvent("tnw_salesforce_order_products_send_after",array(
                    "data" => $this->_cache['opportunityLineItemsToUpsert'],
                    "result" => $this->_cache['responses']['opportunityProducts']
                ));
            }

            Mage::helper('tnw_salesforce')->log('Opportunities Products sync is complete! Moving on...');
        }

        if (!empty($this->_cache['contactRolesToUpsert'])) {
            if (!$this->_cache['bulkJobs']['customerRoles']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['customerRoles']['Id'] = $this->_createJob('OpportunityContactRole', 'upsert', 'Id');
                Mage::helper('tnw_salesforce')->log('Syncronizing Opportunity Contact Roles, created job: ' . $this->_cache['bulkJobs']['customerRoles']['Id']);
            }

            Mage::dispatchEvent("tnw_salesforce_order_contact_roles_send_before",array("data" => $this->_cache['contactRolesToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['customerRoles']['Id'], 'opportunityContactRoles', $this->_cache['contactRolesToUpsert']);

            Mage::helper('tnw_salesforce')->log('Checking if Opportunity Contact Roles were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['customerRoles']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                set_time_limit(1800);
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['customerRoles']['Id']);
                Mage::helper('tnw_salesforce')->log('Still checking contactRolesToUpsert (job: ' . $this->_cache['bulkJobs']['customerRoles']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['customerRoles']['Id']);
            }

            Mage::dispatchEvent("tnw_salesforce_order_contact_roles_send_after",array(
                "data" => $this->_cache['contactRolesToUpsert'],
                "result" => $this->_cache['responses']['customerRoles']
            ));

            Mage::helper('tnw_salesforce')->log('Opportunities Contact Roles sync is complete! Moving on...');
        }

        if (strval($_result) != 'exception') {
            $this->_checkRemainingData();
        }

        if (!empty($this->_cache['notesToUpsert'])) {
            if (!$this->_cache['bulkJobs']['notes']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['notes']['Id'] = $this->_createJob('Note', 'upsert', 'Id');
                Mage::helper('tnw_salesforce')->log('Syncronizing Notes, created job: ' . $this->_cache['bulkJobs']['notes']['Id']);
            }

            Mage::dispatchEvent("tnw_salesforce_order_notes_send_before",array("data" => $this->_cache['notesToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['notes']['Id'], 'notes', $this->_cache['notesToUpsert']);

            Mage::helper('tnw_salesforce')->log('Checking if Notes were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['notes']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                set_time_limit(1800);
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['notes']['Id']);
                Mage::helper('tnw_salesforce')->log('Still checking notesToUpsert (job: ' . $this->_cache['bulkJobs']['notes']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['notes']['Id']);
            }
            Mage::helper('tnw_salesforce')->log('Notes sync is complete! Moving on...');

            if (strval($_result) != 'exception') {
                $this->_checkNotesData();

                Mage::dispatchEvent("tnw_salesforce_order_notes_send_after",array(
                    "data" => $this->_cache['notesToUpsert'],
                    "result" => $this->_cache['responses']['notes']
                ));
            }
        }
    }

    protected function _checkNotesData()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        if (array_key_exists('notes', $this->_cache['batchCache'])) {
            foreach ($this->_cache['batchCache']['notes']['Id'] as $_key => $_batchId) {
                $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['notes']['Id'] . '/batch/' . $_batchId . '/result');
                try {
                    $response = $this->_client->request()->getBody();
                    $response = simplexml_load_string($response);
                    $_i = 0;
                    $sql = "";
                    $_batch = $this->_cache['batch']['notes']['Id'][$_key];
                    $_batchKeys = array_keys($_batch);
                    foreach ($response as $_item) {
                        $_noteId = $_batchKeys[$_i];
                        //Report Transaction
                        $this->_cache['responses']['notes'][$_noteId] = json_decode(json_encode($_item), TRUE);
                        $_orderId = (string)$_batch[$_noteId]->ParentId;
                        if ($_item->success == "false") {
                            $_oid = array_search($_orderId, $this->_cache['upsertedOpportunities']);
                            $this->_processErrors($_item, 'notes', $_batch[$_noteId]);
                            if (!in_array($_oid, $this->_cache['failedOpportunities'])) {
                                $this->_cache['failedOpportunities'][] = $_oid;
                            }
                        } else {
                            $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_status_history') . "` SET salesforce_id = '" . (string)$_item->id . "' WHERE entity_id = '" . $_noteId . "';";
                            Mage::helper('tnw_salesforce')->log('Note (id: ' . $_noteId . ') upserted for order #' . $_orderId . ')');
                        }
                        $_i++;
                    }
                    if (!empty($sql)) {
                        Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
                        $this->_write->query($sql);
                    }
                } catch (Exception $e) {
                    // TODO:  Log error, quit
                    $response = $e->getMessage();
                }
            }
        }
    }

    protected function _pushOpportunitiesToSalesforce()
    {
        if (!empty($this->_cache['opportunitiesToUpsert'])) {
            // assign owner id to opportunity
            $this->_assignOwnerIdToOpp();

            if (!$this->_cache['bulkJobs']['opportunity'][$this->_magentoId]) {
                // Create Job
                $this->_cache['bulkJobs']['opportunity'][$this->_magentoId] = $this->_createJob('Opportunity', 'upsert', $this->_magentoId);
                Mage::helper('tnw_salesforce')->log('Syncronizing Opportunities, created job: ' . $this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            }

            Mage::dispatchEvent("tnw_salesforce_order_send_before",array("data" => $this->_cache['opportunitiesToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['opportunity'][$this->_magentoId], 'opportunities', $this->_cache['opportunitiesToUpsert'], $this->_magentoId);

            Mage::helper('tnw_salesforce')->log('Checking if Opportunities were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                set_time_limit(1800);
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
                Mage::helper('tnw_salesforce')->log('Still checking opportunitiesToUpsert (job: ' . $this->_cache['bulkJobs']['opportunity'][$this->_magentoId] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            }
            Mage::helper('tnw_salesforce')->log('Opportunities sync is complete! Moving on...');

            if (strval($_result) != 'exception') {
                $this->_assignOpportunityIds();
            }
        } else {
            Mage::helper('tnw_salesforce')->log('No Opportunities found queued for the synchronization!');
        }
    }

    protected function _checkRemainingData()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        if (array_key_exists('opportunityProducts', $this->_cache['batchCache'])) {
            foreach ($this->_cache['batchCache']['opportunityProducts']['Id'] as $_key => $_batchId) {
                $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['opportunityProducts']['Id'] . '/batch/' . $_batchId . '/result');
                try {
                    $response = $this->_client->request()->getBody();
                    $response = simplexml_load_string($response);
                    $_i = 0;
                    $_batch = $this->_cache['batch']['opportunityProducts']['Id'][$_key];
                    foreach ($response as $_item) {
                        //Report Transaction
                        $this->_cache['responses']['opportunityLineItems'][] = json_decode(json_encode($_item), TRUE);
                        $_opportunityId = (string)$_batch[$_i]->OpportunityId;
                        if ($_item->success == "false") {
                            $_oid = array_search($_opportunityId, $this->_cache['upsertedOpportunities']);
                            $this->_processErrors($_item, 'opportunityProduct', $_batch[$_i]);
                            if (!in_array($_oid, $this->_cache['failedOpportunities'])) {
                                $this->_cache['failedOpportunities'][] = $_oid;
                            }
                        }
                        $_i++;
                    }
                } catch (Exception $e) {
                    // TODO:  Log error, quit
                    $response = $e->getMessage();
                }
            }
        }

        if (array_key_exists('opportunityContactRoles', $this->_cache['batchCache'])) {
            foreach ($this->_cache['batchCache']['opportunityContactRoles']['Id'] as $_key => $_batchId) {
                $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['customerRoles']['Id'] . '/batch/' . $_batchId . '/result');
                try {
                    $response = $this->_client->request()->getBody();
                    $response = simplexml_load_string($response);
                    $_i = 0;
                    $_batch = $this->_cache['batch']['opportunityContactRoles']['Id'][$_key];
                    foreach ($response as $_rKey => $_item) {
                        //Report Transaction
                        $this->_cache['responses']['opportunityCustomerRoles'][] = json_decode(json_encode($_item), TRUE);

                        $_opportunityId = (string)$_batch[$_i]->OpportunityId;
                        $_i++;
                        if ($_item->success == "false") {
                            $_oid = array_search($_opportunityId, $this->_cache['upsertedOpportunities']);
                            $this->_processErrors($_item, 'opportunityProduct', $_batch[$_i]);
                            if (!in_array($_oid, $this->_cache['failedOpportunities'])) {
                                $this->_cache['failedOpportunities'][] = $_oid;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // TODO:  Log error, quit
                    $response = $e->getMessage();
                }
            }
        }

        $sql = '';
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (!in_array($_orderNumber, $this->_cache['failedOpportunities'])) {
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 1 WHERE entity_id = " . $_key . ";";
            }
        }
        if ($sql != '') {
            Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
            $this->_write->query($sql);
        }
    }

    protected function _assignOpportunityIds()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());
        $_entityArray = array_flip($this->_cache['entitiesUpdating']);
        $sql = '';

        foreach ($this->_cache['batchCache']['opportunities'][$this->_magentoId] as $_key => $_batchId) {
            $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['opportunity'][$this->_magentoId] . '/batch/' . $_batchId . '/result');
            try {
                $response = $this->_client->request()->getBody();
                $response = simplexml_load_string($response);
                $_i = 0;
                $_batch = array_keys($this->_cache['batch']['opportunities'][$this->_magentoId][$_key]);
                foreach ($response as $_item) {
                    $_oid = $_batch[$_i];

                    //Report Transaction
                    $this->_cache['responses']['opportunities'][$_oid] = json_decode(json_encode($_item), TRUE);

                    if ($_item->success == "true") {
                        $this->_cache['upsertedOpportunities'][$_oid] = (string)$_item->id;
                        $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET salesforce_id = '" . $this->_cache['upsertedOpportunities'][$_oid] . "' WHERE entity_id = " . $_entityArray[$_oid] . ";";
                        Mage::helper('tnw_salesforce')->log('Opportunity Upserted: ' . $this->_cache['upsertedOpportunities'][$_oid]);

                        //unset($this->_cache['opportunitiesToUpsert'][$_oid]);
                    } else {
                        $this->_cache['failedOpportunities'][] = $_oid;
                        $this->_processErrors($_item, 'opportunity', $this->_cache['batch']['opportunities'][$this->_magentoId][$_key][$_oid]);
                    }
                    $_i++;
                }
            } catch (Exception $e) {
                // TODO:  Log error, quit
                $response = $e->getMessage();
            }
        }

        Mage::dispatchEvent("tnw_salesforce_order_send_after",array(
            "data" => $this->_cache['opportunitiesToUpsert'],
            "result" => $this->_cache['responses']['opportunities']
        ));

        if (!empty($sql)) {
            Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
            $this->_write->query($sql);
        }
    }

    /**
     * @param $_leadsChunkToConvert
     * @param int $_batch
     */
    protected function _pushLeadSegment($_leadsChunkToConvert, $_batch = 0)
    {
        $results = $this->_mySforceConnection->convertLead(array_values($_leadsChunkToConvert));

        $_keys = array_keys($_leadsChunkToConvert);

        foreach ($results->result as $_key => $_result) {
            $_orderNum = $_keys[$_key];

            // report transaction
            $this->_cache['responses']['leadsToConvert'][$_orderNum] = $_result;

            $_email = strtolower($this->_cache['orderToEmail'][$_orderNum]);
            $_order = (Mage::registry('order_cached_' . $_orderNum)) ? Mage::registry('order_cached_' . $_orderNum) : Mage::getModel('sales/order')->loadByIncrementId($_orderNum);
            $_websiteId = Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
            $_customerId = (is_object($this->_cache['orderCustomers'][$_orderNum])) ? $this->_cache['orderCustomers'][$_orderNum]->getId() : NULL;

            if (!$_result->success) {
                $this->_cache['leadsFaildToConvert'][$_orderNum] = $_email;
                // Remove entity from the sync queue
                $keyToRemove = array_search($_orderNum, $this->_cache['entitiesUpdating']);
                if ($keyToRemove) {
                    unset($this->_cache['entitiesUpdating'][$keyToRemove]);
                }
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Failed to convert Lead for Customer Email (' . $_email . ')');
                }
                Mage::helper('tnw_salesforce')->log('Convert Failed: (email: ' . $_email . ')', 1);
                $this->_processErrors($_result, 'order', $_leadsChunkToConvert[$_orderNum]);
            } else {
                if ($_customerId) {
                    $this->_cache['toSaveInMagento'][$_websiteId][$_customerId] = new stdClass();
                    $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->Email = $_email;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->ContactId = $_result->contactId;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->AccountId = $_result->accountId;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->WebsiteId = $this->_websiteSfIds[$this->_cache['orderCustomers'][$_orderNum]->getData('website_id')];

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
                    // For the guest
                    if (!is_object($this->_cache['orderCustomers'][$_orderNum])) {
                        $this->_cache['orderCustomers'][$_orderNum] = (is_object($_order)) ? $this->_getCustomer($_order) : Mage::getModel("customer/customer");
                    }
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

                unset($this->_cache['leadsToConvert'][$_orderNum]); // remove from cache
                unset($this->_cache['leadLookup'][$_websiteId][$_email]); // remove from cache

                Mage::helper('tnw_salesforce')->log('Converted: (account: ' . $this->_cache['convertedLeads'][$_orderNum]->accountId . ') and (contact: ' . $this->_cache['convertedLeads'][$_orderNum]->contactId . ')');
            }
        }
    }

    /**
     * @param null $_jobId
     * @param $_batchType
     * @param array $_entities
     * @param string $_on
     * @return bool
     */
    protected function _pushChunked($_jobId = NULL, $_batchType, $_entities = array(), $_on = 'Id')
    {
        if (!empty($_entities) && $_jobId) {
            if (!array_key_exists($_batchType, $this->_cache['batch'])) {
                $this->_cache['batch'][$_batchType] = array();
            }
            if (!array_key_exists($_on, $this->_cache['batch'][$_batchType])) {
                $this->_cache['batch'][$_batchType][$_on] = array();
            }
            $_ttl = count($_entities); // 205
            $_success = true;
            if ($_ttl > $this->_maxBatchLimit) {
                $_steps = ceil($_ttl / $this->_maxBatchLimit);
                if ($_steps == 0) {
                    $_steps = 1;
                }
                for ($_i = 0; $_i < $_steps; $_i++) {
                    $_start = $_i * $this->_maxBatchLimit;
                    $_itemsToPush = array_slice($_entities, $_start, $this->_maxBatchLimit, true);
                    if (!array_key_exists($_i, $this->_cache['batch'][$_batchType][$_on])) {
                        $this->_cache['batch'][$_batchType][$_on][$_i] = array();
                    }
                    $_success = $this->_pushSegment($_jobId, $_batchType, $_itemsToPush, $_i, $_on);
                }
            } else {
                if (!array_key_exists(0, $this->_cache['batch'][$_batchType][$_on])) {
                    $this->_cache['batch'][$_batchType][$_on][0] = array();
                }
                $_success = $this->_pushSegment($_jobId, $_batchType, $_entities, 0, $_on);

            }
            if (!$_success) {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . uc_words($_batchType) . ' upsert failed!');
                }
                Mage::helper('tnw_salesforce')->log('ERROR: ' . uc_words($_batchType) . ' upsert failed!');

                return false;
            }
        }

        return true;
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
            $this->_obj = new stdClass();

            $_customerId = $this->_cache['orderToCustomerId'][$_orderNumber];
            if (!Mage::registry('customer_cached_' . $_customerId)) {
                $_customer = $this->_cache['orderCustomers'][$_orderNumber];
            } else {
                $_customer = Mage::registry('customer_cached_' . $_customerId);
            }

            $_order = Mage::getModel('sales/order')->load($_key);
            $_email = strtolower($this->_cache['orderToEmail'][$_orderNumber]);
            $_websiteId = Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
            if (
                array_key_exists('accountsLookup', $this->_cache)
                && is_array($this->_cache['accountsLookup'])
                && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
                && array_key_exists($_email, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])
                && is_object($this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_email])
                && property_exists($this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_email], 'Id')
            ) {
                $this->_obj->ContactId = $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->Id;
            } elseif (array_key_exists($_orderNumber, $this->_cache['convertedLeads'])) {
                $this->_obj->ContactId = $this->_cache['convertedLeads'][$_orderNumber]->contactId;
            } else {
                $this->_obj->ContactId = $_customer->getSalesforceId();
            }

            // Check if already exists
            $_skip = false;
            if ($this->_cache['opportunityLookup'] && array_key_exists($_orderNumber, $this->_cache['opportunityLookup']) && $this->_cache['opportunityLookup'][$_orderNumber]->OpportunityContactRoles) {
                foreach ($this->_cache['opportunityLookup'][$_orderNumber]->OpportunityContactRoles->records as $_role) {
                    if (property_exists($this->_obj, 'ContactId') && property_exists($_role, 'ContactId') && $_role->ContactId == $this->_obj->ContactId) {
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

                if (property_exists($this->_obj, 'ContactId') && $this->_obj->ContactId) {
                    $this->_cache['contactRolesToUpsert'][] = $this->_obj;
                } else {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('Was not able to convert customer Lead, skipping Opportunity Contact Role assignment. Please synchronize customer (email: ' . $_email . ')');
                    }
                    Mage::helper('tnw_salesforce')->log('Was not able to convert customer Lead, skipping Opportunity Contact Role assignment. Please synchronize customer (email: ' . $_email . ')');
                }
            }
        }
        Mage::helper('tnw_salesforce')->log('----------Prepare Opportunity Contact Role: End----------');
    }

    protected function _onComplete()
    {
        // Close Jobs
        if ($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]) {
            $this->_closeJob($this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['opportunity'][$this->_magentoId]);
        }
        if ($this->_cache['bulkJobs']['opportunityProducts']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['opportunityProducts']['Id']);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['opportunityProducts']['Id']);
        }
        if ($this->_cache['bulkJobs']['customerRoles']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['customerRoles']['Id']);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['customerRoles']['Id']);
        }
        if ($this->_cache['bulkJobs']['notes']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['notes']['Id']);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['notes']['Id']);
        }

        Mage::helper('tnw_salesforce')->log('Clearing bulk sync cache...');

        $this->_cache['bulkJobs'] = array(
            'opportunity' => array($this->_magentoId => NULL),
            'opportunityProducts' => array('Id' => NULL),
            'customerRoles' => array('Id' => NULL),
            'notes' => array('Id' => NULL),
        );

        parent::_onComplete();
    }

    /**
     * @return bool
     */
    public function reset()
    {
        parent::reset();

        $this->_cache['bulkJobs'] = array(
            'opportunity' => array($this->_magentoId => NULL),
            'opportunityProducts' => array('Id' => NULL),
            'customerRoles' => array('Id' => NULL),
            'notes' => array('Id' => NULL),
        );

        $this->_cache['batch'] = array();
        $this->_cache['batchCache'] = array();
        $this->_cache['duplicateLeadConversions'] = array();

        return $this->check();
    }
}