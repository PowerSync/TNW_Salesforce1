<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Abstract
 *
 * @method Mage_Sales_Model_Order getEntityByItem($_entity)
 * @method Mage_Sales_Model_Order _loadEntityByCache($_entityId, $_entityNumber)
 */
abstract class TNW_Salesforce_Helper_Salesforce_Abstract_Order extends TNW_Salesforce_Helper_Salesforce_Abstract_Base
{
    /**
     * @var array
     */
    protected $_stockItems = array();

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

    protected $_pricebookEntryId = NULL;

    /**
     * @comment order item field aliases
     * @var array
     */
    protected $_itemFieldAlias = array();

    /**
     * @var array
     */
    protected $_allowedOrderStatuses = array();

    /**
     * @var int
     */
    protected $_guestCount = 0;

    /**
     * @var array
     */
    protected $_quotes = array();

    /**
     * @var array
     */
    protected $_emails = array();

    /**
     * @var array
     */
    protected $_websites = array();

    /**
     * @var bool
     */
    protected $_updateCustomer = true;

    /**
     * @param $isUpdate
     * @return $this
     */
    public function setUpdateCustomer($isUpdate)
    {
        $this->_updateCustomer = $isUpdate;
        return $this;
    }

    /**
     * @return bool
     */
    public function getUpdateCustomer()
    {
        return $this->_updateCustomer;
    }

    /**
     * @return array
     */
    public function getItemFieldAlias()
    {
        return $this->_itemFieldAlias;
    }

    /**
     * @param array $itemFieldAlias
     * @return $this
     */
    public function setItemFieldAlias($itemFieldAlias)
    {
        $this->_itemFieldAlias = $itemFieldAlias;
        return $this;
    }

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

