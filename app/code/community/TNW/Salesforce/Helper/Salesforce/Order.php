<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Order
 *
 * @method Mage_Sales_Model_Order _loadEntityByCache($_key, $cachePrefix = null)
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
     * @var string
     */
    protected $_mappingEntityName = 'Order';

    /**
     * @var string
     */
    protected $_mappingEntityItemName = 'OrderItem';

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
     * @param $_entity Mage_Sales_Model_Order
     */
    protected function _prepareEntityObjCustom($_entity)
    {
        $_entityNumber = $this->_getEntityNumber($_entity);
        if (Mage::helper('tnw_salesforce')->getType() == 'PRO') {
            $disableSyncField = Mage::helper('tnw_salesforce/config')->getDisableSyncField();
            $this->_obj->$disableSyncField = true;
        }

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $_entity->getData('order_currency_code');
        }

        $_oppExists = property_exists($this->_obj, 'OpportunityId');
        if ((!$_oppExists || ($_oppExists && empty($this->_obj->OpportunityId)))
            && !empty($this->_cache['abandonedCart'])
            && array_key_exists($_entity->getQuoteId(), $this->_cache['abandonedCart'])
        ) {
            $this->_obj->OpportunityId = $this->_cache['abandonedCart'][$_entity->getQuoteId()];
        }

        if (property_exists($this->_obj, 'OpportunityId') && empty($this->_obj->OpportunityId)) {
            unset($this->_obj->OpportunityId);
        }

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

            $this->_cache['orderToActivate'][$_entityNumber] = $_toActivate;
        }
    }

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @param $types string
     * @return mixed
     */
    protected function _getObjectByEntityType($_entity, $types)
    {
        switch($types)
        {
            case 'Custom':
                return $_entity->getStore();

            case 'Order':
                return $_entity;

            case 'Payment':
                return $_entity->getPayment();

            case 'Customer':
                $_entityNumber = $this->_getEntityNumber($_entity);
                return $this->_cache[sprintf('%sCustomers', $this->_magentoEntityName)][$_entityNumber];

            case 'Customer Group':
                $_customer = $this->_getObjectByEntityType($_entity, 'Customer');
                $_groupId  = ($_entity->getCustomerGroupId() !== NULL)
                    ? $_entity->getCustomerGroupId() : $_customer->getGroupId();

                return Mage::getModel('customer/group')->load($_groupId);

            case 'Billing':
                return $_entity->getBillingAddress();

            case 'Shipping':
                return $_entity->getShippingAddress();

            case 'Aitoc':
                $_customer     = $this->_getObjectByEntityType($_entity, 'Customer');
                $aitocValues   = array('order' => NULL, 'customer' => NULL);

                $modules       = Mage::getConfig()->getNode('modules')->children();
                if (property_exists($modules, 'Aitoc_Aitcheckoutfields')) {
                    $aitocValues['customer'] = Mage::getModel('aitcheckoutfields/transport')->loadByCustomerId($_customer->getId());
                    $aitocValues['order'] = Mage::getModel('aitcheckoutfields/transport')->loadByOrderId($_entity->getId());
                }

                return $aitocValues;

            default:
                return null;
        }
    }

    /**
     * @return bool|void
     * Prepare values for the synchroization
     */
    public function reset()
    {
        parent::reset();

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
     * Create custom Order name in Salesforce
     */
    protected function _setOrderName($orderNumber)
    {
        $this->_obj->Name = "Magento Order #" . $orderNumber;
    }

    /**
     * Push Order(s) to Salesforce
     */
    protected function _pushEntity()
    {
        if (empty($this->_cache['ordersToUpsert'])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('No Orders found queued for the synchronization!');
            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Order Push: Start----------');
        foreach (array_values($this->_cache['ordersToUpsert']) as $_opp) {
            foreach ($_opp as $_key => $_value) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Order Object: " . $_key . " = '" . $_value . "'");
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("--------------------------");
        }

        $_keys = array_keys($this->_cache['ordersToUpsert']);
        try {
            Mage::dispatchEvent("tnw_salesforce_order_send_before", array(
                "data" => $this->_cache['ordersToUpsert']
            ));

            $results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['ordersToUpsert']), 'Order');
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

        $_undeleteIds = array();
        foreach ($results as $_key => $_result) {
            $_orderNum = $_keys[$_key];

            //Report Transaction
            $this->_cache['responses']['orders'][$_orderNum] = $_result;
            if (!$_result->success) {
                if ($_result->errors[0]->statusCode == "ENTITY_IS_DELETED") {
                    $_undeleteIds[] = $_orderNum;
                }

                $this->_processErrors($_result, 'order', $this->_cache['ordersToUpsert'][$_orderNum]);
                $this->_cache['failedOrders'][] = $_orderNum;

                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Order Failed: (order: ' . $_orderNum . ')');
            } else {
                $_order = $this->_loadEntityByCache(array_search($_orderNum, $this->_cache['entitiesUpdating']), $_orderNum);
                $_order->addData(array(
                    'contact_salesforce_id' => $this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_id'),
                    'account_salesforce_id' => $this->_cache['orderCustomers'][$_orderNum]->getData('salesforce_account_id'),
                    'sf_insync'             => 1,
                    'salesforce_id'         => $_result->id
                ));

                $_order->getResource()->save($_order);

                $this->_cache[sprintf('upserted%s', $this->getManyParentEntityType())][$_orderNum] = $_result->id;
                $this->_cache['upsertedOrderStatuses'][$_orderNum] = (is_array($this->_cache['orderLookup']) && array_key_exists($_orderNum, $this->_cache['orderLookup']))
                    ? $this->_cache['orderLookup'][$_orderNum]->Status : $this->_cache['ordersToUpsert'][$_orderNum]->Status;

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
        $_orderNumbers = array_flip($this->_cache['upserted'.$this->getManyParentEntityType()]);
        $_chunkKeys = array_keys($chunk);
        try {
            $results = $this->_mySforceConnection->upsert("Id", array_values($chunk), 'OrderItem');
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach ($chunk as $_object) {
                $_sOrderId   = $_object->OrderId;
                $_orderNum   = $_orderNumbers[$_sOrderId];

                $this->_cache['responses']['orderItems'][$_orderNum]['subObj'][] = $_response;
            }

            $results = array();
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of Order Items to SalesForce failed' . $e->getMessage());
        }

        $_sql = "";
        foreach ($results as $_key => $_result) {
            $_cartItemId = $_chunkKeys[$_key];
            $_sOrderId   = $this->_cache['orderItemsToUpsert'][$_cartItemId]->OrderId;
            $_orderNum   = $_orderNumbers[$_sOrderId];

            //Report Transaction
            $this->_cache['responses']['orderItems'][$_orderNum]['subObj'][] = $_result;
            if (!$_result->success) {
                // Reset sync status
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET sf_insync = 0 WHERE salesforce_id = '" . $_sOrderId . "';";
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: One of the Cart Item for (order: ' . $_orderNum . ') failed to upsert.');
                $this->_processErrors($_result, 'orderCart', $chunk[$_cartItemId]);
            } else {
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