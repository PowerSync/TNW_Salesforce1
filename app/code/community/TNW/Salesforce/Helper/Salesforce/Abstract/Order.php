<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Abstract
 *
 * @method Mage_Sales_Model_Order getEntityByItem($_entity)
 * @method Mage_Sales_Model_Order getEntityCache($cachePrefix)
 * @method Mage_Sales_Model_Order _loadEntityByCache($_entityId, $_entityNumber)
 */
abstract class TNW_Salesforce_Helper_Salesforce_Abstract_Order extends TNW_Salesforce_Helper_Salesforce_Abstract_Sales
{
    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityId = 'entity_id';

    /**
     * @comment current module name
     * @var string
     */
    protected $_modulePrefix = 'tnw_salesforce';

    protected $_availableFees = array(
        'tax',
        'shipping',
        'discount'
    );

    /**
     * @var array
     */
    protected $_allowedOrderStatuses = array();

    /**
     * @var array
     */
    protected $_quotes = array();

    /**
     * @return string
     */
    public function getModulePrefix()
    {
        return $this->_modulePrefix;
    }

    /**
     * @return array
     */
    public function getMagentoEntityId()
    {
        return $this->_magentoEntityId;
    }

    /**
     * @param array $magentoEntityId
     * @return $this
     */
    public function setMagentoEntityId($magentoEntityId)
    {
        $this->_magentoEntityId = $magentoEntityId;
        return $this;
    }

