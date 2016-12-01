<?php

/**
 * @method Mage_Sales_Model_Order_Creditmemo _loadEntityByCache($_entityId, $_entityNumber)
 */
class TNW_Salesforce_Helper_Salesforce_Creditmemo extends TNW_Salesforce_Helper_Salesforce_Abstract_Sales
{
    /**
     * @comment magento entity alias "convert from"
     * @var string
     */
    protected $_magentoEntityName = 'creditmemo';

    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = 'order';

    /**
     * @var string
     */
    protected $_mappingEntityName = 'OrderCreditMemo';

    /**
     * @var string
     */
    protected $_mappingEntityItemName = 'OrderCreditMemoItem';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityModel = 'sales/order_creditmemo';

    /**
     * @var array
     */
    protected $_availableFees = array(
        'tax',
        'shipping',
        'discount'
    );
    /**
     * @var int
     */
    protected $_guestCount = 0;

    /**
     * @var array
     */
    protected $_emails = array();

    /**
     * @param $_entity Mage_Sales_Model_Order_Creditmemo
     * @return mixed
     */
    protected function _getEntityNumber($_entity)
    {
        return 'cm_'.$_entity->getIncrementId();
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Creditmemo
     * @return bool
     */
    protected function _checkMassAddEntity($_entity)
    {
        // Parent in Salesforce
        $_order = $_entity->getOrder();
        if (!$_order->getSalesforceId() || !$_order->getData('sf_insync')) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')
                    ->addError('WARNING: Sync for creditmemo #' . $_entity->getIncrementId() . ', order #' . $_order->getRealOrderId() . ' needs to be synchronized first!');
            }

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice('SKIPPING: Sync for creditmemo #' . $_entity->getIncrementId() . ', order #' . $_order->getRealOrderId() . ' needs to be synchronized first!');
            return false;
        }

        $_recordNumber =  $this->_getEntityNumber($_entity);

        // Get Magento customer object
        $customer = $this->_generateCustomerByOrder($_order);

        // Associate order Number with a customer ID
        $_customerId = ($customer->getId())
            ? $customer->getId() : sprintf('guest_%d', $this->_guestCount++);

        $customer->setId($_customerId);

