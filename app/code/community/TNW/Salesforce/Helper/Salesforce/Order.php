<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
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
     * @comment salesforce field name to assign parent entity
     * @var string
     */
    protected $_salesforceParentOpportunityField = 'OpportunityId';

    /**
     * create order object
     *
     * @param $order
     */
    protected function _setEntityInfo($order)
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
            $this->_obj->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'BillingCustomer__c'}
                = $_customer->getData('salesforce_id');
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
        $this->_updateEntityStatus($order);

        /**
         * Set 'Draft' status temporarry, it's necessary for order change with status from "Activated" group
         */
        $_currentStatus = $this->_obj->Status;
        if ($_currentStatus != TNW_Salesforce_Helper_Salesforce_Data_Order::DRAFT_STATUS) {
            $this->_obj->Status = TNW_Salesforce_Helper_Salesforce_Data_Order::DRAFT_STATUS;
            $_toActivate = new stdClass();
            $_toActivate->Status = $_currentStatus;
            $_toActivate->Id = NULL;

            if (Mage::helper('tnw_salesforce')->getType() == 'PRO') {
                $disableSyncField = Mage::helper('tnw_salesforce/config')->getDisableSyncField();
                $_toActivate->$disableSyncField = true;
            }

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
            sprintf('upserted%s', $this->getManyParentEntityType()) => array(),
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
            sprintf('failed%s', $this->getManyParentEntityType()) => array(),
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
    protected function _pushEntity()
    {
        if (!empty($this->_cache['ordersToUpsert'])) {
            $_pushOn = $this->_magentoId;
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Order Push: Start----------');
            foreach (array_values($this->_cache['ordersToUpsert']) as $_opp) {
                if (array_key_exists('Id', $_opp)) {
                    $_pushOn = 'Id';
                }
                foreach ($_opp as $_key => $_value) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Order Object: " . $_key . " = '" . $_value . "'");
                }
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("--------------------------");
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
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of an order to Salesforce failed' . $e->getMessage());
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

                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Order Failed: (order: ' . $_orderNum . ')');
                    $this->_processErrors($_result, 'order', $this->_cache['ordersToUpsert'][$_orderNum]);
                    $this->_cache['failedOrders'][] = $_orderNum;
                } else {
                    $_contactId = ($this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_id')) ? "'" . $this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_id') . "'" : 'NULL';
                    $_accountId = ($this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_account_id')) ? "'" . $this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_account_id') . "'" : 'NULL';
                    $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET contact_salesforce_id = " . $_contactId . ", account_salesforce_id = " . $_accountId . ", sf_insync = 1, salesforce_id = '" . $_result->id . "' WHERE entity_id = " . $_entityArray[$_orderNum] . ";";
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
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
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Order Upserted: ' . $_result->id);
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

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Order Push: End----------');
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('No Orders found queued for the synchronization!');
        }
    }

    protected function _checkPrepareEntityItem($_key)
    {
        $_orderNumber   = $this->_cache['entitiesUpdating'][$_key];
        $_orderStatuses = $this->_cache['upsertedOrderStatuses'];

        if (array_key_exists($_orderNumber, $_orderStatuses) &&
            $_orderStatuses[$_orderNumber] != TNW_Salesforce_Helper_Salesforce_Data_Order::DRAFT_STATUS
        ){
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('ORDER (' . $_orderNumber . '): Skipping, order is already Active!');
            return false;
        }

        return true;
    }

    protected function _pushRemainingCustomEntityData()
    {
        parent::_pushRemainingCustomEntityData();

        // Activate orders
        if (!empty($this->_cache['orderToActivate'])) {
            foreach ($this->_cache['orderToActivate'] as $_orderNum => $_object) {
                $salesforceOrderId = $this->_cache  ['upserted' . $this->getManyParentEntityType()][$_orderNum];
                if (array_key_exists($_orderNum, $this->_cache  ['upserted' . $this->getManyParentEntityType()])) {
                    $_object->Id = $salesforceOrderId;
                } else {
                    unset($this->_cache['orderToActivate'][$_orderNum]);
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING ACTIVATION: Order (' . $_orderNum . ') did not make it into Salesforce.');
                }
                // Check if at least 1 product was added to the order before we try to activate
                if (
                    array_key_exists('orderItemsProductsToSync', $this->_cache)
                    && (
                        !array_key_exists($salesforceOrderId, $this->_cache['orderItemsProductsToSync'])
                        || empty($this->_cache['orderItemsProductsToSync'][$salesforceOrderId])
                    )
                ) {
                    unset($this->_cache['orderToActivate'][$_orderNum]);
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING ACTIVATION: Order (' . $_orderNum . ') Products did not make it into Salesforce.');
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice("SKIPPING ORDER ACTIVATION: Order (" . $_orderNum . ") could not be activated w/o any products!");
                    }
                }
            }

            if (!empty($this->_cache['orderToActivate'])) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Activating Orders: Start----------');

                $_orderChunk = array_chunk($this->_cache['orderToActivate'], TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);
                foreach ($_orderChunk as $_itemsToPush) {
                    $this->_activateOrders($_itemsToPush);
                }

                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Activating Orders: End----------');
            }
        }
    }

    /**
     * @param array $chunk
     * push OrderItem chunk into Salesforce
     */
    protected function _pushEntityItems($chunk = array())
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
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of Order Items to SalesForce failed' . $e->getMessage());
        }

        $this->_cache['responses']['orderItems'] = $results;

        $_sql = "";
        foreach ($results as $_key => $_result) {
            $_orderNum = $_orderNumbers[$this->_cache['orderItemsToUpsert'][$_chunkKeys[$_key]]->OrderId];

            //Report Transaction
            $this->_cache['responses']['orderItems'][] = $_result;

            if (!$_result->success) {
                // Reset sync status
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 0 WHERE salesforce_id = '" . $this->_cache['orderItemsToUpsert'][$_chunkKeys[$_key]]->OrderId . "';";
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: One of the Cart Item for (order: ' . $_orderNum . ') failed to upsert.');
                $this->_processErrors($_result, 'orderCart', $chunk[$_chunkKeys[$_key]]);
            } else {
                $_cartItemId = $_chunkKeys[$_key];
                if ($_cartItemId && strrpos($_cartItemId, 'cart_', -strlen($_cartItemId)) !== FALSE) {
                    $_sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_item') . "` SET salesforce_id = '" . $_result->id . "' WHERE item_id = '" . str_replace('cart_', '', $_cartItemId) . "';";
                }
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Cart Item (id: ' . $_result->id . ') for (order: ' . $_orderNum . ') upserted.');
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
        try {
            $results = $this->_mySforceConnection->upsert("Id", array_values($chunk), 'Order');
        } catch (Exception $e) {
            $results = array();
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Activation of Orders in SalesForce failed!' . $e->getMessage());
        }

        $_orderNumbers = array_keys($chunk);
        foreach ($results as $_key => $_result) {
            $_orderNum = $_orderNumbers[$_key];

            if (!$_result->success) {
                // Reset sync status
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 0 WHERE increment_id = '" . $_orderNum . "';";
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Order: ' . $_orderNum . ') failed to activate.');
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Order: ' . $_orderNum . ') activated.');
            }
        }
    }

    /**
     * @param $order
     * Update Order Status
     */
    protected function _updateEntityStatus($order)
    {
        // Magento Order ID
        $orderIdParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
        $this->_obj->$orderIdParam = $order->getRealOrderId();

        /** @var TNW_Salesforce_Model_Mysql4_Order_Status_Collection $collection */
        $collection = Mage::getModel('tnw_salesforce/order_status')->getCollection()
            ->addStatusToFilter($order->getStatus());
        $orderStatus = $collection->getFirstItem()->getData('sf_order_status');

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Mapping status: " . $order->getStatus());

        $this->_obj->Status = ($orderStatus)
            ? $orderStatus : TNW_Salesforce_Helper_Salesforce_Data_Order::DRAFT_STATUS;

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Order status: " . $this->_obj->Status);
        unset($collection);
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

    protected function _prepareEntityAfter()
    {
        $_toUpsert = &$this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))];
        if (empty($_toUpsert)) {
            return;
        }

        // Prepare opportunity array
        $_opportunityIds = array();
        foreach ($_toUpsert as $entityNumber => $entity) {
            if (!property_exists($entity, $this->_salesforceParentOpportunityField)) {
                continue;
            }

            if (empty($entity->{$this->_salesforceParentOpportunityField})) {
                continue;
            }

            $_opportunityIds[$entityNumber] = $entity->{$this->_salesforceParentOpportunityField};
        }

        if (empty($_opportunityIds)) {
            return;
        }

        foreach(array_chunk($_opportunityIds, TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true) as $_chunk) {
            try {
                $results = $this->_mySforceConnection->retrieve('Id', 'Opportunity', array_values($_chunk));
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Check exist Opportunity to Salesforce failed' . $e->getMessage());
                continue;
            }

            $results = is_array($results)
                ? $results
                : array($results);

            $_opportunityExistIds = array();
            foreach ($results as $_result) {
                if (!$_result instanceof stdClass) {
                    continue;
                }

                $_opportunityExistIds[] = $_result->Id;
                $_opportunityExistIds[] = Mage::helper('tnw_salesforce')->prepareId($_result->Id);
            }

            // Undelete
            $_opportunityIdUndelete = array_diff($_chunk, $_opportunityExistIds);
            if (empty($_opportunityIdUndelete)) {
                continue;
            }

            try {
                $results = $this->_mySforceConnection->undelete(array_values($_opportunityIdUndelete));
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Check exist Opportunity to Salesforce failed' . $e->getMessage());
                continue;
            }

            $_keys = array_keys($_opportunityIdUndelete);
            foreach ($results as $_key => $result) {
                if ($result->success) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('INFO: Restoring objects opportunity: ' . $result->id);
                    continue;
                }

                $_entityNumber = $_keys[$_key];
                /** @var Mage_Sales_Model_Order $_entity */
                $_entity = $this->_loadEntityByCache(array_search($_entityNumber, $this->_cache['entitiesUpdating']), $_entityNumber);
                $_entity->setData('opportunity_id', '');
                $_entity->getResource()->save($_entity);

                unset($_toUpsert[$_entityNumber]->{$this->_salesforceParentOpportunityField});
            }
        }
    }
}