    /**
     * @param $parentEntityNumber
     * @param $qty
     * @param $productIdentifier
     * @param string $description
     *
     * @param Mage_Sales_Model_Order_Item $item
     * @return bool
     */
    protected function _doesCartItemExist($parentEntityNumber, $qty, $productIdentifier, $description = 'default', $item = null)
    {
        /**
         * @var $parentEntityCacheKey string  opportunityLookup|$parentEntityCacheKey
         */
        $parentEntityCacheKey = sprintf('%sLookup', $this->_salesforceEntityName);
        if (!empty($this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$this->getItemsField()})) {
            foreach ($this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$this->getItemsField()}->records as $_cartItem) {
                if ($_cartItem->Id != $item->getData('salesforce_id')) {
                    continue;
                }

                return $_cartItem->Id;
            }
        }

        return null;
    }

    /**
     * @param Mage_Sales_Model_Order $_entity
     * @return array
     */
    public function getProductIdsFromEntity($_entity)
    {
        $_productIds = array();
        /** @var Mage_Sales_Model_Order_Item $_item */
        foreach ($this->getItems($_entity) as $_item) {
            $_productIds[] = (int) $this->getProductIdFromCart($_item);
        }

        return array_filter($_productIds);
    }

    /**
     * @param $parentEntityNumber
     * @return Mage_Core_Model_Abstract|mixed
     */
    public function getParentEntity($parentEntityNumber)
    {
        $magentoEntityName = $this->getMagentoEntityName();

        /**
         * @var Mage_Sales_Model_Order|Mage_Sales_Model_Quote|Ophirah_Qquoteadv_Model_Qqadvcustomer
         */
        $parentEntity = (Mage::registry($magentoEntityName . '_cached_' . $parentEntityNumber));

        if (!$parentEntity) {
            $idField = $this->getMagentoEntityId();
            $parentEntity = Mage::getModel($this->getMagentoEntityModel())->load($parentEntityNumber, $idField);
        }

        return $parentEntity;
    }

    /**
     * Return parent entity items and bundle items
     *
     * @param $parentEntity Mage_Sales_Model_Order
     * @return mixed
     */
    public function getItems($parentEntity)
    {
        $_items = parent::getItems($parentEntity);

        /** @var Mage_Sales_Model_Order_Item $_item */
        foreach ($parentEntity->getAllVisibleItems() as $_item) {
            $_item = clone $_item;
            if ($_item->getProductType() != Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                $_items[] = $_item;
                continue;
            }

            switch (Mage::getStoreConfig(TNW_Salesforce_Helper_Config_Sales::XML_PATH_ORDERS_BUNDLE_ITEM_SYNC)) {
                case 0:
                    $_items[] = $_item;
                    break;

                case 1:
                    $_items[] = $_item;

                    foreach ($_item->getChildrenItems() as $_childItem) {
                        $_childItem = clone $_childItem;
                        $_childItem
                            ->setTaxAmount(null)
                            ->setBaseTaxAmount(null)
                            ->setHiddenTaxAmount(null)
                            ->setBaseHiddenTaxAmount(null)
                            ->setRowTotal(null)
                            ->setBaseRowTotal(null)
                            ->setDiscountAmount(null)
                            ->setBaseDiscountAmount(null)
                            ->setBundleItemToSync(TNW_Salesforce_Helper_Config_Sales::BUNDLE_ITEM_MARKER
                                . $_item->getSku());

                        $_items[] = $_childItem;
                    }
                    break;

                case 2:
                    foreach ($_item->getChildrenItems() as $_childItem) {
                        $_childItem = clone $_childItem;
                        $_childItem
                            ->setBundleItemToSync(TNW_Salesforce_Helper_Config_Sales::BUNDLE_ITEM_MARKER . $_item->getSku());

                        $_items[] = $_childItem;
                    }
                    break;
            }
        }

        return $_items;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @param $item Mage_Sales_Model_Order_Item
     */
    protected function _prepareAdditionalFees($_entity, $item)
    {
        $_salesforceId        = null;
        $_entityNumber        = $this->_getEntityNumber($_entity);
        $parentEntityCacheKey = sprintf('%sLookup', $this->_salesforceEntityName);
        $productSalesforceId  = $this->_getObjectByEntityItemType($item, 'Product')->getData('salesforce_id');

        $records              = !empty($this->_cache[$parentEntityCacheKey][$_entityNumber]->{$this->getItemsField()})
            ? $this->_cache[$parentEntityCacheKey][$_entityNumber]->{$this->getItemsField()}->records : array();

        foreach ($records as $_cartItem) {
            if ($_cartItem->PricebookEntry->Product2Id != $productSalesforceId) {
                continue;
            }

            $_salesforceId = $_cartItem->Id;
            break;
        }

        $item->setData('salesforce_id', $_salesforceId);
    }

    /**
     * @comment assign Opportynity/Order Id
     */
    protected function _getParentEntityId($parentEntityNumber)
    {
        if (!$this->getSalesforceParentIdField()) {
            $this->setSalesforceParentIdField($this->getUcParentEntityType() . 'Id');
        }
        $upsertedEntity = $this->_cache['upserted' . $this->getManyParentEntityType()];
        //return $this->_cache['upserted' . $this->getManyParentEntityType()][$parentEntityNumber];
        return (array_key_exists($parentEntityNumber, $upsertedEntity)) ? $upsertedEntity[$parentEntityNumber] :  NULL;
    }

    /**
     * @return string
     */
    public function getManyParentEntityType()
    {
        if (!$this->_manyParentEntityType) {

            $this->_manyParentEntityType = $this->getUcParentEntityType();
            $this->_manyParentEntityType .= 's';

            if ($this->_salesforceEntityName == 'opportunity') {
                $this->_manyParentEntityType = 'Opportunities';
            }
        }

        return $this->_manyParentEntityType;
    }

    /**
     * @return string
     */
    public function getItemsField()
    {
        if (!$this->_itemsField) {
            $this->_itemsField = $this->getUcParentEntityType();

            if ($this->_salesforceEntityName == 'opportunity') {
                $this->_itemsField .= 'Line';
            } elseif ($this->_salesforceEntityName == 'salesorder') {
                $this->_itemsField = 'Order';
            }

            $this->_itemsField .= 'Items';
        }

        return $this->_itemsField;
    }

    protected function _prepareTechnicalPrefixes()
    {

        /**
         * @comment call getters for fields filling
         */
        $this->getUcParentEntityType();
        $this->getManyParentEntityType();
        $this->getItemsField();
    }


    /**
     * Sync customer w/ SF before creating the order
     *
     * @param $order Mage_Core_Model_Abstract|Mage_Sales_Model_Order|Mage_Sales_Model_Quote
     * @return Mage_Customer_Model_Customer
     * @deprecated
     */
    protected function _getCustomer($order)
    {
        return $this->_generateCustomerByOrder($order);
    }

    /**
     * @param $_entityItem
     * @return bool|void
     * @throws Exception
     */
    protected function _getEntityItemSalesforceId($_entityItem)
    {
        $this->_prepareTechnicalPrefixes();
        $_entity = $this->getEntityByItem($_entityItem);
        return $this->_doesCartItemExist($this->_getEntityNumber($_entity), null, null, null, $_entityItem);
    }

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @param $type string
     * @return mixed
     */
    protected function _getObjectByEntityType($_entity, $type)
    {
        switch($type)
        {
            case 'Custom':
                $_object   = $_entity->getStore();
                break;

            case 'Order':
                $_object   = $_entity;
                break;

            case 'Payment':
                $_object   = $_entity->getPayment();
                break;

            case 'Customer':
                $_entityNumber = $this->_getEntityNumber($_entity);
                $_object   = $this->_cache[sprintf('%sCustomers', $this->_magentoEntityName)][$_entityNumber];
                break;

            case 'Customer Group':
                $_customer = $this->_getObjectByEntityType($_entity, 'Customer');
                $_groupId  = ($_entity->getCustomerGroupId() !== NULL)
                    ? $_entity->getCustomerGroupId() : $_customer->getGroupId();

                $_object   = Mage::getModel('customer/group')->load($_groupId);
                break;

            case 'Billing':
                $_object   = $_entity->getBillingAddress();
                break;

            case 'Shipping':
                $_object   = $_entity->getShippingAddress();
                break;

            default:
                $_object   = null;
                break;
        }

        return $_object;
    }

    /**
     * @param $_entityItem Mage_Sales_Model_Order_Item
     * @param $_type string
     * @return mixed
     */
    protected function _getObjectByEntityItemType($_entityItem, $_type)
    {
        switch($_type)
        {
            case 'Order':
                $_object = $this->getEntityByItem($_entityItem);
                break;

            case 'Order Item':
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
                $_object = $this->_getObjectByEntityItemType($_entityItem, 'Order')
                    ->getStore();
                break;

            default:
                $_object = null;
                break;
        }

        return $_object;
    }

    /**
     * @param $_entityItem Mage_Sales_Model_Order_Item
     * @throws Exception
     */
    protected function _prepareEntityItemObjCustom($_entityItem)
    {
        $_entity       = $this->getEntityByItem($_entityItem);
        $_entityNumber = $this->_getEntityNumber($_entity);
        /** @var Mage_Catalog_Model_Product $product */
        $product       = $this->_getObjectByEntityItemType($_entityItem, 'Product');

        $lookupKey     = sprintf('%sLookup', $this->_salesforceEntityName);
        $hasReductionOrder = !empty($this->_cache[$lookupKey][$_entityNumber]->hasReductionOrder)
            ? $this->_cache[$lookupKey][$_entityNumber]->hasReductionOrder : false;
        if (empty($this->_obj->Id) && $hasReductionOrder) {
            $this->logNotice('Product SKU ('.$product->getSku().') was skipped and not attached to the order. Please remove all Reduction orders from Salesforce manually, then manually re-sync this Order followed by all Credit Memos for this Order.');
            return;
        }

        $this->_obj->{$this->getSalesforceParentIdField()} = $this->_getParentEntityId($_entityNumber);

        $_isDescription = property_exists($this->_obj, 'Description');
        if ((!$_isDescription || ($_isDescription && empty($this->_obj->Description)))
            && $_entityItem->getBundleItemToSync()
        ) {
            $this->_obj->Description = $_entityItem->getBundleItemToSync();
        }

        // use_product_campaign_assignment
        if (
            Mage::helper('tnw_salesforce/config_sales')->useProductCampaignAssignment()
            && $_entity instanceof Mage_Sales_Model_Order
            && $product->getData('salesforce_campaign_id')
        ) {
            $this->_cache['productCampaignAssignment'][$product->getData('salesforce_campaign_id')][]
                = $this->_getObjectByEntityType($_entity, 'Customer');
        }

        // PricebookEntryId
        if (!property_exists($this->_obj, 'Id')) {
            $_currencyCode    = $this->getCurrencyCode($_entity);
            $pricebookEntryId = $product->getSalesforcePricebookId();

            if (!empty($pricebookEntryId)) {
                $valuesArray = explode("\n", $pricebookEntryId);

                if (!empty($valuesArray)) {
                    foreach ($valuesArray as $value) {

                        if (strpos($value, ':') !== false) {
                            $tmp = explode(':', $value);
                            if (
                                isset($tmp[0])
                                && ($tmp[0] == $_currencyCode || empty($_currencyCode))
                            ) {
                                $pricebookEntryId = $tmp[1];
                            }
                        }
                    }
                }
            }

            if (empty($pricebookEntryId)) {
                throw new Exception("NOTICE: Product w/ SKU (" . $_entityItem->getSku() . ") is not synchronized, could not add to $this->_salesforceEntityName!");
            }

            $this->_obj->PricebookEntryId = $pricebookEntryId;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace("Opportunity/Order Item Object: \n" . print_r($this->_obj, true));

        $key = empty($_entityItem->getId())
            ? sprintf('%s_%s', $_entityNumber, count($this->_cache[sprintf('%sToUpsert', lcfirst($this->getItemsField()))]))
            : $_entityItem->getId();

        $this->_cache[sprintf('%sProductsToSync', lcfirst($this->getItemsField()))][$this->_getParentEntityId($_entityNumber)][] = $product->getSku();
        $this->_cache[sprintf('%sToUpsert', lcfirst($this->getItemsField()))]['cart_' . $key] = $this->_obj;
    }

    /**
     * @comment See if created from Abandoned Cart
     * @param $quotes
     */
    protected function _findAbandonedCart($quotes)
    {
        if (!is_array($quotes)) {
            $quotes = array($quotes);
        }

        // See if created from Abandoned Cart
        if (Mage::helper('tnw_salesforce/config_sales_abandoned')->isEnabled() && !empty($quotes)) {
            $sql = "SELECT entity_id, salesforce_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_quote') . "` WHERE entity_id IN ('" . join("','", $quotes) . "')";
            $row = Mage::helper('tnw_salesforce')->getDbConnection('read')->query($sql)->fetchAll();
            if ($row) {
                foreach ($row as $_item) {
                    if (array_key_exists('salesforce_id', $_item) && $_item['salesforce_id']) {
                        $this->_cache['abandonedCart'][$_item['entity_id']] = $_item['salesforce_id'];
                    }
                }
            }

        }
    }

    /**
     * @param $order Mage_Sales_Model_Order
     * @return null|string
     */
    protected function _getPricebookIdToOrder($order)
    {
        $pricebook2Id = null;

        try {
            /** @var tnw_salesforce_helper_data $_helper */
            $_helper = Mage::helper('tnw_salesforce');
            $pricebook2Id = Mage::app()
                ->getStore($order->getStoreId())
                ->getConfig($_helper::PRODUCT_PRICEBOOK);
        }
        catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("INFO: Could not load pricebook based on the order ID. Loading default pricebook based on current store ID.");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());

            $_standardPricebookId = Mage::helper('tnw_salesforce/salesforce_data')->getStandardPricebookId();
            $pricebook2Id = (Mage::helper('tnw_salesforce')->getDefaultPricebook())
                ? Mage::helper('tnw_salesforce')->getDefaultPricebook() : $_standardPricebookId;
        }

        return $pricebook2Id;
    }

    /**
     * @param array $_ids
     */
    protected function _massAddBefore($_ids)
    {
        parent::_massAddBefore($_ids);
        $this->_quotes = array();
    }

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @return bool
     */
    protected function _checkMassAddEntity($_entity)
    {
        $_entityNumber = $this->_getEntityNumber($_entity);

        /** @comment check zero orders sync */
        if (!Mage::helper('tnw_salesforce/config_sales_order')->isEnabledZeroOrderSync() && $_entity->getGrandTotal() == 0) {
            $this->logNotice('SKIPPED: Sync for order #' . $_entityNumber . ', grand total is zero and synchronization for these order is disabled in configuration!');
            return false;
        }

        if (!Mage::helper('tnw_salesforce')->syncAllOrders()
            && !in_array($_entity->getStatus(), $this->_allowedOrderStatuses)
        ) {
            $this->logNotice('SKIPPED: Sync for order #' . $_entity->getId() . ', sync for order status "' . $_entity->getStatus() . '" is disabled!');
            return false;
        }

        // Get Magento customer object
        $customer = $this->_generateCustomerByOrder($_entity);

        // Associate order Number with a customer ID
        $_customerId = ($customer->getId())
            ? $customer->getId() : sprintf('guest_%d', $this->_guestCount++);

        $customer->setId($_customerId);

        // Store order number and customer Email into a variable for future use
        $_orderEmail = strtolower($customer->getEmail());
        if (empty($_orderEmail)) {
            $this->logError('SKIPPED: Sync for order #' . $_entityNumber . ' failed, order is missing an email address!');
            return false;
        }

        $this->_cache['orderCustomers'][$_entityNumber] = $customer;
        $this->_cache[sprintf('%sToCustomerId', $this->_magentoEntityName)][$_entityNumber] = $_customerId;
        $this->_cache[sprintf('%sToEmail', $this->_magentoEntityName)][$_entityNumber] = $_orderEmail;

        // Check if customer from this group is allowed to be synchronized
        $_customerGroup = $_entity->getData('customer_group_id');

        if ($_customerGroup === NULL) {
            $_customerGroup = $customer->getGroupId();
        }

        if ($_customerGroup === NULL && !$this->isFromCLI()) {
            $_customerGroup = Mage::getSingleton('customer/session')->getCustomerGroupId();
        }

        if (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($_customerGroup)) {
            $this->logNotice("SKIPPING: Sync for customer group #" . $_customerGroup . " is disabled!");
            return false;
        }

        $_websiteId = Mage::app()->getStore($_entity->getData('store_id'))->getWebsiteId();
        $this->_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];
        $this->_emails[$_customerId] = $_orderEmail;

        if ($_entity->getQuoteId()) {
            $this->_quotes[] = $_entity->getQuoteId();
        }

        return true;
    }

    /**
     *
     */
    protected function _massAddAfter()
    {
        $this->_findAbandonedCart($this->_quotes);
        parent::_massAddAfter();
    }

    /**
     * @param $_entityNumber
     * @return bool
     */
    protected function _checkSyncCustomer($_entityNumber)
    {
        $syncCustomer = parent::_checkSyncCustomer($_entityNumber);

        /**
         * If customer has not default billing/shipping addresses - we can use data from order if it's allowed
         */
        if (!$syncCustomer && Mage::helper('tnw_salesforce')->canUseOrderAddress()) {
            /** @var $customer Mage_Customer_Model_Customer */
            $customer    = $this->_cache[sprintf('%sCustomers', $this->_magentoEntityName)][$_entityNumber];

            if (!$customer->getDefaultBillingAddress()) {
                $entityAddress = $this->getEntityCache($_entityNumber)->getBillingAddress();
                if ($entityAddress instanceof Mage_Customer_Model_Address_Abstract) {
                    /** @var Mage_Customer_Model_Address $customerAddress */
                    $customerAddress = Mage::getModel('customer/address');
                    $customerAddress->setData($entityAddress->getData());
                    $customerAddress->setId($entityAddress->getId());
                    $customerAddress->setIsDefaultBilling(true);

                    $customer->setData('default_billing', $customerAddress->getId());
                    $customer->addAddress($customerAddress);
                    $syncCustomer = true;
                }
            }

            if (!$customer->getDefaultShippingAddress()) {
                $entityAddress = $this->getEntityCache($_entityNumber)->getShippingAddress();
                if ($entityAddress instanceof Mage_Customer_Model_Address_Abstract) {
                    /** @var Mage_Customer_Model_Address $customerAddress */
                    $customerAddress = Mage::getModel('customer/address');
                    $customerAddress->setData($entityAddress->getData());
                    $customerAddress->setId($entityAddress->getId());
                    $customerAddress->setIsDefaultShipping(true);

                    $customer->setData('default_shipping', $customerAddress->getId());
                    $customer->addAddress($customerAddress);
                    $syncCustomer = true;
                }
            }
        }

        return $syncCustomer;
    }

    /**
     * Try to find order in SF and save in local cache
     * @deprecated
     */
    protected function _prepareOrderLookup()
    {
        // Salesforce lookup, find all orders by Magento order number
        $this->_cache['orderLookup'] = Mage::helper('tnw_salesforce/salesforce_data_order')->lookup($this->_cache['entitiesUpdating']);
    }

    /**
     *
     */
    protected function _massAddAfterLookup()
    {
        $this->_prepareOrderLookup();
    }

    /**
     * @param $ids
     * Reset Salesforce ID in Magento for the order
     * @deprecated
     */
    public function resetOrder($ids)
    {
        $this->resetEntity($ids);
    }

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @throws Exception
     * @return array
     */
    protected function _getEntityNotesCollection($_entity)
    {
        return $_entity->getAllStatusHistory();
    }

    /**
     * @param $notes Mage_Sales_Model_Order_Status_History
     * @return mixed
     */
    protected function _getNotesParentSalesforceId($notes)
    {
        return $notes->getOrder()->getSalesforceId();
    }

    /**
     * @param $_note Mage_Sales_Model_Order_Status_History
     * @return bool
     */
    protected function _checkNotesItem($_note)
    {
        return $_note->getData('entity_name') == 'order' && parent::_checkNotesItem($_note);
    }

    /**
     * @return string
     */
    protected function _notesTableName()
    {
        return Mage::helper('tnw_salesforce')->getTable('sales_flat_order_status_history');
    }

    protected function _checkPrepareEntityBefore($_key)
    {
        $skippedKey   = sprintf('%s_skipped', strtolower($this->getManyParentEntityType()));
        $toEmailKey   = sprintf('%sToEmail', $this->_magentoEntityName);
        $_orderNumber = $this->_cache['entitiesUpdating'][$_key];

        if (array_key_exists('leadsFailedToConvert', $this->_cache) &&
            is_array($this->_cache['leadsFailedToConvert']) &&
            array_key_exists($_orderNumber, $this->_cache['leadsFailedToConvert'])
        ){
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('SKIPPED: Order (' . $_orderNumber . '), lead failed to convert');

            unset($this->_cache['entitiesUpdating'][$_key]);
            unset($this->_cache[$toEmailKey][$_orderNumber]);
            $this->_allResults[$skippedKey] = array_key_exists($skippedKey, $this->_allResults)
                ? $this->_allResults[$skippedKey]++ : 1;

            return false;
        }

        return true;
    }

    protected function _checkPrepareEntityAfter($_key)
    {
        $lookupKey    = sprintf('%sLookup', $this->_salesforceEntityName);
        $skippedKey   = sprintf('%s_skipped', strtolower($this->getManyParentEntityType()));
        $toEmailKey   = sprintf('%sToEmail', $this->_magentoEntityName);
        $_orderNumber = $this->_cache['entitiesUpdating'][$_key];

        if (
            isset($this->_cache[$lookupKey][$_orderNumber]->Pricebook2Id)
            && $this->_obj->Pricebook2Id != $this->_cache[$lookupKey][$_orderNumber]->Pricebook2Id
        ){
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace(sprintf("SKIPPED %s: %s - %s uses a different pricebook(%s), please change it in Salesforce.",
                    ucfirst($this->_magentoEntityName), $this->getUcParentEntityType(), $_orderNumber, $this->_cache[$lookupKey][$_orderNumber]->Pricebook2Id));

            unset($this->_cache['entitiesUpdating'][$_key]);
            unset($this->_cache[$toEmailKey][$_orderNumber]);
            $this->_allResults[$skippedKey] = array_key_exists($skippedKey, $this->_allResults)
                ? $this->_allResults[$skippedKey]++ : 1;

            return false;
        }

        return true;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @return mixed
     */
    protected function _getEntityNumber($_entity)
    {
        return $_entity->getRealOrderId();
    }

    /**
     * @param $order Mage_Sales_Model_Order
     * @throws Exception
     */
    public function updateStatus($order)
    {
        $_entityNumber = $this->_getEntityNumber($order);
        if (Mage::getModel('tnw_salesforce/localstorage')->getObject($order->getId())) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice("SKIPPING: Order #$_entityNumber is already queued for update.");
            return;
        }

        if (!$this->reset()) {
            return;
        }

        $_updateCustomer = $this->getUpdateCustomer();
        $this->setUpdateCustomer(Mage::getStoreConfig(TNW_Salesforce_Helper_Config_Sales::XML_PATH_ORDERS_STATUS_UPDATE_CUSTOMER));
        // Added a parameter to skip customer sync when updating order status
        $checkAdd = $this->massAdd($order->getId(), false);
        $this->setEntityCache($order);
        $this->setUpdateCustomer($_updateCustomer);

        if (!$checkAdd) {
            return;
        }

        $_lookupKey    = sprintf('%sLookup', $this->_salesforceEntityName);
        if (isset($this->_cache[$_lookupKey][$_entityNumber])) {
            $this->_prepareEntity();

            // Push Order
            $this->_pushEntity();

            // Push Activated order
            $this->_pushRemainingCustomEntityData();
        }
        else {
            // Need to do full sync instead
            $res = $this->process('full');
            if ($res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SUCCESS: Updating Order #" . $_entityNumber);
            }
        }
    }

    /**
     * @return bool|void
     * Prepare values for the synchroization
     */
    public function reset()
    {
        $return = parent::reset();

        // get all allowed order statuses from configuration
        $this->_allowedOrderStatuses = explode(',', Mage::helper('tnw_salesforce')->getAllowedOrderStates());
        $this->_cache['productCampaignAssignment'] = array();
        $this->_cache['products'] = array();

        return $return;
    }
}