        // Associate order Number with a customer Email
        $email = strtolower($customer->getEmail());
        if (empty($email) ) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                $message = sprintf('SKIPPED: Sync for %s #%s failed, %s is missing an email address!',
                    $this->_magentoEntityName, $_recordNumber, $this->_magentoEntityName);

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveNotice($message);
                Mage::getSingleton('adminhtml/session')
                    ->addNotice($message);
            }

            return false;
        }

        $this->_cache[sprintf('%sToCustomerId', $this->_magentoEntityName)][$_recordNumber] = $_customerId;
        $this->_cache[sprintf('%sToEmail', $this->_magentoEntityName)][$_recordNumber] = $email;
        $this->_cache[sprintf('%sCustomers', $this->_magentoEntityName)][$_recordNumber] = $customer;

        // Check if customer from this group is allowed to be synchronized
        $_customerGroup = $_order->getData('customer_group_id');
        if ($_customerGroup === NULL) {
            $_customerGroup = $customer->getGroupId();
        }

        if ($_customerGroup === NULL && !$this->isFromCLI()) {
            $_customerGroup = Mage::getSingleton('customer/session')->getCustomerGroupId();
        }

        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');
        if (!$helper->getSyncAllGroups() && !$helper->syncCustomer($_customerGroup)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError("SKIPPING: Sync for customer group #" . $_customerGroup . " is disabled!");

            return false;
        }

        $_websiteId = ($customer->getData('website_id'))
            ? $customer->getData('website_id')
            : Mage::app()->getStore($_entity->getData('store_id'))->getWebsiteId();

        $this->_emails[$_customerId]   = strtolower($customer->getEmail());
        $this->_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];

        return true;
    }

    /**
     *
     */
    protected function _massAddAfterLookup()
    {
        // Salesforce lookup, find all orders by Magento order number
        $this->_cache[sprintf('%sLookup', $this->_salesforceEntityName)] = Mage::helper('tnw_salesforce/salesforce_data_creditmemo')
            ->lookup($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);

        $ordersUpdating = array();
        foreach (array_chunk(array_keys($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), 1) as $ids) {

            $ordersCollection = Mage::getModel('sales/order_creditmemo')->getCollection();

            $ordersCollection->getSelect()->reset(Varien_Db_Select::COLUMNS);

            $ordersCollection->addFieldToFilter('main_table.entity_id', array('in' => $ids));

            $ordersCollection
                ->join(
                    array('order_table' => 'sales/order'),
                    'main_table.order_id = order_table.entity_id',
                    array('order_table.entity_id', 'order_table.increment_id')
                );
            $lookupResult = Mage::helper('tnw_salesforce')->getDbConnection('read')->fetchPairs($ordersCollection->getSelect());
            foreach ($lookupResult as $k => $v) {
                $ordersUpdating[$k] = $v;
            }
        }
        $this->_cache['creditmemoOrderLookup'] = Mage::helper('tnw_salesforce/salesforce_data_order')
            ->lookup($ordersUpdating);
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Creditmemo
     * @param $key
     */
    protected function _prepareEntityObjCustom($_entity, $key)
    {
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $this->getCurrencyCode($_entity);
        }

        // Link to Order
        if (!property_exists($this->_obj, 'Id')) {
            $this->_obj->OriginalOrderId    = $_entity->getOrder()->getData('salesforce_id');
            $this->_obj->IsReductionOrder   = true;
        }

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_ENTERPRISE . 'disableMagentoSync__c'}
            = true;

        /**
         * Set 'Draft' status temporarry, it's necessary for order change with status from "Activated" group
         */
        $_currentStatus = $this->_obj->Status;
        $_draftStatus = Mage::helper('tnw_salesforce/config_sales')->getOrderDraftStatus();
        if ($_currentStatus != $_draftStatus) {
            $this->_obj->Status = $_draftStatus;
            $_toActivate = new stdClass();
            $_toActivate->Status = $_currentStatus;
            $_toActivate->Id = NULL;

            if (Mage::helper('tnw_salesforce')->getType() == 'PRO') {
                $_toActivate->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_ENTERPRISE . 'disableMagentoSync__c'}
                    = true;
            }

            $_entityNumber = $this->_getEntityNumber($_entity);
            $this->_cache['orderToActivate'][$_entityNumber] = $_toActivate;
        }
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Creditmemo
     * @param $type string
     * @return mixed
     */
    protected function _getObjectByEntityType($_entity, $type)
    {
        switch($type)
        {
            case 'Credit Memo':
                $_object = $_entity;
                break;

            case 'Order':
                $_object = $_entity->getOrder();
                break;

            case 'Payment':
                $_order = $this->_getObjectByEntityType($_entity, 'Order');
                $_object = $_order->getPayment();
                break;

            case 'Customer':
                $_entityNumber = $this->_getEntityNumber($_entity);
                $_object = $this->_cache[sprintf('%sCustomers', $this->_magentoEntityName)][$_entityNumber];
                break;

            case 'Customer Group':
                $_customer = $this->_getObjectByEntityType($_entity, 'Customer');
                $_order    = $this->_getObjectByEntityType($_entity, 'Order');
                $_groupId  = ($_order->getCustomerGroupId() !== NULL)
                    ? $_order->getCustomerGroupId() : $_customer->getGroupId();

                $_object = Mage::getModel('customer/group')->load($_groupId);
                break;

            case 'Billing':
                $_object = $_entity->getBillingAddress();
                break;

            case 'Shipping':
                $_object = $_entity->getShippingAddress();
                break;

            case 'Custom':
                $_object = $_entity->getStore();
                break;

            default:
                $_object = null;
                break;
        }

        return $_object;
    }

    /**
     * @param $_entityItem
     * @return bool|void
     */
    protected function _getEntityItemSalesforceId($_entityItem)
    {
        $_entity = $this->getEntityByItem($_entityItem);
        return $this->_doesCartItemExist($_entity, $_entityItem);
    }

    /**
     * @param $_entityItem Mage_Sales_Model_Order_Creditmemo_Item
     * @param $_type
     * @return mixed
     */
    protected function _getObjectByEntityItemType($_entityItem, $_type)
    {
        switch($_type)
        {
            case 'Credit Memo':
                $_object = $this->getEntityByItem($_entityItem);
                break;

            case 'Credit Memo Item':
                $_object = $_entityItem;
                break;

            case 'Product':
                $_object = $this->getProductByEntityItem($_entityItem);
                break;

            case 'Product Inventory':
                $product = $this->_getObjectByEntityItemType($_entityItem, 'Product');
                $_object = Mage::getModel('cataloginventory/stock_item')
                    ->loadByProduct($product);
                break;

            case 'Custom':
                $_object = $this->_getObjectByEntityItemType($_entityItem, 'Credit Memo')
                    ->getStore();
                break;

            default:
                $_object = null;
                break;
        }

        return $_object;
    }

    /**
     * @param $entityItem Mage_Sales_Model_Order_Creditmemo_Item
     * @return Mage_Catalog_Model_Product
     */
    protected function getProductByEntityItem($entityItem)
    {
        $productSku = $this->searchSkuByEntityItem($entityItem);
        if (!isset($this->_cache['products'][$productSku])) {
            /** @var Mage_Core_Model_Store $store */
            $store      = $this->_getObjectByEntityItemType($entityItem, 'Custom');
            /** @var Mage_Catalog_Model_Product $_product */
            $_product   = Mage::getModel('catalog/product')
                ->setStoreId($store->getId());

            if (!$this->isFeeEntityItem($entityItem)) {
                $_product->load(Mage::getResourceModel('catalog/product')->getIdBySku($productSku));
            }

            $isCreateProduct = Mage::helper('tnw_salesforce/config_product')->isCreateDeleteProduct();
            if ($this->isFeeEntityItem($entityItem) || ($isCreateProduct && is_null($_product->getId()))) {
                // Generate Fake product
                $_product->addData(array(
                    'sku'       => $productSku,
                    'name'      => $entityItem->getName(),
                    'price'     => $this->isFeeEntityItem($entityItem)
                        ? $entityItem->getBaseOriginalPrice()
                        : $entityItem->getOrderItem()->getBaseOriginalPrice(),

                    'type_id'   => $this->isFeeEntityItem($entityItem)
                        ? $entityItem->getProductType()
                        : $entityItem->getOrderItem()->getProductType(),

                    'enabled'   => 1,
                    'store_ids' => $store->getWebsite()->getStoreIds(),
                    TNW_Salesforce_Helper_Salesforce_Product::ENTITY_FEE_CHECK => $this->isFeeEntityItem($entityItem)
                ));
            }

            $this->_cache['products'][$productSku] = $_product->getSku() ? $_product : null;
        }

        return $this->_cache['products'][$productSku];
    }

    /**
     * @param $entityItem Mage_Sales_Model_Order_Creditmemo_Item
     * @return null|string
     */
    protected function searchSkuByEntityItemInLookup($entityItem)
    {
        // Search SKU by Lookup
        $entity       = $this->getEntityByItem($entityItem);
        $entityNumber = $this->_getEntityNumber($entity);
        $lookupKey    = sprintf('%sLookup', $this->_salesforceEntityName);
        $records      = isset($this->_cache[$lookupKey][$entityNumber]->Items)
            ? $this->_cache[$lookupKey][$entityNumber]->Items->records : array();

        foreach ($records as $_cartItem) {
            if ($_cartItem->OriginalOrderItemId != $entityItem->getOrderItem()->getData('salesforce_id')) {
                continue;
            }

            return $_cartItem->PricebookEntry->ProductCode;
        }

        return null;
    }

    /**
     * @param $_entityItem Mage_Sales_Model_Order_Creditmemo_Item
     */
    protected function _prepareEntityItemObjCustom($_entityItem)
    {
        $_entity       = $this->getEntityByItem($_entityItem);
        $_entityNumber = $this->_getEntityNumber($_entity);

        $this->_obj->OrderId
            = $this->_getParentEntityId($_entityNumber);

        if (!property_exists($this->_obj, 'Id')) {
            $this->_obj->OriginalOrderItemId
                = $_entityItem->getOrderItem()->getData('salesforce_id');
        }

        $entityId = $_entityItem->getId();

        $key = empty($entityId)
            ? sprintf('%s_%s', $_entityNumber, count($this->_cache[sprintf('%sToUpsert', lcfirst($this->getItemsField()))]))
            : $entityId;

        $this->_cache[sprintf('%sToUpsert', lcfirst($this->getItemsField()))]['cart_' . $key] = $this->_obj;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Creditmemo
     * @param $_entityItem Mage_Sales_Model_Order_Creditmemo_Item
     * @return bool
     */
    protected function _doesCartItemExist($_entity, $_entityItem)
    {
        $_sOrderItemId = $_entityItem->getOrderItem()->getData('salesforce_id');
        $_entityNumber = $this->_getEntityNumber($_entity);
        $lookupKey     = sprintf('%sLookup', $this->_salesforceEntityName);

        if (! (isset($this->_cache[$lookupKey][$_entityNumber]) && $this->_cache[$lookupKey][$_entityNumber]->Items)){
            return false;
        }

        foreach ($this->_cache[$lookupKey][$_entityNumber]->Items->records as $_cartItem) {

            if ($_cartItem->OriginalOrderItemId != $_sOrderItemId) {
                continue;
            }

            return $_cartItem->Id;
        }

        return false;
    }

    /**
     * @return mixed
     */
    protected function _pushEntity()
    {
        $entityToUpsertKey = sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()));
        if (empty($this->_cache[$entityToUpsertKey])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('No Credit Memo found queued for the synchronization!');
            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Credit Memo Push: Start----------');
        foreach ($this->_cache[$entityToUpsertKey] as $_opp) {
            foreach ($_opp as $_key => $_value) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('%s Object: %s = "%s"', $this->_salesforceEntityName, $_key, $_value));
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("--------------------------");
        }

        $_keys = array_keys($this->_cache[$entityToUpsertKey]);

        try {
            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_send_before', $this->_magentoEntityName),
                array("data" => $this->_cache[$entityToUpsertKey]));

            $results = $this->getClient()->upsert(
                'Id', array_values($this->_cache[$entityToUpsertKey]), 'Order');

            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_send_after', $this->_magentoEntityName), array(
                "data" => $this->_cache[$entityToUpsertKey],
                "result" => $results
            ));
        }
        catch (Exception $e) {
            $results   = array_fill(0, count($_keys),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of an order to Salesforce failed' . $e->getMessage());
        }

        $_undeleteIds = array();
        foreach ($results as $_key => $_result) {
            $_entityNum = $_keys[$_key];

            //Report Transaction
            $this->_cache['responses'][strtolower($this->getManyParentEntityType())][$_entityNum] = $_result;

            if (!$_result->success) {
                if ($_result->errors[0]->statusCode == "ENTITY_IS_DELETED") {
                    $_undeleteIds[] = $_entityNum;
                }

                $this->_processErrors($_result, $this->_salesforceEntityName, $this->_cache[$entityToUpsertKey][$_entityNum]);
                $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())][] = $_entityNum;

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError(sprintf('%s Failed: (%s: ' . $_entityNum . ')', $this->_salesforceEntityName, $this->_magentoEntityName));
            }
            else {
                $_entity = $this->_loadEntityByCache(array_search($_entityNum, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_entityNum);
                $_entity->addData(array(
                    'sf_insync'     => 1,
                    'salesforce_id' => (string)$_result->id
                ));
                $_entity->getResource()->save($_entity);

                $this->_cache[sprintf('upserted%s', $this->getManyParentEntityType())][$_entityNum] = $_result->id;
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('%s Upserted: %s' , $this->_salesforceEntityName, $_result->id));
            }
        }

        if (!empty($_undeleteIds)) {
            $_deleted = Mage::helper('tnw_salesforce/salesforce_data_creditmemo')
                ->lookup($_undeleteIds);

            $_toUndelete = array();
            foreach ($_deleted as $_object) {
                $_toUndelete[] = $_object->Id;
            }

            if (!empty($_toUndelete)) {
                $this->getClient()->undelete($_toUndelete);
            }
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Credit Memo Push: End----------');
    }

    /**
     * @param array $chunk
     * @return mixed
     */
    protected function _pushEntityItems($chunk = array())
    {
        $_orderNumbers = array_flip($this->_cache['upserted'.$this->getManyParentEntityType()]);
        $_chunkKeys    = array_keys($chunk);

        try {
            $results = $this->getClient()->upsert(
                'Id', array_values($chunk), 'OrderItem');
        } catch (Exception $e) {
            $results = array_fill(0, count($chunk),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of Order Credit Memo Items to SalesForce failed' . $e->getMessage());
        }

        foreach ($results as $_key => $_result) {
            $_cartItemId = $_chunkKeys[$_key];
            $_orderId    = $this->_cache[sprintf('%sToUpsert', lcfirst($this->getItemsField()))][$_cartItemId]->OrderId;
            $_entityNum  = $_orderNumbers[$_orderId];
            $_entity     = $this->_loadEntityByCache(array_search($_entityNum, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_entityNum);

            //Report Transaction
            $this->_cache['responses'][lcfirst($this->getItemsField())][$_entityNum]['subObj'][$_cartItemId] = $_result;
            if (!$_result->success) {
                // Reset sync status
                $_entity->setData('sf_insync', 0);
                $_entity->getResource()->save($_entity);

                $this->_processErrors($_result, 'creditmemoCart', $chunk[$_cartItemId]);
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError(sprintf('ERROR: One of the Cart Item for (%s: %s) failed to upsert.', $this->_magentoEntityName, $_entityNum));
            }
            else {
                $_item = $_entity->getItemsCollection()->getItemById(str_replace('cart_', '', $_cartItemId));
                if ($_item instanceof Mage_Core_Model_Abstract) {
                    $_item->setData('salesforce_id', $_result->id);
                    $_item->getResource()->save($_item);
                }

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('Cart Item (id: %s) for (%s: %s) upserted.', $_result->id, $this->_magentoEntityName, $_entityNum));
            }
        }
    }

    protected function _pushRemainingCustomEntityData()
    {
        parent::_pushRemainingCustomEntityData();

        // Activate orders
        if (!empty($this->_cache['orderToActivate'])) {
            foreach ($this->_cache['orderToActivate'] as $_orderNum => $_object) {
                if (!isset($this->_cache['upserted'.$this->getManyParentEntityType()][$_orderNum])) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveTrace('SKIPPING ACTIVATION: Credit Memo (' . $_orderNum . ') did not make it into Salesforce.');

                    unset($this->_cache['orderToActivate'][$_orderNum]);
                    continue;
                }

                $_object->Id = $this->_cache['upserted'.$this->getManyParentEntityType()][$_orderNum];
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
     * Actiate orders in Salesforce
     */
    protected function _activateOrders($chunk = array())
    {
        try {
            $results = $this->getClient()->upsert("Id", array_values($chunk), 'Order');
        } catch (Exception $e) {
            $results = array();
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Activation of Orders in SalesForce failed!' . $e->getMessage());
        }

        $_orderNumbers = array_keys($chunk);
        foreach ($results as $_key => $_result) {
            $_entityNum = $_orderNumbers[$_key];

            if (!$_result->success) {
                // Reset sync status
                $_entity = $this->_loadEntityByCache(array_search($_entityNum, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_entityNum);
                $_entity->setData('sf_insync', 0);
                $_entity->getResource()->save($_entity);

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR: Order: ' . $_entityNum . ') failed to activate.');
            }
            else {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Order: ' . $_entityNum . ') activated.');
            }
        }
    }

    /**
     * @return bool
     */
    protected function isNotesEnabled()
    {
        return Mage::helper('tnw_salesforce/config_sales_creditmemo')->syncCreditMemoNotes();
    }

    /**
     * @param $notes Mage_Sales_Model_Order_Creditmemo_Comment
     * @throws Exception
     */
    protected function _getNotesParentSalesforceId($notes)
    {
        return $notes->getCreditmemo()->getSalesforceId();
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Creditmemo
     * @throws Exception
     * @return array
     */
    protected function _getEntityNotesCollection($_entity)
    {
        return $_entity->getCommentsCollection();
    }

    /**
     * @return string
     */
    protected function _notesTableName()
    {
        return Mage::helper('tnw_salesforce')->getTable('sales_flat_creditmemo_comment');
    }

    /**
     * @comment return parent entity items
     * @param $_entity Mage_Sales_Model_Order_Creditmemo
     * @return mixed
     */
    public function getItems($_entity)
    {
        $_itemCollection = $_entity->getItemsCollection();
        $_hasOrderItemId = $_itemCollection->walk('getOrderItemId');

        $iDs = array_values($_entity->getOrder()->getCreditmemosCollection()->walk('getId'));
        sort($iDs, SORT_NUMERIC);

        $items = array_search($_entity->getId(), $iDs) === 0
            ? parent::getItems($_entity) : array();
        /** @var Mage_Sales_Model_Order_Creditmemo_Item $item */
        foreach ($_itemCollection as $item) {
            if ($item->isDeleted() || $item->getOrderItem()->getParentItem()) {
                continue;
            }

            $item = clone $item;
            if ($item->getOrderItem()->getProductType() != Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                $items[] =  $item;
                continue;
            }

            $item
                ->setTaxAmount(null)
                ->setBaseTaxAmount(null)
                ->setHiddenTaxAmount(null)
                ->setBaseHiddenTaxAmount(null)
                ->setRowTotal(null)
                ->setBaseRowTotal(null)
                ->setDiscountAmount(null)
                ->setBaseDiscountAmount(null);

            switch (Mage::getStoreConfig(TNW_Salesforce_Helper_Config_Sales::XML_PATH_ORDERS_BUNDLE_ITEM_SYNC)) {
                case 0:
                    $items[] =  $item;

                    /** @var Mage_Sales_Model_Order_Item $_orderItem */
                    foreach ($item->getOrderItem()->getChildrenItems() as $_orderItem) {
                        $_itemId = array_search($_orderItem->getId(), $_hasOrderItemId);

                        if (!$_itemId) {
                            continue;
                        }

                        $_item   = clone $_itemCollection->getItemById($_itemId);
                        if (!$_item instanceof Mage_Sales_Model_Order_Creditmemo_Item) {
                            continue;
                        }

                        $item
                            ->setTaxAmount($item->getTaxAmount() + $_item->getTaxAmount())
                            ->setBaseTaxAmount($item->getBaseTaxAmount() + $_item->getBaseTaxAmount())
                            ->setHiddenTaxAmount($item->getHiddenTaxAmount() + $_item->getHiddenTaxAmount())
                            ->setBaseHiddenTaxAmount($item->getBaseHiddenTaxAmount() + $_item->getBaseHiddenTaxAmount())
                            ->setRowTotal($item->getRowTotal() + $_item->getRowTotal())
                            ->setBaseRowTotal($item->getBaseRowTotal() + $_item->getBaseRowTotal())
                            ->setDiscountAmount($item->getDiscountAmount() + $_item->getDiscountAmount())
                            ->setBaseDiscountAmount($item->getBaseDiscountAmount() + $_item->getBaseDiscountAmount());
                    }
                    break;

                case 1:
                    $items[] =  $item;

                    /** @var Mage_Sales_Model_Order_Item $_orderItem */
                    foreach ($item->getOrderItem()->getChildrenItems() as $_orderItem) {
                        $_itemId = array_search($_orderItem->getId(), $_hasOrderItemId);

                        if (!$_itemId) {
                            continue;
                        }

                        $_item   = clone $_itemCollection->getItemById($_itemId);
                        if (!$_item instanceof Mage_Sales_Model_Order_Creditmemo_Item) {
                            continue;
                        }

                        $item
                            ->setTaxAmount($item->getTaxAmount() + $_item->getTaxAmount())
                            ->setBaseTaxAmount($item->getBaseTaxAmount() + $_item->getBaseTaxAmount())
                            ->setHiddenTaxAmount($item->getHiddenTaxAmount() + $_item->getHiddenTaxAmount())
                            ->setBaseHiddenTaxAmount($item->getBaseHiddenTaxAmount() + $_item->getBaseHiddenTaxAmount())
                            ->setRowTotal($item->getRowTotal() + $_item->getRowTotal())
                            ->setBaseRowTotal($item->getBaseRowTotal() + $_item->getBaseRowTotal())
                            ->setDiscountAmount($item->getDiscountAmount() + $_item->getDiscountAmount())
                            ->setBaseDiscountAmount($item->getBaseDiscountAmount() + $_item->getBaseDiscountAmount());

                        $_item
                            ->setTaxAmount(null)
                            ->setBaseTaxAmount(null)
                            ->setHiddenTaxAmount(null)
                            ->setBaseHiddenTaxAmount(null)
                            ->setRowTotal(null)
                            ->setBaseRowTotal(null)
                            ->setDiscountAmount(null)
                            ->setBaseDiscountAmount(null)
                            ->setBundleItemToSync(TNW_Salesforce_Helper_Config_Sales::BUNDLE_ITEM_MARKER
                                . $item->getSku());

                        $items[] = $_item;
                    }
                    break;

                case 2:
                    /** @var Mage_Sales_Model_Order_Item $_orderItem */
                    foreach ($item->getOrderItem()->getChildrenItems() as $_orderItem) {
                        $_itemId = array_search($_orderItem->getId(), $_hasOrderItemId);

                        if (!$_itemId) {
                            continue;
                        }
                        
                        $_item   = clone $_itemCollection->getItemById($_itemId);
                        if (!$_item instanceof Mage_Sales_Model_Order_Creditmemo_Item) {
                            continue;
                        }

                        $_item
                            ->setBundleItemToSync(TNW_Salesforce_Helper_Config_Sales::BUNDLE_ITEM_MARKER . $item->getSku());

                        $items[] = $_item;
                    }
                    break;
            }
        }

        return $items;
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $_entity
     * @param string $feeName
     * @param array $feeData
     * @return Mage_Sales_Model_Order_Creditmemo_Item
     */
    protected function generateFeeEntityItem($_entity, $feeName, $feeData)
    {
        return Mage::getModel('sales/order_creditmemo_item')
            ->setCreditmemo($_entity)
            ->addData(array(
                'name'                    => $feeData['Name'],
                'sku'                     => $feeData['ProductCode'],
                $this->getItemQtyField()  => 1,
                'description'             => Mage::helper('tnw_salesforce')->__($feeName),
                'row_total'               => $this->getEntityPrice($_entity, sprintf('%sAmount', ucfirst($feeName))),
                'base_original_price'     => 0,
                'product_type'            => 'simple'
            ));
    }

    /**
     * Clean up all the data & memory
     */
    protected function _onComplete()
    {
        parent::_onComplete();

        if (Mage::helper('tnw_salesforce')->isRemoteLogEnabled()) {
            /** @var TNW_Salesforce_Helper_Report $logger */
            $logger = Mage::helper('tnw_salesforce/report');
            $logger->reset();

            $logger->add('Salesforce', ucwords($this->_magentoEntityName),
                $this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))],
                $this->_cache['responses'][strtolower($this->getManyParentEntityType())]);

            if (!empty($this->_cache['responses'][lcfirst($this->getItemsField())])) {
                $logger->add('Salesforce', ucwords($this->_magentoEntityName) . 'Item',
                    $this->_cache[sprintf('%sToUpsert', lcfirst($this->getItemsField()))],
                    $this->_cache['responses'][lcfirst($this->getItemsField())]);
            }

            if (!empty($this->_cache['responses']['notes'])) {
                $logger->add('Salesforce', 'Note', $this->_cache['notesToUpsert'], $this->_cache['responses']['notes']);
            }

            $logger->send();
        }

        // Logout
        $this->reset();
        $this->clearMemory();
    }


    /**
     * @param $_entity Mage_Sales_Model_Order_Creditmemo
     * @param $item Mage_Sales_Model_Order_Creditmemo_Item
     */
    protected function _prepareAdditionalFees($_entity, $item)
    {
        /** @var Mage_Sales_Model_Order_Item $_orderItem */
        $_orderItem           = Mage::getModel('sales/order_item');
        $productSalesforceId  = $this->_getObjectByEntityItemType($item, 'Product')->getData('salesforce_id');

        $records              = !empty($this->_cache['creditmemoOrderLookup'][$_entity->getOrder()->getRealOrderId()]->OrderItems)
            ? $this->_cache['creditmemoOrderLookup'][$_entity->getOrder()->getRealOrderId()]->OrderItems->records : array();

        foreach ($records as $record) {
            if ($record->PricebookEntry->Product2Id != $productSalesforceId) {
                continue;
            }

            $_orderItem->setData('salesforce_id', $record->Id);
            break;
        }

        //FIX: $item->getOrderItem()->getData('salesforce_id')
        $item->setOrderItem($_orderItem);
    }

    /**
     * @return bool|void
     * Prepare values for the synchroization
     */
    public function reset()
    {
        $return = parent::reset();

        $this->_cache['orderToActivate'] = array();
        return $return;
    }
}