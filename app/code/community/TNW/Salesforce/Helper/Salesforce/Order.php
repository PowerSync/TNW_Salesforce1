<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Order
 */
class TNW_Salesforce_Helper_Salesforce_Order extends TNW_Salesforce_Helper_Salesforce_Abstract_Order
{

    /**
     * @comment magento entity alias "convert from"
     * @var string
     */
    protected $_magentoEntityName = 'order';


    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = 'order';

    /**
     * @comment magento entity model alias
     * @var string
     */
    protected $_magentoEntityModel = 'sales/order';

    /**
     * @comment magento entity model alias
     * @var string
     */
    protected $_magentoEntityId = 'increment_id';

    /**
     * @comment magento entity item qty field name
     * @var string
     */
    protected $_itemQtyField = 'qty_ordered';

    /**
     * @comment salesforce field name to assign parent entity
     * @var string
     */
    protected $_salesforceParentIdField = 'OrderId';

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

            $this->_alternativeKeys = $this->_cache['entitiesUpdating'];

            $this->_prepareOrders($type);

            $this->_pushOrdersToSalesforce();
            $this->clearMemory();

            set_time_limit(1000);

            if ($type == 'full') {
                if (Mage::helper('tnw_salesforce')->doPushShoppingCart()) {
                    $this->_prepareOrderItems();
                }
                if (Mage::helper('tnw_salesforce')->isOrderNotesEnabled()) {
                    $this->_prepareNotes();
                }

                $this->_pushRemainingOrderData();
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
     * Update Magento
     * @return bool
     */
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
        $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($_emailsArray, $_websites);
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

    /**
     * @depricated Exists compatibility for
     * @comment call leads convertation method
     */
    protected function _convertLeads()
    {
        return Mage::helper('tnw_salesforce/salesforce_data_lead')->setParent($this)->convertLeads('order');
    }

    /**
     * create order object
     *
     * @param $order
     */
    protected function _setOrderInfo($order)
    {
        $_websiteId = Mage::getModel('core/store')->load($order->getStoreId())->getWebsiteId();
        $_orderNumber = $order->getRealOrderId();
        $_email = $this->_cache['orderToEmail'][$_orderNumber];

        // For some reason some customers are removed from this list (some guests, not all)
        // Following logic loads the missing customer again
        if (!array_key_exists($_orderNumber, $this->_cache['orderCustomers']) || !is_object($this->_cache['orderCustomers'][$_orderNumber])) {
            $_customer = $this->_getCustomer($order);
            $this->_cache['orderCustomers'][$_orderNumber] = $_customer;
            if (is_array($this->_cache['accountsLookup'])
                && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
                && array_key_exists($_email, $this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]])
            ) {
                $this->_cache['orderCustomers'][$_orderNumber]->setSalesforceId($this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->Id);
                $this->_cache['orderCustomers'][$_orderNumber]->setSalesforceAccountId($this->_cache['accountsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->AccountId);
            }
        } else {
            $_customer = $this->_cache['orderCustomers'][$_orderNumber];
        }

        if ($_customer->getData('salesforce_id')) {
            $this->_obj->BillToContactId = $_customer->getData('salesforce_id');
            $this->_obj->ShipToContactId = $_customer->getData('salesforce_id');
        }

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $order->getData('order_currency_code');
        }

        if (
            !empty($this->_cache['abandonedCart'])
            && array_key_exists($order->getQuoteId(), $this->_cache['abandonedCart'])
        ) {
            $this->_obj->OpportunityId = $this->_cache['abandonedCart'][$order->getQuoteId()];
        }

        // Set proper Status
        $this->_updateOrderStatus($order);

        /**
         * Set 'Draft' status temporarry, it's necessary for order change with status from "Activated" group
         */
        $_currentStatus = $this->_obj->Status;
        if ($_currentStatus != TNW_Salesforce_Helper_Salesforce_Data_Order::DRAFT_STATUS) {
            $this->_obj->Status = TNW_Salesforce_Helper_Salesforce_Data_Order::DRAFT_STATUS;
            $_toActivate = new stdClass();
            $_toActivate->Status = $_currentStatus;
            $_toActivate->Id = NULL;
            $this->_cache['orderToActivate'][$_orderNumber] = $_toActivate;
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

        // Force configured pricebook
        $this->_assignPricebookToOrder($order);

        // Close Date
        if ($order->getCreatedAt()) {
            // Always use order date as closing date if order already exists
            $this->_obj->EffectiveDate = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($order->getCreatedAt())));
        } else {
            // this should never happen
            $this->_obj->EffectiveDate = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
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
        Mage::getSingleton('tnw_salesforce/sync_mapping_order_order')
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

        if (property_exists($this->_obj, 'OpportunityId') && empty($this->_obj->OpportunityId)) {
            unset($this->_obj->OpportunityId);
        }

        $this->_setOrderName($_orderNumber, $_accountName);
        unset($order);
    }