        if (
            $this->_cache[$parentEntityCacheKey]
            && array_key_exists($parentEntityNumber, $this->_cache[$parentEntityCacheKey])
            && $this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$this->getItemsField()}
        ) {
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

        return $_productIds;
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
        $_items = array();

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
                            ->setRowTotalInclTax(null)
                            ->setBaseRowTotalInclTax(null)
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
     */
    protected function _prepareEntityItemAfter($_entity)
    {
        $this->_applyAdditionalFees($_entity);
    }

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @param $item Varien_Object
     */
    protected function _prepareAdditionalFees($_entity, $item)
    {
        $_salesforceId        = null;
        $_entityNumber        = $this->_getEntityNumber($_entity);
        $parentEntityCacheKey = sprintf('%sLookup', $this->_salesforceEntityName);

        if (
            $this->_cache[$parentEntityCacheKey]
            && array_key_exists($_entityNumber, $this->_cache[$parentEntityCacheKey])
            && $this->_cache[$parentEntityCacheKey][$_entityNumber]->{$this->getItemsField()}
        ) {
            foreach ($this->_cache[$parentEntityCacheKey][$_entityNumber]->{$this->getItemsField()}->records as $_cartItem) {
                if ($_cartItem->PricebookEntry->Product2Id != $item->getData('Id')) {
                    continue;
                }

                $_salesforceId = $_cartItem->Id;
                break;
            }
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
     * @param $item
     * @param int $qty
     * @return float
     */
    protected function _prepareItemPrice($item, $qty = 1)
    {
        $netTotal = $this->_calculateItemPrice($item, $qty);

        /**
         * @comment prepare formatted price
         */
        return $this->numberFormat($netTotal);
    }

    /**
     * @param $item
     * @param int $qty
     * @return float
     */
    protected function _calculateItemPrice($item, $qty = 1)
    {
        if (!Mage::helper('tnw_salesforce')->useTaxFeeProduct()) {
            $netTotal = $this->getEntityPrice($item, 'RowTotalInclTax');
        } else {
            $netTotal = $this->getEntityPrice($item, 'RowTotal');
        }

        if (!Mage::helper('tnw_salesforce')->useDiscountFeeProduct()) {
            $netTotal = ($netTotal - $this->getEntityPrice($item, 'DiscountAmount'));
            $netTotal = $netTotal / $qty;
        } else {
            $netTotal = $netTotal / $qty;
        }

        return $netTotal;
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
     * @return null
     * @throws Exception
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
                // Load by product Id only if bundled OR simple with options
                $_productId = $this->getProductIdFromCart($_entityItem);
                $storeId    = $this->_getObjectByEntityItemType($_entityItem, 'Custom')->getId();

                /** @var Mage_Catalog_Model_Product $_product */
                $_product   = Mage::getModel('catalog/product')
                    ->setStoreId($storeId);

                if ($_productId) {
                    $_object = $_product->load($_productId);
                    break;
                }
                else {
                    $_entity        = $this->_getObjectByEntityItemType($_entityItem, 'Order');
                    $pricebookId    = $this->_getPricebookIdToOrder($_entity);
                    $_currencyCode  = $this->getCurrencyCode($_entity);
                    $pricebookEntry = Mage::helper('tnw_salesforce/salesforce_data_product')
                        ->getProductPricebookEntry($_entityItem->getData('Id'), $pricebookId, $_currencyCode);

                    if (!$pricebookEntry || !isset($pricebookEntry['Id'])) {
                        throw new Exception("NOTICE: Product w/ SKU (" . $_entityItem->getData('ProductCode') . ") is not synchronized, could not add to $this->_salesforceEntityName!");
                    }

                    $_object = $_product->addData(array(
                        'name'                    => $_entityItem->getData('Name'),
                        'sku'                     => $_entityItem->getData('ProductCode'),
                        'salesforce_id'           => $_entityItem->getData('Id'),
                        'salesforce_pricebook_id' => $pricebookEntry['Id'],
                    ));
                    break;
                }

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

        /* Dump OpportunityLineItem object into the log */
        foreach ($this->_obj as $key => $_item) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Opportunity/Order Item Object: " . $key . " = '" . $_item . "'");
        }
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('-----------------');

        $key = $_entityItem->getId();
        // if it's fake product for order fee, has the same id's for all products
        if (!$product->getId()) {
            $key .= '_' . $_entityNumber;
        }

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
        $this->_guestCount = 0;
        $this->_quotes = $this->_emails = $this->_websites = array();
    }

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @return bool
     */
    protected function _checkMassAddEntity($_entity)
    {
        $_entityNumber = $this->_getEntityNumber($_entity);

        if ($_entity->getRelationChildId()) {
            $this->logNotice('SKIPPED: Sync for order #' . $_entityNumber . ', this one is reordered! Try to sync child order instead.');
            return false;
        }

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

        $_cacheCustomersKey = sprintf('%sCustomers', $this->_magentoEntityName);
        $this->_cache['leadsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')
            ->lookup($this->_cache[$_cacheCustomersKey]);
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')
            ->lookup($this->_cache[$_cacheCustomersKey]);
        $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')
            ->lookup($this->_cache[$_cacheCustomersKey]);

        $this->_prepareOrderLookup();

        /**
         * Order customers sync can be denied if we just update order status
         */
        if ($this->getUpdateCustomer()) {
            $this->syncEntityCustomers();
        }

        /**
         * define Salesforce data for order customers
         */
        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $key => $number) {
            $entity         = $this->_loadEntityByCache($key, $number);
            /** @var Mage_Customer_Model_Customer $customer */
            $customer       = $this->_getObjectByEntityType($entity, 'Customer');
            $customerEmail  = strtolower($customer->getEmail());

            if (!empty($this->_cache['accountsLookup'][0][$customerEmail])) {
                $_websiteId = $this->_websites[$this->_cache[sprintf('%sToCustomerId', $this->_magentoEntityName)][$number]];

                $customer->setData('salesforce_account_id', $this->_cache['accountsLookup'][0][$customerEmail]->Id);

                // Overwrite Contact Id for Person Account
                if (property_exists($this->_cache['accountsLookup'][0][$customerEmail], 'PersonContactId')) {
                    $customer->setData('salesforce_id', $this->_cache['accountsLookup'][0][$customerEmail]->PersonContactId);
                }

                // Overwrite from Contact Lookup if value exists there
                if (isset($this->_cache['contactsLookup'][$_websiteId][$customerEmail])) {
                    $customer->setData('salesforce_id', $this->_cache['contactsLookup'][$_websiteId][$customerEmail]->Id);
                }

                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SUCCESS: Automatic customer synchronization.');
            }
            else {
                /**
                 * No customers for this order in salesforce - error
                 */
                // Something is wrong, could not create / find Magento customer in SalesForce
                $this->logError('CRITICAL ERROR: Contact or Lead for Magento customer (' . $customerEmail . ') could not be created / found!');
                $this->_skippedEntity[$key] = $key;

                continue;
            }
        }

        foreach ($this->_skippedEntity as $_idToRemove) {
            unset($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING][$_idToRemove]);
        }
    }

    /**
     * Check and synchronize order customers if it's necessary
     */
    public function syncEntityCustomers()
    {
        /**
         * Force sync of the customer
         * Or if it's guest checkout: customer->getId() is empty
         * Or customer was not synchronized before: no account/contact ids ot lead not converted
         */

        $_customersToSync = array();

        $_cacheCustomersKey = sprintf('%sCustomers', $this->_magentoEntityName);
        /** @var $customer Mage_Customer_Model_Customer */
        foreach ($this->_cache[$_cacheCustomersKey] as $_entityNumber => $customer) {
            if (!$this->_checkSyncCustomer($_entityNumber)) {
                continue;
            }

            $_customersToSync[$_entityNumber] = $customer;
        }

        if (!empty($_customersToSync)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Synchronizing Guest/New customer...');

            $helperType = count($_customersToSync) > 1
                ? 'bulk' : 'salesforce';

            /** @var $manualSync TNW_Salesforce_Helper_Salesforce_Customer */
            $manualSync = Mage::helper('tnw_salesforce/' . $helperType . '_customer');
            if ($manualSync->reset() && $manualSync->forceAdd($_customersToSync) && $manualSync->process()) {
                set_time_limit(30);
                $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($this->_cache[$_cacheCustomersKey]);
                $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($this->_cache[$_cacheCustomersKey]);
            }
        }
    }

    /**
     * @param $_entityNumber
     * @return bool
     */
    protected function _checkSyncCustomer($_entityNumber)
    {
        $_entityId   = array_search($_entityNumber, $this->_cache['entitiesUpdating']);
        if (false === $_entityId) {
            return false;
        }

        $customerId  = $this->_cache[sprintf('%sToCustomerId', $this->_magentoEntityName)][$_entityNumber];
        $email       = $this->_cache[sprintf('%sToEmail', $this->_magentoEntityName)][$_entityNumber];
        $websiteSfId = $this->_websites[$customerId];

        /** @var $customer Mage_Customer_Model_Customer */
        $customer    = $this->_cache[sprintf('%sCustomers', $this->_magentoEntityName)][$_entityNumber];

        $syncCustomer = false;
        /**
         * If customer has not default billing/shipping addresses - we can use data from order if it's allowed
         */
        if (Mage::helper('tnw_salesforce')->canUseOrderAddress()) {

            if (!$customer->getDefaultBillingAddress()) {
                /** @var Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity */
                $entity = $this->_loadEntityByCache($_entityId, $_entityNumber);
                $entityAddress = $entity->getBillingAddress();
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
                /** @var Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity */
                $entity = $this->_loadEntityByCache($_entityId, $_entityNumber);
                $entityAddress = $entity->getShippingAddress();
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

        if (!isset($this->_cache['contactsLookup'][$websiteSfId][$email])
            || !isset($this->_cache['accountsLookup'][0][$email])
            || (
                isset($this->_cache['leadsLookup'][$websiteSfId][$email])
                && !$this->_cache['leadsLookup'][$websiteSfId][$email]->IsConverted
            )
        ) {
            $syncCustomer = true;
        }

        return $syncCustomer;
    }

    /**
     * Try to find order in SF and save in local cache
     */
    protected function _prepareOrderLookup()
    {
        // Salesforce lookup, find all orders by Magento order number
        $this->_cache['orderLookup'] = Mage::helper('tnw_salesforce/salesforce_data_order')->lookup($this->_cache['entitiesUpdating']);
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
            is_array($this->_cache[$lookupKey])
            && array_key_exists($_orderNumber, $this->_cache[$lookupKey])
            && property_exists($this->_cache[$lookupKey][$_orderNumber], 'Pricebook2Id')
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

    protected function _prepareEntityItemsBefore()
    {
        $failedKey    = sprintf('failed%s', $this->getManyParentEntityType());

        // only sync all products if processing real time
        if ($this->_isCron) {
            return;
        }

        // Get all products from each order and decide if all needs to me synced prior to inserting them
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (in_array($_orderNumber, $this->_cache[$failedKey])) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('%s (%s): Skipping, issues with upserting an %s!',
                        strtoupper($this->_magentoEntityName), $_orderNumber, $this->_salesforceEntityName));

                continue;
            }

            if (!$this->_checkPrepareEntityItem($_key)) {
                continue;
            }

            /** @var Mage_Sales_Model_Order $_order */
            $_order = $this->_loadEntityByCache($_key, $_orderNumber);
            foreach ($this->getItems($_order) as $_item) {
                $this->_prepareStoreId($_item);
            }
        }

        // Sync Products
        if (!empty($this->_stockItems)) {
            $this->syncProducts();
        }
    }

    protected function _checkPrepareEntityItem($_key)
    {
        return true;
    }

    /**
     * Prepare Store Id for upsert
     *
     * @param Mage_Sales_Model_Order_Item $_item
     */
    protected function _prepareStoreId($_item)
    {
        $itemId = $this->getProductIdFromCart($_item);
        $_order = $_item->getOrder();
        $_storeId = $_order->getStoreId();

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
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ INVENTORY SYNC: START ================");

        /** @var TNW_Salesforce_Helper_Salesforce_Product $manualSync */
        $manualSync = Mage::helper('tnw_salesforce/salesforce_product');

        foreach ($this->_stockItems as $_storeId => $_products) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Store Id: " . $_storeId);
            $manualSync->setOrderStoreId($_storeId);
            if ($manualSync->reset() && $manualSync->massAdd($this->_stockItems[$_storeId]) && $manualSync->process()) {
                if (!$this->isFromCLI()) {
                    Mage::getSingleton('adminhtml/session')
                        ->addSuccess(Mage::helper('adminhtml')->__('Store #' . $_storeId . ' ,Product inventory was synchronized with Salesforce'));
                }
            }
            else {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('WARNING: Salesforce Connection could not be established!');
            }
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ INVENTORY SYNC: END ================");
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
        parent::reset();

        // get all allowed order statuses from configuration
        $this->_allowedOrderStatuses = explode(',', Mage::helper('tnw_salesforce')->getAllowedOrderStates());

        // Clean order cache
        if (is_array($this->_cache['entitiesUpdating'])) {
            foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
                $this->unsetEntityCache($_orderNumber);
            }
        }

        $this->_cache = array(
            'accountsLookup' => array(),
            'productCampaignAssignment' => array(),
            'entitiesUpdating' => array(),
            sprintf('upserted%s', $this->getManyParentEntityType()) => array(),
            sprintf('failed%s', $this->getManyParentEntityType()) => array(),
            sprintf('%sToUpsert', lcfirst($this->getItemsField())) => array(),
            sprintf('%sToUpsert', strtolower($this->getManyParentEntityType())) => array(),
        );

        return $this->check();
    }
}