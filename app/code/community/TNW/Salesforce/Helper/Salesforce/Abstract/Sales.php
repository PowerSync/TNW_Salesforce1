<?php

abstract class TNW_Salesforce_Helper_Salesforce_Abstract_Sales extends TNW_Salesforce_Helper_Salesforce_Abstract_Base
{
    const ITEM_FEE_CHECK = '__tnw_fee_check';

    /**
     * @var array
     */
    protected $_availableFees = array();

    /**
     * @comment magento entity item qty field name
     * @var array
     */
    protected $_itemQtyField = 'qty';

    /**
     * @var int
     */
    protected $_guestCount = 0;

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
     * @return array
     */
    public function getAvailableFees()
    {
        return $this->_availableFees;
    }

    /**
     * @param array $availableFees
     * @return $this
     */
    public function setAvailableFees($availableFees)
    {
        $this->_availableFees = $availableFees;
        return $this;
    }

    /**
     * @return string
     */
    public function getItemQtyField()
    {
        return $this->_itemQtyField;
    }

    /**
     * @param array $itemQtyField
     * @return $this
     */
    public function setItemQtyField($itemQtyField)
    {
        $this->_itemQtyField = $itemQtyField;
        return $this;
    }

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
     * @param array $_ids
     */
    protected function _massAddBefore($_ids)
    {
        $this->_guestCount = 0;
        $this->_emails = $this->_websites = array();
    }

    /**
     *
     */
    protected function _massAddAfter()
    {
        $_cacheCustomersKey = sprintf('%sCustomers', $this->_magentoEntityName);
        // Salesforce lookup, find all contacts/accounts by email address
        $this->_cache['leadsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')
            ->lookup($this->_cache[$_cacheCustomersKey]);
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')
            ->lookup($this->_cache[$_cacheCustomersKey]);
        $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')
            ->lookup($this->_cache[$_cacheCustomersKey]);

        $this->_massAddAfterLookup();

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
     *
     */
    protected function _massAddAfterLookup()
    {
        return;
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
        $customerId  = $this->_cache[sprintf('%sToCustomerId', $this->_magentoEntityName)][$_entityNumber];
        $email       = $this->_cache[sprintf('%sToEmail', $this->_magentoEntityName)][$_entityNumber];
        $websiteSfId = $this->_websites[$customerId];

        return
            !isset($this->_cache['contactsLookup'][$websiteSfId][$email]) ||
            !isset($this->_cache['accountsLookup'][0][$email]) ||
            (isset($this->_cache['leadsLookup'][$websiteSfId][$email]) && !$this->_cache['leadsLookup'][$websiteSfId][$email]->IsConverted);
    }

    /**
     * @comment return entity items
     * @param $_entity Mage_Sales_Model_Abstract
     * @return mixed
     * @throws Exception
     */
    public function getItems($_entity)
    {
        return $this->getFeeItems($_entity);
    }

    /**
     * @param $_entity Mage_Sales_Model_Abstract
     * @return mixed
     */
    public function getFeeItems($_entity)
    {
        $entityNumber = $this->_getEntityNumber($_entity);
        if (isset($this->_cache['fee_entity_items'][$entityNumber])) {
            return $this->_cache['fee_entity_items'][$entityNumber];
        }

        /** @var TNW_Salesforce_Helper_Data $_helper */
        $_helper = Mage::helper('tnw_salesforce');
        foreach ($this->getAvailableFees() as $feeName) {
            $ucFee = ucfirst($feeName);

            // Push Fee As Product
            if (!call_user_func(array($_helper, sprintf('use%sFeeProduct', $ucFee))) || $_entity->getData($feeName . '_amount') == 0) {
                continue;
            }

            if (!call_user_func(array($_helper, sprintf('get%sProduct', $ucFee)))) {
                continue;
            }

            $feeData = Mage::getStoreConfig($_helper->getFeeProduct($feeName), $_entity->getStoreId());
            if (!$feeData) {
                continue;
            }

            $feeData = @unserialize($feeData);
            if (empty($feeData)) {
                continue;
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Add $feeName");
            $this->_cache['fee_entity_items'][$entityNumber][]
                = $this->generateFeeEntityItem($_entity, $feeName, $feeData)->setData(self::ITEM_FEE_CHECK, true);
        }

        return $this->_cache['fee_entity_items'][$entityNumber];
    }

    /**
     * @param $entityItem
     * @return mixed
     */
    public function isFeeEntityItem($entityItem)
    {
        return (bool)$entityItem->getData(self::ITEM_FEE_CHECK);
    }

    /**
     * @param Mage_Sales_Model_Order $_entity
     * @param string $feeName
     * @param array $feeData
     * @return Mage_Sales_Model_Order_Item
     */
    protected function generateFeeEntityItem($_entity, $feeName, $feeData)
    {
        return Mage::getModel('sales/order_item')
            ->setOrder($_entity)
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
     * @param $_entity
     * @param $item Varien_Object
     */
    protected function _prepareAdditionalFees($_entity, $item)
    {
        return;
    }

    /**
     * Prepare Order items object(s) for upsert
     */
    protected function _prepareEntityItems()
    {


        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf('----------Prepare %s items: Start----------', $this->_magentoEntityName));

        $syncProduct = $prepareEntity = array();
        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $_key => $_entityNumber) {
            if (in_array($_entityNumber, $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())])) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('%s (%s): Skipping, issues with upserting an %s!',
                        strtoupper($this->_magentoEntityName), $_entityNumber, $this->_salesforceEntityName));