    /**
     * Prepare Salesforce Order object
     */
    protected function _prepareOrders()
    {
        Mage::helper('tnw_salesforce')->log('----------Order Preparation: Start----------');
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (array_key_exists('leadsFailedToConvert', $this->_cache) && is_array($this->_cache['leadsFailedToConvert']) && array_key_exists($_orderNumber, $this->_cache['leadsFailedToConvert'])) {
                Mage::helper('tnw_salesforce')->log('SKIPPED: Order (' . $_orderNumber . '), lead failed to convert');
                unset($this->_cache['entitiesUpdating'][$_key]);
                unset($this->_cache['orderToEmail'][$_orderNumber]);
                $this->_allResults['orders_skipped']++;
                continue;
            }
            if (!Mage::registry('order_cached_' . $_orderNumber)) {
                $_order = Mage::getModel('sales/order')->load($_key);
                Mage::register('order_cached_' . $_orderNumber, $_order);
            } else {
                $_order = Mage::registry('order_cached_' . $_orderNumber);
            }

            $this->_obj = new stdClass();
            $this->_setOrderInfo($_order);
            // Check if Pricebook Id does not match
            if (
                is_array($this->_cache['orderLookup'])
                && array_key_exists($_orderNumber, $this->_cache['orderLookup'])
                && property_exists($this->_cache['orderLookup'][$_orderNumber], 'Pricebook2Id')
                && $this->_obj->Pricebook2Id != $this->_cache['orderLookup'][$_orderNumber]->Pricebook2Id
            ) {
                Mage::helper('tnw_salesforce')->log("SKIPPED Order: " . $_orderNumber . " - Order uses a different pricebook(" . $this->_cache['orderLookup'][$_orderNumber]->Pricebook2Id . "), please change it in Salesforce.");
                unset($this->_cache['entitiesUpdating'][$_key]);
                unset($this->_cache['orderToEmail'][$_orderNumber]);
                $this->_allResults['orders_skipped']++;
            } else {
                $this->_cache['ordersToUpsert'][$_orderNumber] = $this->_obj;
            }
        }
        Mage::helper('tnw_salesforce')->log('----------Order Preparation: End----------');
    }

    /**
     * @return bool|void
     * Prepare values for the synchroization
     */
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
            'upsertedOrders' => array(),
            'upsertedOrderStatuses' => array(),
            'accountsLookup' => array(),
            'entitiesUpdating' => array(),
            'abandonedCart' => array(),
            'orderLookup' => array(),
            'ordersToUpsert' => array(),
            'orderItemsToUpsert' => array(),
            'leadsToConvert' => array(),
            'leadLookup' => array(),
            'orderCustomers' => array(),
            'toSaveInMagento' => array(),
            'contactsLookup' => array(),
            'failedOrders' => array(),
            'orderToEmail' => array(),
            'convertedLeads' => array(),
            'orderToCustomerId' => array(),
            'orderToActivate' => array(),
            'notesToUpsert' => array(),
            'responses' => array(
                'leadsToConvert' => array(),
                'orders' => array(),
                'orderItems' => array(),
                'notes' => array(),
            ),
            'orderCustomersToSync' => array(),
            'leadsFailedToConvert' => array()
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
     * Clean up all the data & memory
     */
    protected function _onComplete()
    {
        parent::_onComplete();

        if (Mage::helper('tnw_salesforce')->isRemoteLogEnabled()) {
            $logger = Mage::helper('tnw_salesforce/report');
            $logger->reset();

            $logger->add('Salesforce', 'leadsToConvert', $this->_cache['leadsToConvert'], $this->_cache['responses']['leadsToConvert']);
            $logger->add('Salesforce', 'Order', $this->_cache['ordersToUpsert'], $this->_cache['responses']['orders']);
            $logger->add('Salesforce', 'OrderItem', $this->_cache['orderItemsToUpsert'], $this->_cache['responses']['orderItems']);
            $logger->add('Salesforce', 'Note', $this->_cache['notesToUpsert'], $this->_cache['responses']['notes']);

            $logger->send();
        }

        // Logout
        $this->reset();
        $this->clearMemory();
    }

    /**
     * Sync customer w/ SF before creating the order
     *
     * @param $order
     * @return false|Mage_Core_Model_Abstract
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
                $row = Mage::helper('tnw_salesforce')->getDbConnection()->query($sql)->fetch();
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
                if (isset($_websiteId)) {
                    $_customer->setWebsiteId($_websiteId);
                }

                $_customer->setCreatedAt(gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($order->getCreatedAt()))));
                //TODO: Extract as much as we can from the order

            } else {
                //UPDATE order to record Customer Id
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET customer_id = " . $_customer->getId() . " WHERE entity_id = " . $order->getId() . ";";
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_grid') . "` SET customer_id = " . $_customer->getId() . " WHERE entity_id = " . $order->getId() . ";";
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_address') . "` SET customer_id = " . $_customer->getId() . " WHERE parent_id = " . $order->getId() . ";";
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
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

        // Set Company Name
        if (!$_customer->getData('company') && $order->getBillingAddress()->getData('company')) {
            $_customer->setData('company', $order->getBillingAddress()->getData('company'));
        } elseif (!$_customer->getData('company') && !Mage::helper('tnw_salesforce')->usePersonAccount()) {
            $_customer->setData('company', $_customer->getFirstname() . ' ' . $_customer->getLastname());
        }

        return $_customer;
    }

    /**
     * @param $orderNumber
     * @param $accountName
     * Create custom Order name in Salesforce
     */
    protected function _setOrderName($orderNumber, $accountName = NULL)
    {
        $this->_obj->Name = "Magento Order #" . $orderNumber;
    }

    /**
     * Push Order(s) to Salesforce
     */
    protected function _pushOrdersToSalesforce()
    {
        if (!empty($this->_cache['ordersToUpsert'])) {
            $_pushOn = $this->_magentoId;
            Mage::helper('tnw_salesforce')->log('----------Order Push: Start----------');
            foreach (array_values($this->_cache['ordersToUpsert']) as $_opp) {
                if (array_key_exists('Id', $_opp)) {
                    $_pushOn = 'Id';
                }
                foreach ($_opp as $_key => $_value) {
                    Mage::helper('tnw_salesforce')->log("Order Object: " . $_key . " = '" . $_value . "'");
                }
                Mage::helper('tnw_salesforce')->log("--------------------------");
            }

            try {
                Mage::dispatchEvent("tnw_salesforce_order_send_before", array("data" => $this->_cache['ordersToUpsert']));

                $_toSyncValues = array_values($this->_cache['ordersToUpsert']);
                $_keys = array_keys($this->_cache['ordersToUpsert']);
                $results = $this->_mySforceConnection->upsert($_pushOn, $_toSyncValues, 'Order');

                Mage::dispatchEvent("tnw_salesforce_order_send_after", array(
                    "data" => $this->_cache['ordersToUpsert'],
                    "result" => $results
                ));
            } catch (Exception $e) {
                $_response = $this->_buildErrorResponse($e->getMessage());
                foreach ($_keys as $_id) {
                    $this->_cache['responses']['orders'][$_id] = $_response;
                }
                $results = array();
                Mage::helper('tnw_salesforce')->log('CRITICAL: Push of an order to Salesforce failed' . $e->getMessage());
            }

            $_entityArray = array_flip($this->_cache['entitiesUpdating']);

            $_undeleteIds = array();
            if (!$results) {
                $results = array();
            }
            foreach ($results as $_key => $_result) {
                $_orderNum = $_keys[$_key];

                //Report Transaction
                $this->_cache['responses']['orders'][$_orderNum] = $_result;

                if (!$_result->success) {
                    if ($_result->errors[0]->statusCode == "ENTITY_IS_DELETED") {
                        $_undeleteIds[] = $_orderNum;
                    }

                    Mage::helper('tnw_salesforce')->log('Order Failed: (order: ' . $_orderNum . ')', 1, "sf-errors");
                    $this->_processErrors($_result, 'order', $this->_cache['ordersToUpsert'][$_orderNum]);
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Failed to upsert Order #' . $_orderNum);
                    }
                    $this->_cache['failedOrders'][] = $_orderNum;
                } else {
                    $_contactId = ($this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_id')) ? "'" . $this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_id') . "'" : 'NULL';
                    $_accountId = ($this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_account_id')) ? "'" . $this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_account_id') . "'" : 'NULL';
                    $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET contact_salesforce_id = " . $_contactId . ", account_salesforce_id = " . $_accountId . ", sf_insync = 1, salesforce_id = '" . $_result->id . "' WHERE entity_id = " . $_entityArray[$_orderNum] . ";";
                    Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
                    Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
                    $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_orderNum] = $_result->id;

                    if (Mage::registry('order_cached_' . $_orderNum)) {
                        $_order = Mage::registry('order_cached_' . $_orderNum);
                        Mage::unregister('order_cached_' . $_orderNum);
                        $_order->setData('salesforce_id', $_result->id);
                        $_order->setData('sf_insync', 1);
                        Mage::register('order_cached_' . $_orderNum, $_order);
                        unset($_order);
                    }

                    $_orderStatus = (
                        is_array($this->_cache['orderLookup'])
                        && array_key_exists($_orderNum, $this->_cache['orderLookup'])
                        && property_exists($this->_cache['orderLookup'][$_orderNum], 'Status')
                    ) ? $this->_cache['orderLookup'][$_orderNum]->Status : $this->_cache['ordersToUpsert'][$_orderNum]->Status;

                    $this->_cache['upsertedOrderStatuses'][$_orderNum] = $_orderStatus;
                    Mage::helper('tnw_salesforce')->log('Order Upserted: ' . $_result->id);
                }
            }
            if (!empty($_undeleteIds)) {
                $_deleted = Mage::helper('tnw_salesforce/salesforce_data_order')->lookup($_undeleteIds);
                $_toUndelete = array();
                foreach ($_deleted as $_object) {
                    $_toUndelete[] = $_object->Id;
                }
                if (!empty($_toUndelete)) {
                    $this->_mySforceConnection->undelete($_toUndelete);
                }
            }

            Mage::helper('tnw_salesforce')->log('----------Order Push: End----------');
        } else {
            Mage::helper('tnw_salesforce')->log('No Orders found queued for the synchronization!');
        }
    }

    /**
     * assign ownerId to order
     *
     * @return bool
     */
    protected function _assignOwnerIdToOrder()
    {
        $_websites = $_emailArray = array();
        foreach ($this->_cache['orderToEmail'] as $_oid => $_email) {
            $_customerId = $this->_cache['orderToCustomerId'][$_oid];
            $_emailArray[$_customerId] = $_email;
            $_order = Mage::registry('order_cached_' . $_oid);
            $_websiteId = (array_key_exists($_oid, $this->_cache['orderCustomers']) && $this->_cache['orderCustomers'][$_oid]->getData('website_id')) ? $this->_cache['orderCustomers'][$_oid]->getData('website_id') : Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
            $_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];
        }
        // update contact lookup data
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emailArray, $_websites);
        $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($_emailArray, $_websites);

        // assign owner id to opp
        foreach ($this->_cache['ordersToUpsert'] as $_orderNumber => $_opportunityData) {
            $_orderData = $this->_cache['ordersToUpsert'][$_orderNumber];
            $_email = $this->_cache['orderToEmail'][$_orderNumber];
            $_order = (Mage::registry('order_cached_' . $_orderNumber)) ? Mage::registry('order_cached_' . $_orderNumber) : Mage::getModel('sales/order')->loadByIncrementId($_orderNumber);
            $_websiteId = ($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getData('website_id')) ? $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getData('website_id') : Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
            $websiteSfId = $this->_websiteSfIds[$_websiteId];

            // Default Owner ID as configured in Magento
            $_orderData->OwnerId = Mage::helper('tnw_salesforce')->getDefaultOwner();
            if (
                is_array($this->_cache['orderLookup'])
                && array_key_exists($_orderNumber, $this->_cache['orderLookup'])
                && is_object($this->_cache['orderLookup'][$_orderNumber])
                && property_exists($this->_cache['orderLookup'][$_orderNumber], 'OwnerId')
                && $this->_cache['orderLookup'][$_orderNumber]->OwnerId
            ) {
                // Overwrite Owner ID if Opportuinity already exists, use existing owner
                $_orderData->OwnerId = $this->_cache['orderLookup'][$_orderNumber]->OwnerId;
            } elseif (
                $_email
                && is_array($this->_cache['contactsLookup'])
                && array_key_exists($websiteSfId, $this->_cache['contactsLookup'])
                && array_key_exists($_email, $this->_cache['contactsLookup'][$websiteSfId])
                && property_exists($this->_cache['contactsLookup'][$websiteSfId][$_email], 'OwnerId')
                && $this->_cache['contactsLookup'][$websiteSfId][$_email]->OwnerId
            ) {
                // Overwrite Owner ID, use Owner ID from Contact
                $_orderData->OwnerId = $this->_cache['contactsLookup'][$websiteSfId][$_email]->OwnerId;
            }
            // Reset back if inactive
            if (!$this->_isUserActive($_opportunityData->OwnerId)) {
                $_orderData->OwnerId = Mage::helper('tnw_salesforce')->getDefaultOwner();
            }
            $this->_cache['ordersToUpsert'][$_orderNumber] = $_orderData;
        }
        return true;
    }

    /**
     * Prepare Order items object(s) for upsert
     */
    protected function _prepareOrderItems()
    {
        Mage::helper('tnw_salesforce')->log('----------Prepare Cart Items: Start----------');

        // only sync all products if processing real time
        if (!$this->_isCron) {
            // Get all products from each order and decide if all needs to me synced prior to inserting them
            foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
                if (in_array($_orderNumber, $this->_cache['failedOrders'])) {
                    Mage::helper('tnw_salesforce')->log('ORDER (' . $_orderNumber . '): Skipping, issues with upserting an order!');
                    continue;
                }
                if (
                    array_key_exists($_orderNumber, $this->_cache['upsertedOrderStatuses'])
                    && $this->_cache['upsertedOrderStatuses'][$_orderNumber] != TNW_Salesforce_Helper_Salesforce_Data_Order::DRAFT_STATUS
                ) {
                    Mage::helper('tnw_salesforce')->log('ORDER (' . $_orderNumber . '): Skipping, order is already Active!');
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
            if (in_array($_orderNumber, $this->_cache['failedOrders'])) {
                Mage::helper('tnw_salesforce')->log('ORDER (' . $_orderNumber . '): Skipping, issues with upserting an order!');
                continue;
            }

            $this->_prepareOrderItem($_orderNumber);

        }
        Mage::helper('tnw_salesforce')->log('----------Prepare Cart Items: End----------');
    }

    /**
     * Prepare Store Id for upsert
     *
     * @param Mage_Sales_Model_Order_Item $_item
     */
    protected function _prepareStoreId(Mage_Sales_Model_Order_Item $_item)
    {
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

    /**
     * Mass sync products that are part of the order
     */
    protected function syncProducts()
    {
        Mage::helper('tnw_salesforce')->log("================ INVENTORY SYNC: START ================");

        $manualSync = Mage::helper('tnw_salesforce/bulk_product');

        $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
        $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());

        foreach ($this->_stockItems as $_storeId => $_products) {
            Mage::helper('tnw_salesforce')->log("Store Id: " . $_storeId);
            $manualSync->setOrderStoreId($_storeId);
            if ($manualSync->reset()) {
                $manualSync->massAdd($this->_stockItems[$_storeId]);
                $manualSync->process();
                if (!$this->isFromCLI()) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Store #' . $_storeId . ' ,Product inventory was synchronized with Salesforce'));
                }
            } else {
                if (!$this->isFromCLI() && !$this->isCron()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Salesforce Connection could not be established!');
                }
            }
        }

        Mage::helper('tnw_salesforce')->log("================ INVENTORY SYNC: END ================");
    }

    /**
     * Prepare order history notes for syncronization
     */
    protected function _prepareNotes()
    {
        Mage::helper('tnw_salesforce')->log('----------Prepare Notes: Start----------');

        // Get all products from each order and decide if all needs to me synced prior to inserting them
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (in_array($_orderNumber, $this->_cache['failedOrders'])) {
                Mage::helper('tnw_salesforce')->log('ORDER (' . $_orderNumber . '): Skipping, issues with upserting an order!');
                continue;
            }
            $_order = (Mage::registry('order_cached_' . $_orderNumber)) ? Mage::registry('order_cached_' . $_orderNumber) : Mage::getModel('sales/order')->loadByIncrementId($_orderNumber);

            // TODO: need to add this feature
            foreach ($_order->getAllStatusHistory() as $_note) {
                // Only sync notes for the order
                if ($_note->getData('entity_name') == 'order' && !$_note->getData('salesforce_id') && $_note->getData('comment')) {
                    $this->_obj = new stdClass();
                    $this->_obj->ParentId = $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_orderNumber];
                    $this->_obj->IsPrivate = 0;
                    $this->_obj->Body = utf8_encode($_note->getData('comment'));
                    $this->_obj->Title = ($_note->getData('comment'));

                    if (strlen($this->_obj->Title) > 75) {
                        $this->_obj->Title = utf8_encode(substr($_note->getData('comment'), 0, 75) . '...');
                    } else {
                        $this->_obj->Title = utf8_encode($_note->getData('comment'));
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

    /**
     * Push OrderItems and Notes into Salesforce
     */
    protected function _pushRemainingOrderData()
    {
        // Push Order Products
        if (!empty($this->_cache['orderItemsToUpsert'])) {
            Mage::helper('tnw_salesforce')->log('----------Push Cart Items: Start----------');

            Mage::dispatchEvent("tnw_salesforce_order_products_send_before", array("data" => $this->_cache['orderItemsToUpsert']));

            // Push Cart
            $_ttl = count($this->_cache['orderItemsToUpsert']);
            if ($_ttl > 199) {
                $_steps = ceil($_ttl / 199);
                for ($_i = 0; $_i < $_steps; $_i++) {
                    $_start = $_i * 200;
                    $_itemsToPush = array_slice($this->_cache['orderItemsToUpsert'], $_start, $_start + 199);
                    $this->_pushOrderItems($_itemsToPush);
                }
            } else {
                $this->_pushOrderItems($this->_cache['orderItemsToUpsert']);
            }

            Mage::dispatchEvent("tnw_salesforce_order_products_send_after", array(
                "data" => $this->_cache['orderItemsToUpsert'],
                "result" => $this->_cache['responses']['orderProducts']
            ));

            Mage::helper('tnw_salesforce')->log('----------Push Cart Items: End----------');
        }

        // Push Notes
        if (!empty($this->_cache['notesToUpsert'])) {
            Mage::helper('tnw_salesforce')->log('----------Push Notes: Start----------');

            Mage::dispatchEvent("tnw_salesforce_order_notes_send_before", array("data" => $this->_cache['notesToUpsert']));

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

            Mage::dispatchEvent("tnw_salesforce_order_notes_send_after", array(
                "data" => $this->_cache['notesToUpsert'],
                "result" => $this->_cache['responses']['notes']
            ));

            Mage::helper('tnw_salesforce')->log('----------Push Notes: End----------');
        }

        // Kick off the event to allow additional data to be pushed into salesforce
        Mage::dispatchEvent("tnw_salesforce_order_sync_after_final", array(
            "all" => $this->_cache['entitiesUpdating'],
            "failed" => $this->_cache['failedOrders']
        ));

        // Activate orders
        if (!empty($this->_cache['orderToActivate'])) {
            foreach ($this->_cache['orderToActivate'] as $_orderNum => $_object) {
                $salesforceOrderId = $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_orderNum];
                if (array_key_exists($_orderNum, $this->_cache  ['upserted' . $this->getManyParentEntityType()])) {
                    $_object->Id = $salesforceOrderId;
                } else {
                    unset($this->_cache['orderToActivate'][$_orderNum]);
                    Mage::helper('tnw_salesforce')->log('SKIPPING ACTIVATION: Order (' . $_orderNum . ') did not make it into Salesforce.');
                }
                // Check if at least 1 product was added to the order before we try to activate
                if (
                    !array_key_exists($salesforceOrderId, $this->_cache['orderItemsProductsToSync'])
                    || empty($this->_cache['orderItemsProductsToSync'][$salesforceOrderId])
                ) {
                    unset($this->_cache['orderToActivate'][$_orderNum]);
                    Mage::helper('tnw_salesforce')->log('SKIPPING ACTIVATION: Order (' . $_orderNum . ') Products did not make it into Salesforce.');
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice("SKIPPING ORDER ACTIVATION: Order (" . $_orderNum . ") could not be activated w/o any products!");
                    }
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
            foreach ($chunk as $_object) {
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
                $_orderId = array_search($_orderSalesforceId, $this->_cache  ['upserted' . $this->getManyParentEntityType()]);

                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_status_history') . "` SET salesforce_id = '" . $_result->id . "' WHERE entity_id = '" . $_noteId . "';";
                Mage::helper('tnw_salesforce')->log('Note (id: ' . $_noteId . ') upserted for order #' . $_orderId . ')');
            }
        }

        if (!empty($sql)) {
            Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
            Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
        }
    }

    /**
     * @param array $chunk
     * push OrderItem chunk into Salesforce
     */
    protected function _pushOrderItems($chunk = array())
    {
        $_orderNumbers = array_flip($this->_cache  ['upserted' . $this->getManyParentEntityType()]);
        $_chunkKeys = array_keys($chunk);
        try {
            $results = $this->_mySforceConnection->upsert("Id", array_values($chunk), 'OrderItem');
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach ($chunk as $_object) {
                $this->_cache['responses']['orderItems'][] = $_response;
            }
            $results = array();
            Mage::helper('tnw_salesforce')->log('CRITICAL: Push of Order Items to SalesForce failed' . $e->getMessage());
        }

        $this->_cache['responses']['orderProducts'] = $results;

        $_sql = "";
        foreach ($results as $_key => $_result) {
            $_orderNum = $_orderNumbers[$this->_cache['orderItemsToUpsert'][$_chunkKeys[$_key]]->OrderId];

            //Report Transaction
            $this->_cache['responses']['orderItems'][] = $_result;

            if (!$_result->success) {
                // Reset sync status
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 0 WHERE salesforce_id = '" . $this->_cache['orderItemsToUpsert'][$_chunkKeys[$_key]]->OrderId . "';";
                Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

                Mage::helper('tnw_salesforce')->log('ERROR: One of the Cart Item for (order: ' . $_orderNum . ') failed to upsert.', 1, "sf-errors");
                $this->_processErrors($_result, 'orderCart', $chunk[$_chunkKeys[$_key]]);
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('Failed to upsert one of the Cart Item for Order #' . $_orderNum);
                }
            } else {
                $_cartItemId = $_chunkKeys[$_key];
                if ($_cartItemId && strrpos($_cartItemId, 'cart_', -strlen($_cartItemId)) !== FALSE) {
                    $_sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_item') . "` SET salesforce_id = '" . $_result->id . "' WHERE item_id = '" . str_replace('cart_', '', $_cartItemId) . "';";
                }
                Mage::helper('tnw_salesforce')->log('Cart Item (id: ' . $_result->id . ') for (order: ' . $_orderNum . ') upserted.');
            }
        }
        if (!empty($_sql)) {
            Mage::helper('tnw_salesforce')->getDbConnection()->query($_sql);
        }
    }

    /**
     * @param array $chunk
     * Actiate orders in Salesforce
     */
    protected function _activateOrders($chunk = array())
    {
        $_orderNumbers = array_keys($this->_cache['orderToActivate']);
        try {
            $results = $this->_mySforceConnection->upsert("Id", array_values($chunk), 'Order');
        } catch (Exception $e) {
            $results = array();
            Mage::helper('tnw_salesforce')->log('CRITICAL: Activation of Orders in SalesForce failed!');
            Mage::helper('tnw_salesforce')->log('ERROR:' . $e->getMessage());
        }

        foreach ($results as $_key => $_result) {
            $_orderNum = $_orderNumbers[$_key];

            if (!$_result->success) {
                // Reset sync status
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 0 WHERE increment_id = '" . $_orderNum . "';";
                Mage::helper('tnw_salesforce')->log('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

                Mage::helper('tnw_salesforce')->log('ERROR: Order: ' . $_orderNum . ') failed to activate.', 1, "sf-errors");
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('Failed to activate Order #' . $_orderNum);
                }
            } else {
                Mage::helper('tnw_salesforce')->log('Order: ' . $_orderNum . ') activated.');
            }
        }
    }

    /**
     * @param $order
     * Update Order Status
     */
    protected function _updateOrderStatus($order)
    {
        $collection = Mage::getModel('tnw_salesforce/order_status')->getCollection();
        $collection->getSelect()
            ->where("main_table.status = ?", $order->getStatus());

        Mage::helper('tnw_salesforce')->log("Mapping status: " . $order->getStatus());

        $this->_obj->Status = TNW_Salesforce_Helper_Salesforce_Data_Order::DRAFT_STATUS;
        foreach ($collection as $_item) {
            $this->_obj->Status = ($_item->getData('sf_order_status')) ? $_item->getData('sf_order_status') : TNW_Salesforce_Helper_Salesforce_Data_Order::DRAFT_STATUS;

            Mage::helper('tnw_salesforce')->log("Order status: " . $this->_obj->Status);
            break;
        }
        unset($collection, $_item);
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
        // Added a parameter to skip customer sync when updating order status
        $this->massAdd($order->getId(), false, true);

        $this->_obj = new stdClass();
        // Magento Order ID
        $orderIdParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
        $this->_obj->$orderIdParam = $order->getRealOrderId();

        //Process mapping
        Mage::getSingleton('tnw_salesforce/sync_mapping_order_order')
            ->setSync($this)
            ->processMapping($order);

        // Update order status
        $this->_updateOrderStatus($order);

        if ($order->getSalesforceId()) {
            $this->_cache['ordersToUpsert'][$order->getRealOrderId()] = $this->_obj;

            $this->_pushOrdersToSalesforce();
        } else {
            // Need to do full sync instead
            $res = $this->process('full');
            if ($res) {
                Mage::helper('tnw_salesforce')->log("SUCCESS: Updating Order #" . $order->getRealOrderId());
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
                        if ($_childItem->getParentItemId() == $_item->getItemId()) {
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

    /**
     * @depricated Exists compatibility for
     * @param $_customersToSync
     * @return mixed
     * Update accountLookup data
     */
    protected function _updateAccountLookupData($_customersToSync)
    {
        if (is_array($this->_cache['leadLookup'])) {
            foreach ($this->_cache['leadLookup'] as $website => $websiteLeads) {
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
}