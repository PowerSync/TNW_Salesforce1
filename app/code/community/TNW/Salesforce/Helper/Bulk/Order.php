<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Bulk_Order extends TNW_Salesforce_Helper_Salesforce_Order
{
    /**
     * @var array
     */
    protected $_allResults = array(
        'orders' => array(),
        'order_products' => array(),
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
            $_quotes = array();
            // Clear Order ID
            $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET salesforce_id = NULL WHERE entity_id IN (" . join(',', $ids) . ");";
            $sql .= ";UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 0 WHERE entity_id IN (" . join(',', $ids) . ");";

            $this->_write->query($sql);
            Mage::helper('tnw_salesforce')->log("Order ID and Sync Status for order (#" . join(',', $ids) . ") were reset.");

            $_guestCount = 0;
            $_skippedOrders = array();
            foreach ($ids as $_count => $_id) {
                $_order = Mage::getModel('sales/order')->load($_id);
                // Add to cache
                if (Mage::registry('order_cached_' . $_order->getRealOrderId())) {
                    Mage::unregister('order_cached_' . $_order->getRealOrderId());
                }
                Mage::register('order_cached_' . $_order->getRealOrderId(), $_order);

                /**
                 * @comment check zero orders sync
                 */
                if (!Mage::helper('tnw_salesforce/order')->isEnabledZeroOrderSync() && $_order->getGrandTotal() == 0) {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for order #' . $_order->getRealOrderId() . ', grand total is zero and synchronization for these order is disabled in configuration!');
                    }
                    Mage::helper("tnw_salesforce")->log('SKIPPED: Sync for order #' . $_order->getRealOrderId() . ', grand total is zero and synchronization for these order is disabled in configuration!');
                    $_skippedOrders[] = $_order->getId();
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
                    $_skippedOrders[] = $_order->getId();
                    continue;
                }

                if (!$_order->getId() || !$_order->getRealOrderId()) {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Sync for order #' . $_id . ', order could not be loaded!');
                    }
                    Mage::helper("tnw_salesforce")->log("SKIPPING: Sync for order #" . $_id . ", order could not be loaded!", 1, "sf-errors");
                    $_skippedOrders[] = $_order->getId();
                    continue;
                }

                $this->_cache['orderCustomers'][$_order->getRealOrderId()] = $this->_getCustomer($_order);
                $_customerId = ($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId()) ? $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId() : 'guest-' . $_guestCount;
                if (!$this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId()) {
                    $_guestCount++;
                }
                $_emails[$_customerId] = ($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getEmail()) ? strtolower($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getEmail()) : strtolower($_order->getCustomerEmail());

                $this->_cache['orderToEmail'][$_order->getRealOrderId()] = $_emails[$_customerId];
                $this->_cache['orderToCustomerId'][$_order->getRealOrderId()] = $_customerId;

                if (empty($this->_cache['orderToEmail'][$_order->getRealOrderId()]) ) {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::helper("tnw_salesforce")->log('SKIPPED: Sync for order #' . $_order->getRealOrderId() . ' failed, order is missing an email address!');
                        Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for order #' . $_order->getRealOrderId() . ' failed, order is missing an email address!');
                    }
                    $_skippedOrders[] = $_order->getId();
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
                    $_skippedOrders[] = $_order->getId();
                    continue;
                }
                $_orderNumbers[$_id] = $_order->getRealOrderId();

                $_websiteId = Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
                $_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];
                if ($_order->getQuoteId()) {
                    $_quotes[] = $_order->getQuoteId();
                }
            }

            if (!empty($_skippedOrders)) {
                $sql = "DELETE FROM `" . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . "` WHERE object_id IN ('" . join("','", $_skippedOrders) . "') and mage_object_type = 'sales/order';";
                Mage::helper('tnw_salesforce')->getDbConnection('delete')->query($sql);
            }

            $this->_findAbandonedCart($_quotes);

            if (empty($_orderNumbers)) {
                Mage::helper("tnw_salesforce")->log("SKIPPING: Skipping synchronization, orders array is empty!", 1, "sf-errors");

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

                Mage::helper("tnw_salesforce")->log('Synchronizing guest customers...');
                $manualSync = Mage::helper('tnw_salesforce/bulk_customer');
                if ($manualSync->reset()) {
                    $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
                    $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());
                    $manualSync->forceAdd($_customerToSync, $this->_cache['orderCustomers']);

                    // here we use $this->_cache['orderCustomers']
                    $this->_cache['orderCustomers'] = $manualSync->process('bulk'); // and in process() we use $this->_toSyncOrderCustomers which is not equal to $this->_cache['orderCustomers']
                }
            }

            $this->_cache['orderLookup'] = Mage::helper('tnw_salesforce/salesforce_data_order')->lookup($_orderNumbers);
            $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($_emails, $_websites);
            $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emails, $_websites);
            $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($_emails, $_websites);

            $_customersToSync = array();
            $_leadsToLookup = array();

            $_count = 0;

            foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
                $_order = (Mage::registry('order_cached_' . $_orderNumber)) ? Mage::registry('order_cached_' . $_orderNumber) : Mage::getModel('sales/order')->load($_key);
                $_email = $this->_cache['orderToEmail'][$_orderNumber];
                $_websiteId = Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
                // here may be potential bug where we lost some orders
                if (
                    is_array($this->_cache['accountsLookup'])
                    && array_key_exists(0, $this->_cache['accountsLookup'])
                    && array_key_exists($_email, $this->_cache['accountsLookup'][0])
                ) {
                    if (property_exists($this->_cache['accountsLookup'][0][$_email], 'Id')) {
                        $this->_cache['orderCustomers'][$_orderNumber]->setData('salesforce_account_id', $this->_cache['accountsLookup'][0][$_email]->Id);
                    }

                    if (property_exists($this->_cache['accountsLookup'][0][$_email], 'Id')) {
                        $this->_cache['orderCustomers'][$_orderNumber]->setData('salesforce_id', $this->_cache['accountsLookup'][0][$_email]->Id);
                    }
                    // Overwrite Contact Id for Person Account
                    if (property_exists($this->_cache['accountsLookup'][0][$_email], 'PersonContactId')) {
                        $this->_cache['orderCustomers'][$_orderNumber]->setData('salesforce_id', $this->_cache['accountsLookup'][0][$_email]->PersonContactId);
                    }

                    // Overwrite from Contact Lookup if value exists there
                    if (
                        is_array($this->_cache['contactsLookup'])
                        && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['contactsLookup'])
                        && array_key_exists($_email, $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]])
                    ) {
                        $this->_cache['orderCustomers'][$_orderNumber]->setData('salesforce_id', $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->Id);
                    }
                } else {
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
                $_customersToSync = $this->_updateAccountLookupData($_customersToSync);
            }

            if (!empty($_customersToSync)) {
                Mage::helper("tnw_salesforce")->log('Syncronizing guest accounts...');
                $manualSync = Mage::helper('tnw_salesforce/bulk_customer');
                if ($manualSync->reset()) {
                    $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
                    $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());
                    $manualSync->forceAdd($_customersToSync, $this->_cache['orderCustomers']);

                    // here we use $this->_cache['orderCustomers']
                    $this->_cache['orderCustomers'] = $manualSync->process('bulk'); // and in process() we use $this->_toSyncOrderCustomers which is not equal to $this->_cache['orderCustomers']
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
                        is_array($this->_cache['orderCustomers'])
                        && array_key_exists($_orderNum, $this->_cache['orderCustomers'])
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
                                Mage::helper('tnw_salesforce')->log("SKIPPED Order: " . $_idToRemove . " - customer (" . $_email . ") could not be synchronized");
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
                    $_foundAccounts = array_merge($_foundAccounts, $manualSync->findCustomerAccounts(array_keys($websiteleads)));
                }
            } else {
                $_foundAccounts = array();
            }

            foreach ($this->_cache['orderToEmail'] as $_orderNum => $_email) {
                Mage::helper('tnw_salesforce/salesforce_data_lead')->setParent($this)->prepareLeadConversionObject($_orderNum, $_foundAccounts, 'order');
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
     * @comment call leads convertation method
     */
    protected function _convertLeads()
    {
        return Mage::helper('tnw_salesforce/salesforce_data_lead')->setParent($this)->convertLeadsBulk('order');
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

        $_order = (Mage::registry('order_cached_' . $_orderNumber)) ? Mage::registry('order_cached_' . $_orderNumber) : Mage::getModel('sales/order')->loadByIncrementId($_orderNumber);
        $_websiteId = Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();

        if (
            is_array($this->_cache['accountsLookup'])
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
            && array_key_exists($_orderEmail, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])
        ) {
            $_accountId = $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_orderEmail]->AccountId;
        } elseif (
            $_customerEmail && $_orderEmail != $_customerEmail
            && is_array($this->_cache['accountsLookup'])
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
            && array_key_exists($_customerEmail, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])
        ) {
            $_accountId = $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_customerEmail]->AccountId;
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
            $_accountId = $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_customerEmail]->AccountId;
        } elseif (is_array($this->_cache['convertedLeads']) && array_key_exists($_orderNumber, $this->_cache['convertedLeads'])) {
            $_accountId = $this->_cache['convertedLeads'][$_orderNumber]->accountId;
        }
        return $_accountId;
    }

    /**
     * Push cart items, notes
     */
    protected function _pushRemainingOrderData()
    {
        if (!empty($this->_cache['orderItemsToUpsert'])) {
            if (!$this->_cache['bulkJobs']['orderProducts']['Id']) {
                // Create Job
                $this->_cache['bulkJobs']['orderProducts']['Id'] = $this->_createJob('OrderItem', 'upsert', 'Id');
                Mage::helper('tnw_salesforce')->log('Syncronizing Order Products, created job: ' . $this->_cache['bulkJobs']['orderProducts']['Id']);
            }

            Mage::dispatchEvent("tnw_salesforce_order_products_send_before",array("data" => $this->_cache['orderItemsToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['orderProducts']['Id'], 'orderProducts', $this->_cache['orderItemsToUpsert']);

            Mage::helper('tnw_salesforce')->log('Checking if Order Products were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['orderProducts']['Id']);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['orderProducts']['Id']);
                Mage::helper('tnw_salesforce')->log('Still checking orderItemsToUpsert (job: ' . $this->_cache['bulkJobs']['orderProducts']['Id'] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['orderProducts']['Id']);
            }
            Mage::helper('tnw_salesforce')->log('Order Products sync is complete! Moving on...');

            if (strval($_result) != 'exception') {
                $this->_checkOrderProductData();

                Mage::dispatchEvent("tnw_salesforce_order_products_send_after",array(
                    "data" => $this->_cache['orderItemsToUpsert'],
                    "result" => $this->_cache['responses']['orderProducts']
                ));
            }
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

        // Kick off the event to allow additional data to be pushed into salesforce
        Mage::dispatchEvent("tnw_salesforce_order_sync_after_final",array(
            "all" => $this->_cache['entitiesUpdating'],
            "failed" => $this->_cache['failedOrders']
        ));

        // Mark orders as failed or successful
        $this->_updateOrders();

        // Activate orders
        if (!empty($this->_cache['orderToActivate'])) {
            foreach($this->_cache['orderToActivate'] as $_orderNum => $_object) {
                if (array_key_exists($_orderNum, $this->_cache  ['upserted' . $this->getManyParentEntityType()])) {
                    $_object->Id = $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_orderNum];

                    // Check if at least 1 product was added to the order before we try to activate
                    if (
                        !array_key_exists($_object->Id, $this->_cache['orderItemsProductsToSync'])
                        || empty($this->_cache['orderItemsProductsToSync'][$_object->Id])
                    ) {
                        unset($this->_cache['orderToActivate'][$_orderNum]);
                        Mage::helper('tnw_salesforce')->log('SKIPPING ACTIVATION: Order (' . $_orderNum . ') Products did not make it into Salesforce.');
                        if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                            Mage::getSingleton('adminhtml/session')->addNotice("SKIPPING ORDER ACTIVATION: Order (" . $_orderNum . ") could not be activated w/o any products!");
                        }
                    }
                } else {
                    unset($this->_cache['orderToActivate'][$_orderNum]);
                    Mage::helper('tnw_salesforce')->log('SKIPPING ACTIVATION: Order (' . $_orderNum . ') did not make it into Salesforce.');
                }
            }
            if (!empty($this->_cache['orderToActivate'])) {
                Mage::helper('tnw_salesforce')->log('----------Activating Orders: Start----------');
                // Push Cart
                $_ttl = count($this->_cache['orderToActivate']);
                if ($_ttl > 199) {
                    $_steps = ceil($_ttl / 199);
                    for ($_i = 0; $_i < $_steps; $_i++) {
                        $_start = $_i * 200;
                        $_itemsToPush = array_slice($this->_cache['orderToActivate'], $_start, $_start + 199);
                        $this->_activateOrders($_itemsToPush);
                    }
                } else {
                    $this->_activateOrders($this->_cache['orderToActivate']);
                }
                Mage::helper('tnw_salesforce')->log('----------Activating Orders: End----------');
            }
        }
    }

    protected function _pushOrdersToSalesforce()
    {
        if (!empty($this->_cache['ordersToUpsert'])) {

            if (!$this->_cache['bulkJobs']['order'][$this->_magentoId]) {
                // Create Job
                $this->_cache['bulkJobs']['order'][$this->_magentoId] = $this->_createJob('Order', 'upsert', $this->_magentoId);
                Mage::helper('tnw_salesforce')->log('Syncronizing Orders, created job: ' . $this->_cache['bulkJobs']['order'][$this->_magentoId]);
            }

            Mage::dispatchEvent("tnw_salesforce_order_send_before",array("data" => $this->_cache['ordersToUpsert']));

            $this->_pushChunked($this->_cache['bulkJobs']['order'][$this->_magentoId], 'orders', $this->_cache['ordersToUpsert'], $this->_magentoId);

            Mage::helper('tnw_salesforce')->log('Checking if Orders were successfully synced...');
            $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['order'][$this->_magentoId]);
            $_attempt = 1;
            while (strval($_result) != 'exception' && !$_result) {
                sleep(5);
                $_result = $this->_checkBatchCompletion($this->_cache['bulkJobs']['order'][$this->_magentoId]);
                Mage::helper('tnw_salesforce')->log('Still checking ordersToUpsert (job: ' . $this->_cache['bulkJobs']['order'][$this->_magentoId] . ')...');
                $_attempt++;

                $_result = $this->_whenToStopWaiting($_result, $_attempt, $this->_cache['bulkJobs']['order'][$this->_magentoId]);
            }
            Mage::helper('tnw_salesforce')->log('Orders sync is complete! Moving on...');

            if (strval($_result) != 'exception') {
                $this->_assignOrderIds();
            }
        } else {
            Mage::helper('tnw_salesforce')->log('No Orders were queued for synchronization!');
        }
    }

    protected function _checkOrderProductData()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());

        if (array_key_exists('orderProducts', $this->_cache['batchCache'])) {
            $_sql = "";
            foreach ($this->_cache['batchCache']['orderProducts']['Id'] as $_key => $_batchId) {
                $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['orderProducts']['Id'] . '/batch/' . $_batchId . '/result');
                try {
                    $response = $this->_client->request()->getBody();
                    $response = simplexml_load_string($response);
                    $_i = 0;
                    $_batch = $this->_cache['batch']['orderProducts']['Id'][$_key];
                    $_batchKeys = array_keys($_batch);
                    foreach ($response as $_item) {
                        //Report Transaction
                        $this->_cache['responses']['orderProducts'][] = json_decode(json_encode($_item), TRUE);
                        $_orderId = (string)$_batch[$_batchKeys[$_i]]->OrderId;
                        if ($_item->success == "false") {
                            $_oid = array_search($_orderId, $this->_cache  ['upserted' . $this->getManyParentEntityType()]);
                            $this->_processErrors($_item, 'orderProduct', $_batch[$_batchKeys[$_i]]);
                            if (!in_array($_oid, $this->_cache['failedOrders'])) {
                                $this->_cache['failedOrders'][] = $_oid;
                            }
                        } else {
                            $_cartItemId = $_batchKeys[$_i];
                            if ($_cartItemId) {
                                $_sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_item') . "` SET salesforce_id = '" . $_item->id . "' WHERE item_id = '" . str_replace('cart_','',$_cartItemId) . "';";
                            }
                        }
                        $_i++;
                    }
                } catch (Exception $e) {
                    // TODO:  Log error, quit
                }
            }
            if (!empty($_sql)) {
                Mage::helper('tnw_salesforce')->getDbConnection()->query($_sql);
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
                            $_oid = array_search($_orderId, $this->_cache  ['upserted' . $this->getManyParentEntityType()]);
                            $this->_processErrors($_item, 'notes', $_batch[$_noteId]);
                            if (!in_array($_oid, $this->_cache['failedOrders'])) {
                                $this->_cache['failedOrders'][] = $_oid;
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
                }
            }
        }
    }

    protected function _updateOrders() {
        $sql = '';
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (!in_array($_orderNumber, $this->_cache['failedOrders'])) {
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 1 WHERE entity_id = " . $_key . ";";
            }
        }
        if ($sql != '') {
            Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
            $this->_write->query($sql);
        }
    }

    protected function _assignOrderIds()
    {
        $this->_client->setMethod('GET');
        $this->_client->setHeaders('Content-Type: application/xml');
        $this->_client->setHeaders('X-SFDC-Session', $this->getSalesforceSessionId());
        $_entityArray = array_flip($this->_cache['entitiesUpdating']);
        $sql = '';

        foreach ($this->_cache['batchCache']['orders'][$this->_magentoId] as $_key => $_batchId) {
            $this->_client->setUri($this->getSalesforceServerDomain() . '/services/async/' . $this->_salesforceApiVersion . '/job/' . $this->_cache['bulkJobs']['order'][$this->_magentoId] . '/batch/' . $_batchId . '/result');
            try {
                $response = $this->_client->request()->getBody();
                $response = simplexml_load_string($response);
                $_i = 0;
                $_batch = array_keys($this->_cache['batch']['orders'][$this->_magentoId][$_key]);
                foreach ($response as $_item) {
                    $_oid = $_batch[$_i];

                    //Report Transaction
                    $this->_cache['responses']['orders'][$_oid] = json_decode(json_encode($_item), TRUE);

                    if ($_item->success == "true") {
                        $_orderStatus = is_array( $this->_cache['ordersToUpsert'])
                                && array_key_exists($_oid, $this->_cache['ordersToUpsert'])
                                && property_exists($this->_cache['ordersToUpsert'][$_oid], 'Status')
                            ? $this->_cache['ordersToUpsert'][$_oid]->Status
                            : TNW_Salesforce_Helper_Salesforce_Data_Order::DRAFT_STATUS;

                        $_orderStatus = is_array( $this->_cache['orderLookup'])
                                && array_key_exists($_oid, $this->_cache['orderLookup'])
                                && property_exists($this->_cache['orderLookup'][$_oid], 'Status')
                            ? $this->_cache['orderLookup'][$_oid]->Status : $_orderStatus;

                        $this->_cache['upsertedOrderStatuses'][$_oid] = $_orderStatus;

                        $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_oid] = (string)$_item->id;

                        $_contactId = ($this->_cache['orderCustomers'][$_oid]->getData('salesforce_id')) ? "'" . $this->_cache['orderCustomers'][$_oid]->getData('salesforce_id') . "'" : 'NULL';
                        $_accountId = ($this->_cache['orderCustomers'][$_oid]->getData('salesforce_account_id')) ? "'" . $this->_cache['orderCustomers'][$_oid]->getData('salesforce_account_id') . "'" : 'NULL';
                        $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET contact_salesforce_id = " . $_contactId . ", account_salesforce_id = " . $_accountId . ", sf_insync = 1, salesforce_id = '" . $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_oid] . "' WHERE entity_id = " . $_entityArray[$_oid] . ";";

                        Mage::helper('tnw_salesforce')->log('Order Upserted: ' . $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_oid]);

                        if (Mage::registry('order_cached_' . $_oid)) {
                            $_order = Mage::registry('order_cached_' . $_oid);
                            Mage::unregister('order_cached_' . $_oid);
                            $_order->setData('salesforce_id', $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_oid]);
                            $_order->setData('sf_insync', 1);
                            Mage::register('order_cached_' . $_oid, $_order);
                            unset($_order);
                        }
                    } else {
                        $this->_cache['failedOrders'][] = $_oid;
                        $this->_processErrors($_item, 'order', $this->_cache['batch']['orders'][$this->_magentoId][$_key][$_oid]);
                    }
                    $_i++;
                }
            } catch (Exception $e) {
                // TODO:  Log error, quit
            }
        }

        Mage::dispatchEvent("tnw_salesforce_order_send_after",array(
            "data" => $this->_cache['ordersToUpsert'],
            "result" => $this->_cache['responses']['orders']
        ));

        if (!empty($sql)) {
            Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
            Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
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

    protected function _onComplete()
    {
        // Close Jobs
        if ($this->_cache['bulkJobs']['order'][$this->_magentoId]) {
            $this->_closeJob($this->_cache['bulkJobs']['order'][$this->_magentoId]);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['order'][$this->_magentoId]);
        }
        if ($this->_cache['bulkJobs']['orderProducts']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['orderProducts']['Id']);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['orderProducts']['Id']);
        }
        if ($this->_cache['bulkJobs']['notes']['Id']) {
            $this->_closeJob($this->_cache['bulkJobs']['notes']['Id']);
            Mage::helper('tnw_salesforce')->log("Closing job: " . $this->_cache['bulkJobs']['notes']['Id']);
        }
        Mage::helper('tnw_salesforce')->log('Clearing bulk sync cache...');

        $this->_cache['bulkJobs'] = array(
            'order' => array($this->_magentoId => NULL),
            'orderProducts' => array('Id' => NULL),
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
            'order' => array($this->_magentoId => NULL),
            'orderProducts' => array('Id' => NULL),
            'notes' => array('Id' => NULL),
        );

        $this->_cache['batch'] = array();
        $this->_cache['batchCache'] = array();
        $this->_cache['duplicateLeadConversions'] = array();

        $valid = $this->check();

        return $valid;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function process($type = 'soft')
    {

        /**
         * @comment apply bulk server settings
         */
        $this->getServerHelper()->apply(TNW_Salesforce_Helper_Config_Server::BULK);

        $result = parent::process($type);

        /**
         * @comment restore server settings
         */
        $this->getServerHelper()->apply();

        return $result;
    }
}