                continue;
            }

            if (!$this->_checkPrepareEntityItem($_key)) {
                continue;
            }

            $_entity = $this->getEntityCache($_entityNumber);
            foreach ($this->getItems($_entity) as $_entityItem) {
                $product = $this->_getObjectByEntityItemType($_entityItem, 'Product');
                if (!$product instanceof Mage_Catalog_Model_Product) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveTrace(sprintf('%s Item (Name: "%s") skipping: product not exists anymore',
                            ucfirst($this->_magentoEntityName), $_entityItem->getName()));

                    continue 2;
                }

                // only sync product if processing real time
                if (!is_null($product->getId()) && $this->_isCron) {
                    continue;
                }

                $syncProduct[] = $product;
            }

            $prepareEntity[$_key] = $_entityNumber;
        }

        // Sync Products
        $this->syncProducts($syncProduct);

        //Prepare entity items
        foreach ($prepareEntity as $_key => $_entityNumber) {
            $_entity = $this->getEntityCache($_entityNumber);
            foreach ($this->getItems($_entity) as $_entityItem) {
                if ($this->isFeeEntityItem($_entityItem)) {
                    $this->_prepareAdditionalFees($_entity, $_entityItem);
                }

                $this->_prepareEntityItemObj($_entity, $_entityItem);
            }
        }

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf('----------Prepare %s items: End----------', $this->_magentoEntityName));
    }

    /**
     * Mass sync products that are part of the order
     * @param Mage_Catalog_Model_Product[] $products
     */
    protected function syncProducts($products)
    {
        if (empty($products)) {
            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ INVENTORY SYNC: START ================");

        /** @var TNW_Salesforce_Helper_Salesforce_Product $manualSync */
        $manualSync = Mage::helper('tnw_salesforce/salesforce_product');
        if ($manualSync->reset() && $manualSync->forceAdd($products) && $manualSync->process()) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('The products have been synchronized with Salesforce');
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ INVENTORY SYNC: END ================");
    }

    /**
     * @param $_entity
     * @return string
     */
    public function getCurrencyCode($_entity)
    {
        $currencyCodeField = $this->getMagentoEntityName() . '_currency_code';
        $_currencyCode = '';

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {

            /**
             * this condition used for invoice sync
             */
            if (!$_entity->hasData($currencyCodeField)) {
                $currencyCodeField = 'order_currency_code';
            }
            $_currencyCode = $_entity->getData($currencyCodeField);
        }

        return $_currencyCode;
    }

    /**
     * @comment returns item qty
     * @param $item Varien_Object
     * @return mixed
     */
    public function getItemQty($item)
    {
        return $item->getData($this->getItemQtyField());
    }

    /**
     * @param $entityItem Mage_Sales_Model_Order_Item
     * @param $fieldName
     * @return null
     */
    public function getFieldFromEntityItem($entityItem, $fieldName)
    {
        $field = null;
        switch ($entityItem->getProductType()) {
            case Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE:
                $children = $entityItem->getChildrenItems();
                if (empty($children)) {
                    $productId = null;
                    break;
                }

                $field = reset($children)->getData($fieldName);
                break;

            default:
                $field = $entityItem->getData($fieldName);
                break;
        }

        return $field;
    }

    /**
     * @param $_item Mage_Sales_Model_Order_Item
     * @return int
     * Get product Id from the cart
     */
    public function getProductIdFromCart($_item)
    {
        return $this->getFieldFromEntityItem($_item, 'product_id');
    }

    /**
     * @param $_entity
     * @return null|string
     */
    protected function _getPricebookIdToEntity($_entity)
    {
        /** @var TNW_Salesforce_Helper_Data $_helper */
        $_helper = Mage::helper('tnw_salesforce');
        return Mage::getStoreConfig($_helper::PRODUCT_PRICEBOOK, $_entity->getStoreId());
    }

    /**
     * @param array $customerData
     * @return Mage_Customer_Model_Customer
     */
    protected function _generateCustomer(array $customerData)
    {
        $customerData = array_merge(array(
            'customer_id'       => null,
            'store_id'          => null,
            'customer_email'    => null,
            'first_name'        => null,
            'last_name'         => null,
            'created_at'        => null,
            'billing_address'   => null,
            'shipping_address'  => null,
        ), $customerData);

        $_customer = Mage::getModel("customer/customer");

        if ($customerData['customer_id']) {
            if (Mage::helper('tnw_salesforce')->getMagentoVersion() < 1500) {
                $sql = "SELECT website_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('customer_entity') . "` WHERE entity_id = '" . $customerData['customer_id'] . "'";
                $row = Mage::helper('tnw_salesforce')->getDbConnection()->query($sql)->fetch();
                if (!$row) {
                    $_customer->setWebsiteId($row['website_id']);
                }
            }

            $_customer->load($customerData['customer_id']);
        }
        else {
            $_websiteId = Mage::app()->getStore($customerData['store_id'])->getWebsiteId();
            if ($_customer->getSharingConfig()->isWebsiteScope()) {
                $_customer->setWebsiteId($_websiteId);
            }

            $_email = strtolower($customerData['customer_email']);
            $_customer->loadByEmail($_email);

            if (!$_customer->getId()) {
                $_customer->setGroupId(0); // NOT LOGGED IN
                $_customer->setFirstname($customerData['first_name']);
                $_customer->setLastname($customerData['last_name']);
                $_customer->setEmail($_email);
                $_customer->setStoreId($customerData['store_id']);
                if (isset($_websiteId)) {
                    $_customer->setWebsiteId($_websiteId);
                }

                $_customer->setCreatedAt(gmdate(DATE_ATOM, strtotime($customerData['created_at'])));
            }
        }

        if (
            !$_customer->getDefaultBillingAddress()
            && is_array($customerData['billing_address'])
        ) {
            $_billingAddress = Mage::getModel('customer/address');
            $_billingAddress->setCustomerId(0)
                ->setIsDefaultBilling('1')
                ->setSaveInAddressBook('0')
                ->addData($customerData['billing_address']);
            $_customer->setBillingAddress($_billingAddress);
        }

        if (
            !$_customer->getDefaultShippingAddress()
            && is_array($customerData['shipping_address'])
        ) {
            $_shippingAddress = Mage::getModel('customer/address');
            $_shippingAddress->setCustomerId(0)
                ->setIsDefaultShipping('1')
                ->setSaveInAddressBook('0')
                ->addData($customerData['shipping_address']);
            $_customer->setShippingAddress($_shippingAddress);
        }

        $_websiteId = Mage::app()->getStore($customerData['store_id'])->getWebsiteId();
        if ($_customer->getSharingConfig()->isWebsiteScope()) {
            $_customer->setWebsiteId($_websiteId);
        }

        // Set Company Name
        if (!$_customer->getData('company') && isset($customerData['billing_address']['company'])) {
            $_customer->setData('company', $customerData['billing_address']['company']);
        }

        return $_customer;
    }

    /**
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $order
     * @return Mage_Customer_Model_Customer
     */
    protected function _generateCustomerByOrder($order)
    {
        $customer = $this->_generateCustomer(array(
            'customer_id'       => $order->getCustomerId(),
            'store_id'          => $order->getStoreId(),
            'customer_email'    => $order->getCustomerEmail(),
            'first_name'        => $order->getBillingAddress()->getFirstname(),
            'last_name'         => $order->getBillingAddress()->getLastname(),
            'created_at'        => $order->getCreatedAt(),
            'billing_address'   => $order->getBillingAddress()->getData(),
            'shipping_address'  => ($order->getShippingAddress())? $order->getShippingAddress()->getData(): array(),
        ));

        if ($customer->getId() && !$order->getCustomerId()) {
            $sql = '';
            //UPDATE order to record Customer Id
            if ($order->getResource()->getMainTable()) {
                $sql .= "UPDATE `" . $order->getResource()->getMainTable() . "` SET customer_id = " . $customer->getId() . " WHERE entity_id = " . $order->getId() . ";";
            }

            if ($order->getResource()->getGridTable()) {
                $sql .= "UPDATE `" . $order->getResource()->getGridTable() . "` SET customer_id = " . $customer->getId() . " WHERE entity_id = " . $order->getId() . ";";
            }

            if ($order->getAddressesCollection()->getMainTable()) {
                $sql .= "UPDATE `" . $order->getAddressesCollection()->getMainTable() . "` SET customer_id = " . $customer->getId() . " WHERE parent_id = " . $order->getId() . ";";
            }

            Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Guest user found in Magento, updating order #' . $order->getId() . ' attaching cusomter ID: ' . $customer->getId());
        }

        return $customer;
    }

    /**
     * @param $entityItem
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
                    'price'     => $entityItem->getBaseOriginalPrice(),
                    'type_id'   => $entityItem->getProductType(),
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
     * @param $entityItem
     * @return null|string
     */
    protected function searchSkuByEntityItem($entityItem)
    {
        if ($this->isFeeEntityItem($entityItem)) {
            return $entityItem->getSku();
        }

        // Load by product Id only if bundled OR simple with options
        $_productId  = $this->getProductIdFromCart($entityItem);
        if (is_numeric($_productId)) {
            $productsSku = Mage::getResourceModel('catalog/product')->getProductsSku(array($_productId));
            if (!empty($productsSku[0]['sku'])) {
                return $productsSku[0]['sku'];
            }
        }

        $sku = $this->searchSkuByEntityItemInLookup($entityItem);
        if (!empty($sku)) {
            return $sku;
        }

        return $this->getFieldFromEntityItem($entityItem, 'sku');
    }

    /**
     * @param $entityItem
     * @return null|string
     */
    protected function searchSkuByEntityItemInLookup($entityItem)
    {
        // Search SKU by Lookup
        $entity       = $this->getEntityByItem($entityItem);
        $entityNumber = $this->_getEntityNumber($entity);
        $lookupKey    = sprintf('%sLookup', $this->_salesforceEntityName);
        $records      = isset($this->_cache[$lookupKey][$entityNumber]->{$this->getItemsField()})
            ? $this->_cache[$lookupKey][$entityNumber]->{$this->getItemsField()}->records : array();

        foreach ($records as $_cartItem) {
            if ($_cartItem->Id != $entityItem->getData('salesforce_id')) {
                continue;
            }

            return $_cartItem->PricebookEntry->ProductCode;
        }

        return null;
    }

    /**
     * @return bool|void
     * Prepare values for the synchroization
     */
    public function reset()
    {
        parent::reset();

        // Clean order cache
        if (is_array($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING])) {
            foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $_orderNumber) {
                $this->unsetEntityCache($_orderNumber);
            }
        }

        $this->_cache = array(
            'leadsLookup' => array(),
            'contactsLookup' => array(),
            'accountsLookup' => array(),
            'products' => array(),
            self::CACHE_KEY_ENTITIES_UPDATING => array(),
            sprintf('upserted%s', $this->getManyParentEntityType()) => array(),
            sprintf('failed%s', $this->getManyParentEntityType()) => array(),
            sprintf('%sToUpsert', lcfirst($this->getItemsField())) => array(),
            sprintf('%sToUpsert', strtolower($this->getManyParentEntityType())) => array(),
        );

        return $this->check();
    }
}