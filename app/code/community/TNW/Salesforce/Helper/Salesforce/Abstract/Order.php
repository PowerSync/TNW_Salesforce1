<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Abstract
 */
abstract class TNW_Salesforce_Helper_Salesforce_Abstract_Order extends TNW_Salesforce_Helper_Salesforce_Abstract_Base
{
    /**
     * @var array
     */
    protected $_stockItems = array();

    /**
     * @var array
     */
    protected $_alternativeKeys = array();

    /**
     * @comment magento entity alias "convert from"
     * @var string
     */
    protected $_magentoEntityName = '';

    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = '';

    /**
     * @comment cache keys
     * @var string
     */
    protected $_ucParentEntityType = '';
    protected $_manyParentEntityType = '';
    protected $_itemsField = '';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityModel = '';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityId = 'entity_id';

    /**
     * @comment magento entity item qty field name
     * @var array
     */
    protected $_itemQtyField = 'qty';

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
     * @comment salesforce field name to assign parent entity
     * @var string
     */
    protected $_salesforceParentIdField = '';

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
     * @return string
     */
    public function getSalesforceEntityName()
    {
        return $this->_salesforceEntityName;
    }

    /**
     * @param string $salesforceEntityName
     * @return $this
     */
    public function setSalesforceEntityName($salesforceEntityName)
    {
        $this->_salesforceEntityName = $salesforceEntityName;
        return $this;
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
    public function getSalesforceParentIdField()
    {
        return $this->_salesforceParentIdField;
    }

    /**
     * @param string $salesforceParentIdField
     * @return $this
     */
    public function setSalesforceParentIdField($salesforceParentIdField)
    {
        $this->_salesforceParentIdField = $salesforceParentIdField;
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
     * @return array
     */
    public function getMagentoEntityModel()
    {
        return $this->_magentoEntityModel;
    }

    /**
     * @param array $magentoEntityModel
     * @return $this
     */
    public function setMagentoEntityModel($magentoEntityModel)
    {
        $this->_magentoEntityModel = $magentoEntityModel;
        return $this;
    }

    /**
     * @return array
     */
    public function getMagentoEntityName()
    {
        return $this->_magentoEntityName;
    }

    /**
     * @param array $magentoEntityName
     * @return $this
     */
    public function setMagentoEntityName($magentoEntityName)
    {
        $this->_magentoEntityName = $magentoEntityName;
        return $this;
    }

    /**
     * @param $parentEntityNumber
     * @param $qty
     * @param $productIdentifier
     * @param $description
     *
     * @return bool
     */
    protected function _doesCartItemExist($parentEntityNumber, $qty, $productIdentifier, $description = 'default', $item = null)
    {
        $_cartItemFound = false;

        /**
         * @TODO: add magentoId field for SF Order/Opportunity/etc and define matching by this field
         * other fields are not unique and can occur again for different items
         */
        $magentoIdField = $this->getMagentoIdField();

        /**
         * @var $parentEntityCacheKey string  opportunityLookup|$parentEntityCacheKey
         */
        $parentEntityCacheKey = $this->_salesforceEntityName . 'Lookup';

        if (
            $this->_cache[$parentEntityCacheKey]
            && array_key_exists($parentEntityNumber, $this->_cache[$parentEntityCacheKey])
            && $this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$this->_itemsField}
        ) {
            foreach ($this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$this->_itemsField}->records as $_cartItem) {
                if (
                    (
                        $_cartItem->PricebookEntryId == $productIdentifier
                        || ($magentoIdField && $_cartItem->{$magentoIdField} == $productIdentifier)

                        || (property_exists($_cartItem, 'PricebookEntry')
                            && property_exists($_cartItem->PricebookEntry, 'ProductCode')
                            && ($_cartItem->PricebookEntry->ProductCode == trim($productIdentifier))
                            && ($description === 'default' || !$description || (property_exists($_cartItem, 'Description') && $_cartItem->Description == $description))
                        )
                    )
                    && $_cartItem->Quantity == (float)$qty
                ) {

                    /**
                     * if SF object has not magentoId field - try to use alternative matching
                     */
                    if (!$magentoIdField
                        || empty($_cartItem->{$magentoIdField})
                    ) {

                        /**
                         * if current SF item already assigned to some Magento item - skip it and try to find one more
                         * sometimes items with the same parameters can be in order - we should divide it
                         */
                        foreach ($this->_cache[lcfirst($this->getItemsField()) . 'ToUpsert'] as $itemToUpsert) {
                            if (property_exists($itemToUpsert, 'Id')
                                && !empty($itemToUpsert->Id)
                                && $_cartItem->Id == $itemToUpsert->Id
                            ) {
                                continue 2;
                            }
                        }

                        $_cartItemFound = $_cartItem->Id;
                        break;
                    } elseif ($_cartItem->{$magentoIdField} == $productIdentifier) {
                        $_cartItemFound = $_cartItem->Id;
                        break;
                    }

                }
            }
        }
        return $_cartItemFound;
    }

    /**
     * @return array
     */
    public function getAlternativeKeys()
    {
        return $this->_alternativeKeys;
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
     * @param $_item
     * @return int
     * Get product Id from the cart
     */
    public function getProductIdFromCart($_item)
    {
        $_options = unserialize($_item->getData('product_options'));
        if (
            $_item->getData('product_type') == 'bundle'
            || (is_array($_options) && array_key_exists('options', $_options))
        ) {
            $id = $_item->getData('product_id');
        } else {
            $id = (int)Mage::getModel('catalog/product')->getIdBySku($_item->getSku());
        }
        return $id;
    }

    /**
     * @param Mage_Sales_Model_Order $_entity
     * @return array
     */
    public function getProductIdsFromEntity($_entity)
    {
        $_productIds = array();
        /** @var Mage_Sales_Model_Order_Item $_item */
        foreach ($_entity->getAllVisibleItems() as $_item) {
            if ($_item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE
                && Mage::getStoreConfig(TNW_Salesforce_Helper_Config_Sales::XML_PATH_ORDERS_BUNDLE_ITEM_SYNC)
            ) {
                $_productIds = array_merge(
                    $_productIds,
                    $this->_getChildProductIdsFromCart($_item)
                );

                continue;
            }

            $_productIds[] = (int) $this->getProductIdFromCart($_item);
        }

        return $_productIds;
    }

    /**
     * Get child product ids
     *
     * @param Mage_Sales_Model_Order_Item $_item
     * @return array
     */
    protected function _getChildProductIdsFromCart($_item) {
        $Ids = array();
        $productId = $_item->getItemId();
        $Ids[] = (int) $_item->getProductId();

        foreach ($_item->getOrder()->getAllItems() as $_itemProduct) {
            if ($_itemProduct->getParentItemId() == $productId) {
                $Ids[] = (int) $_itemProduct->getProductId();
            }
        }
        return $Ids;
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
     * @param $parentEntityNumber
     * @return Mage_Core_Model_Abstract|mixed
     */
    public function getSalesforceParentEntity($parentEntityNumber)
    {
        return $this->_cache[strtolower($this->getManyParentEntityType()) . 'ToUpsert'][$parentEntityNumber];
    }

    /**
     * @comment returns item qty
     * @param $item
     * @return mixed
     */
    public function getItemQty($item)
    {
        $qty = $item->getData($this->getItemQtyField());

        return $qty;
    }

    /**
     * @comment return parent entity items
     * @param $parentEntity Mage_Sales_Model_Quote|Mage_Sales_Model_Order
     * @return mixed
     */
    public function getItems($parentEntity)
    {
        return $parentEntity->getAllVisibleItems();
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

        /**
         * @comment prepare formatted price
         */
        return $this->numberFormat($netTotal);
    }

    /**
     * @comment Prepare order items for Salesforce
     * @throws Exception
     */
    protected function _prepareOrderItem($parentEntityNumber)
    {

        $parentEntity = $this->getParentEntity($parentEntityNumber);

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('******** ' . strtoupper($this->getMagentoEntityName()) . ' (' . $parentEntityNumber . ') ********');

        /**
         * @comment prepare products for Salesforce order/opportunity
         * @var $_item Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item
         */
        foreach ($this->getItems($parentEntity) as $_item) {

            try {
                $this->_prepareItemObj($parentEntity, $parentEntityNumber, $_item);
            } catch (Exception $e) {

                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addNotice($e->getMessage());
                }
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError($e->getMessage());
                continue;
            }

        }

        $this->_applyAdditionalFees($parentEntity, $parentEntityNumber);
    }

    /**
     * @comment add Tax/Shipping/Discount to the order as different product
     * @param $parentEntity
     * @param $parentEntityNumber
     */
    protected function _applyAdditionalFees($parentEntity, $parentEntityNumber)
    {
        $_helper = Mage::helper('tnw_salesforce');

        foreach ($this->getAvailableFees() as $feeName) {
            $ucFee = ucfirst($feeName);

            $configMethod = 'use' . $ucFee . 'FeeProduct';
            // Push Fee As Product
            if (Mage::helper('tnw_salesforce')->$configMethod() && $parentEntity->getData($feeName . '_amount') != 0) {

                $getProductMethod = 'get' . $ucFee . 'Product';

                if (Mage::helper('tnw_salesforce')->$getProductMethod()) {

                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Add $feeName");

                    $qty = 1;

                    $item = new Varien_Object();

                    $feeData = Mage::app()->getStore($parentEntity->getStoreId())->getConfig($_helper->getFeeProduct($feeName));
                    if ($feeData) {
                        $feeData = unserialize($feeData);
                    } else {
                        continue;
                    }

                    $item->setData($feeData);

                    /**
                     * add data in lower case too compatibility for
                     */
                    foreach ($feeData as $key => $value) {
                        $key = strtolower($key);
                        $item->setData($key, $value);
                    }

                    $item->setData($this->getItemQtyField(), $qty);

                    $item->setDescription($_helper->__($ucFee));

                    $item->setRowTotalInclTax($this->getEntityPrice($parentEntity, $ucFee . 'Amount'));
                    $item->setRowTotal($this->getEntityPrice($parentEntity, $ucFee . 'Amount'));

                    $this->_prepareItemObj($parentEntity, $parentEntityNumber, $item);

                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL ERROR: $feeName product is not configured!");
                }
            }
        }
    }

    /**
     * @return string
     */
    public function getUcParentEntityType()
    {
        if (!$this->_ucParentEntityType) {
            /**
             * @comment first letter in upper case
             */
            $this->_ucParentEntityType = ucfirst($this->_salesforceEntityName);
        }

        return $this->_ucParentEntityType;
    }

    /**
     * @param string $ucParentEntityType
     * @return $this
     */
    public function setUcParentEntityType($ucParentEntityType)
    {
        $this->_ucParentEntityType = $ucParentEntityType;
        return $this;
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
     * @param string $manyParentEntityType
     * @return $this
     */
    public function setManyParentEntityType($manyParentEntityType)
    {
        $this->_manyParentEntityType = $manyParentEntityType;
        return $this;
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

    /**
     * @param string $itemsField
     * @return $this
     */
    public function setItemsField($itemsField)
    {
        $this->_itemsField = $itemsField;
        return $this;
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
            $_email = strtolower($order->getCustomerEmail());
            $_customer->loadByEmail($_email);

            if (!$_customer->getId()) {
                //Guest
                $_customer = Mage::getModel("customer/customer");
                $_customer->setGroupId(0); // NOT LOGGED IN
                $_customer->setFirstname($order->getBillingAddress()->getFirstname());
                $_customer->setLastname($order->getBillingAddress()->getLastname());
                $_customer->setEmail($_email);
                $_customer->setStoreId($_storeId);
                if (isset($_websiteId)) {
                    $_customer->setWebsiteId($_websiteId);
                }

                $_customer->setCreatedAt(gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($order->getCreatedAt()))));
                //TODO: Extract as much as we can from the order

            } else {

                $sql = '';
                //UPDATE order to record Customer Id
                if ($order->getResource()->getMainTable()) {

                    $sql = "UPDATE `" . $order->getResource()->getMainTable() . "` SET customer_id = " . $_customer->getId() . " WHERE entity_id = " . $order->getId() . ";";
                }

                if ($order->getResource()->getGridTable()) {
                    $sql .= "UPDATE `" . $order->getResource()->getGridTable() . "` SET customer_id = " . $_customer->getId() . " WHERE entity_id = " . $order->getId() . ";";
                }

                if ($order->getAddressesCollection()->getMainTable()) {
                    $sql .= "UPDATE `" . $order->getAddressesCollection()->getMainTable() . "` SET customer_id = " . $_customer->getId() . " WHERE parent_id = " . $order->getId() . ";";
                }
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
                Mage::helper("tnw_salesforce")->log('Guest user found in Magento, updating order #' . $order->getId() . ' attaching cusomter ID: ' . $_customer->getId());
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

        $_websiteId = Mage::getModel('core/store')->load($order->getStoreId())->getWebsiteId();
        if ($_customer->getSharingConfig()->isWebsiteScope()) {
            $_customer->setWebsiteId($_websiteId);
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
     * @comment prepare order item object (product, tax, shipping, discount) for Salesforce
     * @param $parentEntity
     * @param $parentEntityNumber
     * @param $item
     * @throws Exception
     */
    protected function _prepareItemObj($parentEntity, $parentEntityNumber, $item)
    {
        $this->_prepareTechnicalPrefixes();

        $this->_obj = new stdClass();

        $this->_prepareItemObjStart();

        $_currencyCode = $this->getCurrencyCode($parentEntity);

        $qty = $this->getItemQty($item);

        if ($qty === NULL) {
            $qty = 0;
        }
        if ($qty == 0) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("NOTE: Product w/ SKU (" . $item->getSku() . ") is not synchronized, ordered quantity is zero!");
        }

        $storeId = $parentEntity->getStoreId();

        // Load by product Id only if bundled OR simple with options
        $id = $this->getProductIdFromCart($item);

        /**
         * @comment do some additional actions if we add real product to the order
         */
        if ($id) {
            /**
             * @var $productModel Mage_Catalog_Model_Product
             */
            $productModel = Mage::getModel('catalog/product')->setStoreId($storeId);
            $product = $productModel->load($id);

            $sku = ($product->getSku()) ? $product->getSku() : $item->getSku();

            $pricebookEntryId = $product->getSalesforcePricebookId();

            if (!empty($pricebookEntryId)) {
                $valuesArray = explode("\n", $pricebookEntryId);

                $pricebookEntryId = '';

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

            if (!$pricebookEntryId) {

                throw new Exception("NOTICE: Product w/ SKU (" . $sku . ") is not synchronized, could not add to $this->_salesforceEntityName!");
            }

            $this->_pricebookEntryId = $pricebookEntryId;

            /**
             * @var $mapping TNW_Salesforce_Model_Sync_Mapping_Order_Base_Item
             */
            $mapping = Mage::getSingleton($this->getModulePrefix() . '/sync_mapping_' . $this->getMagentoEntityName() . '_' . $this->_salesforceEntityName . '_item');

            $mapping->setSync($this)
                ->processMapping($item, $product);

            $identifier = $sku;
            /**
             * if MagentoId field exists - the orderItemId should be identifier
             */
            if ($this->getMagentoIdField() && $item->getId()) {
                $identifier = $item->getId();
            }

            if ($item->getBundleItemToSync()) {
                $this->_obj->Description = $item->getBundleItemToSync();
            }

            /**
             * use_product_campaign_assignment
             */
            if (
                Mage::helper('tnw_salesforce/config_sales')->useProductCampaignAssignment()
                && $parentEntity instanceof Mage_Sales_Model_Order
                && $product->getSalesforceCampaignId()
            ) {
                $contactId = $this->_cache['orderCustomers'][$parentEntity->getRealOrderId()]->getSalesforceId();

                Mage::helper('tnw_salesforce/salesforce_newslettersubscriber')
                    ->prepareCampaignMemberItem('ContactId', $contactId, null, $product->getSalesforceCampaignId());
            }

        } else {
            $product = new Varien_Object();
            $sku = $item->getData('ProductCode');

            $pricebookId = $this->_getPricebookIdToOrder($parentEntity);

            $pricebookEntry = Mage::helper('tnw_salesforce/salesforce_data_product')->getProductPricebookEntry($item->getData('Id'), $pricebookId, $_currencyCode);

            if (!$pricebookEntry || !isset($pricebookEntry['Id'])) {
                throw new Exception("NOTICE: Product w/ SKU (" . $sku . ") is not synchronized, could not add to $this->_salesforceEntityName!");
            }
            $pricebookEntryId = $pricebookEntry['Id'];

            $product->setSalesforceId($pricebookEntryId);
            $this->_pricebookEntryId = $pricebookEntryId;

            // Used for custom object integration and overrides
            $this->_updatePreparedObjectInfo($item);

            $identifier = $this->_pricebookEntryId;
            $this->_obj->Description = $item->getDescription();
            $id = $item->getData('Id');
        }

        $this->_obj->{$this->getSalesforceParentIdField()} = $this->_getParentEntityId($parentEntityNumber);

        $this->_obj->UnitPrice = $this->_prepareItemPrice($item, $qty);

        $opt = array();
        $options = (is_array($item->getData('product_options'))) ? $item->getData('product_options') : @unserialize($item->getData('product_options'));

        $_summary = array();
        if (
            is_array($options)
            && array_key_exists('options', $options)
        ) {
            $_prefix = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th></tr></thead><tbody>';
            foreach ($options['options'] as $_option) {
                $optionValue = '';
                if(isset($_option['print_value'])) {
                    $optionValue = $_option['print_value'];
                } elseif (isset($_option['value'])) {
                    $optionValue = $_option['value'];
                }

                $opt[] = '<tr><td align="left">' . $_option['label'] . '</td><td align="left">' . $optionValue . '</td></tr>';
                $_summary[] = $optionValue;
            }
        }
        if (
            is_array($options)
            && $item->getData('product_type') == 'bundle'
            && array_key_exists('bundle_options', $options)
        ) {
            $_prefix = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th><th>Qty</th><th align="left">Fee<th></tr><tbody>';
            foreach ($options['bundle_options'] as $_option) {
                $_string = '<td align="left">' . $_option['label'] . '</td>';
                if (is_array($_option['value'])) {
                    $_tmp = array();
                    foreach ($_option['value'] as $_value) {
                        $_tmp[] = '<td align="left">' . $_value['title'] . '</td><td align="center">' . $_value['qty'] . '</td><td align="left">' . $_currencyCode . ' ' . $this->numberFormat($_value['price']) . '</td>';
                        $_summary[] = $_value['title'];
                    }
                    if (count($_tmp) > 0) {
                        $_string .= join(", ", $_tmp);
                    }
                }

                $opt[] = '<tr>' . $_string . '</tr>';
            }
        }

        if (
            is_array($options)
            && $item->getData('product_type') == 'configurable'
            && array_key_exists('attributes_info', $options)
        ) {
            $_prefix = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th></tr><tbody>';
            foreach ($options['attributes_info'] as $_option) {
                $_string = '<td align="left">' . $_option['label'] . '</td>';
                $_string .= '<td align="left">' . $_option['value'] . '</td>';
                $_summary[] = $_option['value'];
                $opt[] = '<tr>' . $_string . '</tr>';
            }
        }

        if (count($opt) > 0) {
            $syncParam = $this->_getSalesforcePrefix() . "Product_Options__c";
            $this->_obj->$syncParam = $_prefix . join("", $opt) . '</tbody></table>';

            $this->_obj->Description = join(", ", $_summary);
            if (strlen($this->_obj->Description) > 200) {
                $this->_obj->Description = substr($this->_obj->Description, 0, 200) . '...';
            }
        }

        /**
         * @comment try to fined item in lookup array. Search prodyct by the sku or tax/shipping/discount by the SalesforcePricebookId
         * @TODO: check, may be it sould be better search product by SalesforcePricebookId too
         */
        $description = $item->getBundleItemToSync();
        if (!$description && property_exists($this->_obj, 'Description') && empty($this->_obj->Description)) {
            $description = $this->_obj->Description;
        }

        $cartItemFound = $this->_doesCartItemExist($parentEntityNumber, $qty, $identifier, $description, $item);
        if ($cartItemFound) {
            $this->_obj->Id = $cartItemFound;
        } else {
            $this->_obj->PricebookEntryId = $pricebookEntryId;
        }

        $this->_obj->Quantity = $qty;

        $this->_prepareItemObjFinish($item, $product);

        $this->_cache[lcfirst($this->getItemsField()) . 'ProductsToSync'][$this->_getParentEntityId($parentEntityNumber)] = array();

        if ($this->isItemObjectValid()) {
            /* Dump OpportunityLineItem object into the log */
            foreach ($this->_obj as $key => $_item) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Opportunity/Order Item Object: " . $key . " = '" . $_item . "'");
            }

            $this->_cache[lcfirst($this->getItemsField()) . 'ProductsToSync'][$this->_getParentEntityId($parentEntityNumber)][] = $sku;

            $key = $item->getId();

            /**
             * if it's fake product for order fee, has the same id's for all products
             */
            if (!$product->getId()) {
                $key .= '_' . $parentEntityNumber;
            }
            $this->_cache[lcfirst($this->getItemsField()) . 'ToUpsert']['cart_' . $key] = $this->_obj;
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING: Magento product is most likely deleted or quantity is zero!');
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('-----------------');

    }

    /*
     * Should Item object be added to SF
     * aka validation to prevent errors
     */
    protected function isItemObjectValid()
    {
        return (
            (property_exists($this->_obj, 'PricebookEntryId') && $this->_obj->PricebookEntryId)
            || (property_exists($this->_obj, 'Product__c') && $this->_obj->Product__c)
            || (property_exists($this->_obj, 'Id') && $this->_obj->Id)
        ) && (
            ($this->_obj->Quantity != 0)
        )
            ;
    }

    /**
     * @comment returns prefix for some fields
     * @return mixed|null
     */
    protected function _getSalesforcePrefix()
    {
        return Mage::helper('tnw_salesforce/config')->getSalesforcePrefix();
    }

    /**
     * @comment run some code before order item preparing
     * @return $this
     */
    protected function _prepareItemObjStart()
    {
        return $this;
    }

    /**
     * @comment run some code after order item preparing
     * @return $this
     */
    protected function _prepareItemObjFinish($item = null, $product = null)
    {
        /**
         * @comment rename fields if it's necessary
         */
        $itemFieldAlias = $this->getItemFieldAlias();
        if (!empty($itemFieldAlias)) {
            foreach ($itemFieldAlias as $defaultName => $customName) {
                if (!property_exists($this->_obj, $defaultName)) {
                    continue;
                }
                if (!empty($customName)) {
                    $this->_obj->{$customName} = $this->_obj->{$defaultName};
                }

                unset($this->_obj->{$defaultName});
            }
        }

        return $this;
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
     * @param $order
     * @return null|string
     */
    protected function _getPricebookIdToOrder($order)
    {
        $pricebook2Id = null;

        try {
            $_storeId = $order->getStoreId();
            $_helper = Mage::helper('tnw_salesforce');

            $pricebook2Id = Mage::app()->getStore($_storeId)->getConfig($_helper::PRODUCT_PRICEBOOK);

        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("INFO: Could not load pricebook based on the order ID. Loading default pricebook based on current store ID.");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            if ($this->_defaultPriceBook) {
                $pricebook2Id = $this->_defaultPriceBook;
            }
        }
        return $pricebook2Id;
    }

    /**
     * @param $_order Mage_Sales_Model_Order|Mage_Sales_Model_Quote
     */
    protected function _assignPricebookToOrder($_order)
    {
        $this->_obj->Pricebook2Id = $this->_getPricebookIdToOrder($_order);
    }

    /**
     * @param $incrementId
     * @return Mage_Sales_Model_Order
     */
    public function getOrderByIncrementId($incrementId)
    {
        $order = Mage::registry('order_cached_' . $incrementId);
        // Add to cache
        if (!$order) {
            // Load order by ID
            $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
            Mage::register('order_cached_' . $incrementId, $order);
        }

        return $order;
    }

    /**
     * @param array $_ids
     * @param bool $_isCron
     * @param bool $orderStatusUpdateCustomer
     * @return bool
     */
    public function massAdd($_ids = NULL, $_isCron = false, $orderStatusUpdateCustomer = true)
    {
        $_ids           = !is_array($_ids) ? array($_ids) : $_ids;
        $this->_isCron  = $_isCron;

        if (!$_ids) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Order Id is not specified, don't know what to synchronize!");
            return false;
        }

        // test sf api connection
        /** @var TNW_Salesforce_Model_Connection $_client */
        $_client = Mage::getSingleton('tnw_salesforce/connection');
        if (!$_client->initConnection()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR on sync orders, sf api connection failed");

            return true;
        }

        try {
            $_guestCount = 0;
            $this->_skippedEntity = $_quotes = $_emails = $_websites = array();

            // Clear Order ID
            $this->resetEntity($_ids);

            foreach ($_ids as $_id) {
                // Load order by ID
                /** @var Mage_Sales_Model_Order $_order */
                $_order = $this->_loadEntityByCache($_id);

                // Order could not be loaded for some reason
                if (!$_order->getId() || !$_order->getRealOrderId()) {
                    $this->logError('WARNING: Sync for order #' . $_id . ', order could not be loaded!');
                    $this->_skippedEntity[$_order->getId()] = $_order->getId();
                    continue;
                }

                /**
                 * @comment check zero orders sync
                 */
                if (!Mage::helper('tnw_salesforce/config_sales_order')->isEnabledZeroOrderSync() && $_order->getGrandTotal() == 0) {
                    $this->logNotice('SKIPPED: Sync for order #' . $_order->getRealOrderId() . ', grand total is zero and synchronization for these order is disabled in configuration!');
                    $this->_skippedEntity[$_order->getId()] = $_order->getId();
                    continue;
                }

                if (!Mage::helper('tnw_salesforce')->syncAllOrders()
                    && !in_array($_order->getStatus(), $this->_allowedOrderStatuses)
                ) {
                    $this->logNotice('SKIPPED: Sync for order #' . $_order->getId() . ', sync for order status "' . $_order->getStatus() . '" is disabled!');
                    $this->_skippedEntity[$_order->getId()] = $_order->getId();
                    continue;
                }

                // Get Magento customer object
                $this->_cache['orderCustomers'][$_order->getRealOrderId()] = $this->_getCustomer($_order);

                // Associate order Number with a customer ID
                $_customerId = ($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId())
                    ? $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId() : sprintf('guest-%d', $_guestCount++);
                $this->_cache['orderToCustomerId'][$_order->getRealOrderId()] = $_customerId;

                // Check if customer from this group is allowed to be synchronized
                $_customerGroup = $_order->getData('customer_group_id');

                if ($_customerGroup === NULL) {
                    $_customerGroup = $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getGroupId();
                }

                if ($_customerGroup === NULL && !$this->isFromCLI()) {
                    $_customerGroup = Mage::getSingleton('customer/session')->getCustomerGroupId();
                }

                if (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($_customerGroup)) {

                    $this->logNotice("SKIPPING: Sync for customer group #" . $_customerGroup . " is disabled!");
                    $this->_skippedEntity[$_order->getId()] = $_order->getId();
                    continue;
                }

                $_emails[$_customerId] = $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getEmail();

                // Associate order Number with a customer Email
                $this->_cache['orderToEmail'][$_order->getRealOrderId()] = $_emails[$_customerId];

                // Store order number and customer Email into a variable for future use
                $_orderEmail = $this->_cache['orderToEmail'][$_order->getRealOrderId()];

                $_orderNumber = $_order->getRealOrderId();

                if (empty($_orderEmail)) {
                    $this->logError('SKIPPED: Sync for order #' . $_orderNumber . ' failed, order is missing an email address!');
                    $this->_skippedEntity[$_order->getId()] = $_order->getId();
                    continue;
                }

                $_websiteId = Mage::getModel('core/store')->load($_order->getData('store_id'))->getWebsiteId();
                $_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];
                if ($_order->getQuoteId()) {
                    $_quotes[] = $_order->getQuoteId();
                }
                // Associate order ID with order Number
                $this->_cache['entitiesUpdating'][$_id] = $_orderNumber;

            }

            if (empty($this->_cache['entitiesUpdating'])) {
                return false;
            }

            $this->_findAbandonedCart($_quotes);

            $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emails, $_websites);
            $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($_emails, $_websites);
            $this->_cache['leadsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($_emails, $_websites);

            $this->_prepareOrderLookup();

            /**
             * Order customers sync can be denied if we just update order status
             */
            if ($orderStatusUpdateCustomer) {
                $this->syncOrderCustomers($_emails, $_websites);
            }

            /**
             * define Salesforce data for order customers
             */
            foreach ($this->_cache['entitiesUpdating'] as $id => $_orderNumber) {

                $_orderEmail = strtolower($this->_cache['orderToEmail'][$_orderNumber]);

                if (isset($this->_cache['orderCustomers'][$_orderNumber])
                    && is_object($this->_cache['orderCustomers'][$_orderNumber])
                    && !empty($this->_cache['accountsLookup'][0][$_orderEmail])
                ) {

                    $_websiteId = $this->_cache['orderCustomers'][$_orderNumber]->getData('website_id');

                    $this->_cache['orderCustomers'][$_orderNumber]->setData('salesforce_id', $this->_cache['accountsLookup'][0][$_orderEmail]->Id);
                    $this->_cache['orderCustomers'][$_orderNumber]->setData('salesforce_account_id', $this->_cache['accountsLookup'][0][$_orderEmail]->Id);

                    // Overwrite Contact Id for Person Account
                    if (property_exists($this->_cache['accountsLookup'][0][$_orderEmail], 'PersonContactId')) {
                        $this->_cache['orderCustomers'][$_orderNumber]->setData('salesforce_id', $this->_cache['accountsLookup'][0][$_orderEmail]->PersonContactId);
                    }

                    // Overwrite from Contact Lookup if value exists there
                    if (isset($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_orderEmail])) {
                        $this->_cache['orderCustomers'][$_orderNumber]->setData('salesforce_id', $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_orderEmail]->Id);
                    }

                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SUCCESS: Automatic customer synchronization.');

                } else {
                    /**
                     * No customers for this order in salesforce - error
                     */
                    // Something is wrong, could not create / find Magento customer in SalesForce
                    $this->logError('CRITICAL ERROR: Contact or Lead for Magento customer (' . $_orderEmail . ') could not be created / found!');
                    $this->_skippedEntity[$id] = $id;

                    continue;
                }
            }

            if (!empty($this->_skippedEntity)) {
                $chunk = array_chunk($this->_skippedEntity, TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT);

                foreach ($chunk as $_skippedOrdersChunk) {
                    $sql = "DELETE FROM `" . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . "` WHERE object_id IN ('" . join("','", $_skippedOrdersChunk) . "') and mage_object_type = 'sales/order';";
                    Mage::helper('tnw_salesforce')->getDbConnection('delete')->query($sql);
                    foreach ($_skippedOrdersChunk as $_idToRemove) {
                        unset($this->_cache['entitiesUpdating'][$_idToRemove]);
                    }
                }
            }

            /**
             * use_product_campaign_assignment, reset data
             */
            if (Mage::helper('tnw_salesforce/config_sales')->useProductCampaignAssignment()) {

                /**
                 * @var $manualSync TNW_Salesforce_Helper_Salesforce_Newslettersubscriber
                 */
                $manualSync = Mage::helper('tnw_salesforce/salesforce_newslettersubscriber');

                $manualSync->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
                $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));
                $manualSync->validateSync(true);
            }

            return true;
        }
        catch (Exception $e) {
            $this->logError("CRITICAL: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check and synchronize order customers if it's necessary
     * @param $_websites
     */
    public function syncOrderCustomers($_emails, $_websites)
    {
        /**
         * Force sync of the customer
         * Or if it's guest checkout: customer->getId() is empty
         * Or customer was not synchronized before: no account/contact ids ot lead not converted
         */

        $_customersToSync = array();

        /**
         * @var $customer Mage_Customer_Model_Customer
         */
        foreach ($this->_cache['orderCustomers'] as $orderIncrementId => $customer) {
            $customerId = $this->_cache['orderToCustomerId'][$orderIncrementId];
            $websiteSfId = $_websites[$customerId];

            $email = $this->_cache['orderToEmail'][$orderIncrementId];

            $syncCustomer = false;

            /**
             * If customer has not default billing/shipping addresses - we can use data from order if it's allowed
             */
            if (Mage::helper('tnw_salesforce')->canUseOrderAddress()) {

                if (!$customer->getDefaultBillingAddress()) {
                    $order = $this->getOrderByIncrementId($orderIncrementId);

                    $customerAddress = Mage::getModel('customer/address');

                    $orderAddress = $order->getBillingAddress();
                    $customerAddress->setData($orderAddress->getData());

                    $customerAddress->setIsDefaultBilling(true);
                    $customer->setData('default_billing', $customerAddress->getId());
                    $customer->addAddress($customerAddress);
                    $syncCustomer = true;
                }

                if (!$customer->getDefaultShippingAddress()) {
                    $order = $this->getOrderByIncrementId($orderIncrementId);

                    $customerAddress = Mage::getModel('customer/address');

                    $orderAddress = $order->getShippingAddress();
                    $customerAddress->setData($orderAddress->getData());

                    $customerAddress->setIsDefaultShipping(true);
                    $customer->setData('default_shipping', $customerAddress->getId());
                    $customer->addAddress($customerAddress);
                    $syncCustomer = true;
                }
            }

            /**
             * synchronize customer if no account/contact exists or lead not converted
             */
            if (!isset($this->_cache['contactsLookup'][$websiteSfId][$email])
                || !isset($this->_cache['accountsLookup'][0][$email])
                || (
                    isset($this->_cache['leadsLookup'][$websiteSfId][$email])
                    && !$this->_cache['leadsLookup'][$websiteSfId][$email]->IsConverted
                )
            ) {
                $syncCustomer = true;
            }

            if ($syncCustomer) {
                $_customersToSync[$orderIncrementId] = $customer;
                /**
                 * update cache, useful if we define some customer data from order
                 */
                $this->_cache['orderCustomers'][$orderIncrementId] = $customer;

                /**
                 * register custome, this data will be used in customer sync class
                 */
                if ($customer->getId()) {
                    if (Mage::registry('customer_cached_' . $customer->getId())) {
                        Mage::unregister('customer_cached_' . $customer->getId());
                    }
                    Mage::register('customer_cached_' . $customer->getId(), $customer);
                }

            }
        }

        if (!empty($_customersToSync)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Synchronizing Guest/New customer...');

            $helperType = 'salesforce';
            //if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            if (count($_customersToSync) > 1) {
                $helperType = 'bulk';
            }

            /**
             * @var $manualSync TNW_Salesforce_Helper_Bulk_Customer|TNW_Salesforce_Helper_Salesforce_Customer
             */
            $manualSync = Mage::helper('tnw_salesforce/' . $helperType . '_customer');
            if ($manualSync->reset()) {
                $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
                $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());

                $manualSync->forceAdd($_customersToSync, $this->_cache['orderCustomers']);
                set_time_limit(30);
                $orderCustomers = $manualSync->process(true);

                if (!empty($orderCustomers)) {
                    if (!is_array($orderCustomers)) {
                        $orderIncrementIds = array_keys($_customersToSync);
                        $orderCustomersArray[array_shift($orderIncrementIds)] = $orderCustomers;
                    } else {
                        $orderCustomersArray = $orderCustomers;
                    }

                    $this->_cache['orderCustomers'] = $orderCustomersArray + $this->_cache['orderCustomers'];
                    set_time_limit(30);

                    $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emails, $_websites);
                    $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($_emails, $_websites);
                }
            }
        }
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
     * @param $ids
     * Reset Salesforce ID in Magento for the order
     */
    public function resetEntity($ids)
    {
        $ids = !is_array($ids)
            ? array($ids) : $ids;

        $mainTable = $this->_modelEntity()->getResource()->getMainTable();
        $sql = "UPDATE `" . $mainTable . "` SET salesforce_id = NULL, sf_insync = 0 WHERE entity_id IN (" . join(',', $ids) . ");";
        Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf("%s ID and Sync Status for %s (#%s) were reset.", 'Order', 'order', join(',', $ids)));
    }

    /**
     * Prepare order history notes for syncronization
     */
    protected function _prepareNotes()
    {
        /** @var TNW_Salesforce_Model_Tool_Log $toolLog */
        $toolLog =  Mage::getSingleton('tnw_salesforce/tool_log');
        $toolLog->saveTrace('----------Prepare Notes: Start----------');

        // Get all products from each order and decide if all needs to me synced prior to inserting them
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_number) {
            if (in_array($_number, $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())])) {
                $toolLog->saveTrace(sprintf('%s (%s): Skipping, issues with upserting an %s!',
                    strtoupper($this->getMagentoEntityName()), $_number, $this->getSalesforceEntityName()));

                continue;
            }

            $_order = $this->_loadEntityByCache($_key, $_number);
            $this->createObjNones($_order->getAllStatusHistory());
        }

        $toolLog->saveTrace('----------Prepare Notes: End----------');
    }

    /**
     * @param Mage_Sales_Model_Order_Status_History[] $notes
     * @return $this
     */
    public function createObjNones($notes)
    {
        if (!$notes instanceof Varien_Data_Collection && !is_array($notes)) {
            $notes = array($notes);
        }

        /** @var Mage_Sales_Model_Order_Status_History $_note */
        foreach ($notes as $_note) {
            // Only sync notes for the order
            if (!($_note->getData('entity_name') == 'order' && !$_note->getData('salesforce_id') && $_note->getData('comment'))) {
                continue;
            }

            $comment      = utf8_encode($_note->getData('comment'));
            $salesforceId = $_note->getOrder()->getSalesforceId();

            $_obj = new stdClass();
            $_obj->ParentId   = $salesforceId;
            $_obj->IsPrivate  = 0;
            $_obj->Body       = $comment;
            $_obj->Title      = (strlen($comment) > 75)
                ? sprintf('%s...', mb_substr($comment, 0, 75))
                : $comment;

            foreach ($_obj as $key => $_value) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Note Object: " . $key . " = '" . $_value . "'");
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('+++++++++++++++++++++++++++++');
            $this->_cache['notesToUpsert'][$_note->getData('entity_id')] = $_obj;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function pushDataNotes()
    {
        if (empty($this->_cache['notesToUpsert'])) {
            return $this;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Notes: Start----------');
        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_notes_send_before', $this->_magentoEntityName), array("data" => $this->_cache['notesToUpsert']));

        // Push Cart
        $notesToUpsert = array_chunk($this->_cache['notesToUpsert'], TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);
        foreach ($notesToUpsert as $_itemsToPush) {
            $this->_pushNotes($_itemsToPush);
        }

        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_notes_send_after', $this->_magentoEntityName), array(
            "data" => $this->_cache['notesToUpsert'],
            "result" => $this->_cache['responses']['notes']
        ));

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Notes: End----------');
        return $this;
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

        if (
            is_array($this->_cache['accountsLookup'])
            && array_key_exists(0, $this->_cache['accountsLookup'])
            && array_key_exists($_orderEmail, $this->_cache['accountsLookup'][0])
        ) {
            $_accountId = $this->_cache['accountsLookup'][0][$_orderEmail]->Id;
        } elseif (
            $_customerEmail && $_orderEmail != $_customerEmail
            && is_array($this->_cache['accountsLookup'])
            && array_key_exists(0, $this->_cache['accountsLookup'])
            && array_key_exists($_customerEmail, $this->_cache['accountsLookup'][0])
        ) {
            $_accountId = $this->_cache['accountsLookup'][0][$_customerEmail]->Id;
        }

        return $_accountId;
    }

    /**
     * @return bool|false|null|string
     */
    public function getMagentoIdField()
    {
        /**
         * @var $mapping TNW_Salesforce_Model_Sync_Mapping_Order_Base_Item
         */
        $mapping = Mage::getSingleton($this->getModulePrefix() . '/sync_mapping_' . $this->getMagentoEntityName() . '_' . $this->_salesforceEntityName . '_item');

        return $mapping->getMagentoIdField();
    }

    protected function _updatePreparedObjectInfo($item) {}

    /**
     * @param array $chunk
     * push Notes chunk into Salesforce
     */
    protected function _pushNotes($chunk = array())
    {
        try {
            $results = $this->_mySforceConnection->upsert("Id", array_values($chunk), 'Note');
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach($chunk as $_object) {
                $this->_cache['responses']['notes'][] = $_response;
            }
            $results = array();
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL: Push of Notes to SalesForce failed' . $e->getMessage());
        }

        $_noteIds = array_keys($chunk);
        foreach ($results as $_key => $_result) {
            $_noteId = $_noteIds[$_key];

            //Report Transaction
            $this->_cache['responses']['notes'][$_noteId] = $_result;

            if (!$_result->success) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Note (id: ' . $_noteId . ') failed to upsert');
                $this->_processErrors($_result, 'orderNote', $chunk[$_noteId]);

            } else {
                $_orderSalesforceId = $this->_cache['notesToUpsert'][$_noteId]->ParentId;
                $_orderId = array_search($_orderSalesforceId, $this->_cache  ['upserted' . $this->getManyParentEntityType()]);

                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_status_history') . "` SET salesforce_id = '" . $_result->id . "' WHERE entity_id = '" . $_noteId . "';";
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Note (id: ' . $_noteId . ') upserted for order #' . $_orderId . ')');
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
            }
        }
    }

    /**
     * @param string $type
     * @return bool
     */
    public function process($type = 'soft')
    {
        try {
            if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
                if (!$this->isFromCLI() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addWarning('WARNING: SKIPPING synchronization, could not establish Salesforce connection.');
                }

                return false;
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ MASS SYNC: START ================");

            if (!is_array($this->_cache) || empty($this->_cache['entitiesUpdating'])) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("WARNING: Sync %s, cache is empty!", $this->getManyParentEntityType()));
                $this->_dumpObjectToLog($this->_cache, "Cache", true);

                return false;
            }

            $this->_beforeProcess();
            $this->_alternativeKeys = $this->_cache['entitiesUpdating'];

            $this->_prepareEntity();
            $this->_pushEntity();

            $this->clearMemory();
            set_time_limit(1000);

            if ($type == 'full') {
                $this->_prepareRemaining();
                $this->_pushRemainingEntityData();

                $this->clearMemory();
            }

            $this->_onComplete();
            $this->_afterProcess();

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================= MASS SYNC: END =================");
            return true;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: " . $e->getMessage());
            return false;
        }
    }

    protected function _beforeProcess()
    {
        return;
    }

    protected function _afterProcess()
    {
        return;
    }

    /**
     * Remaining Data
     */
    protected function _prepareRemaining()
    {
        if (Mage::helper('tnw_salesforce')->doPushShoppingCart()) {
            $this->_prepareEntityItems();
        }

        if (Mage::helper('tnw_salesforce')->isOrderNotesEnabled()) {
            $this->_prepareNotes();
        }
    }

    protected function _prepareEntity()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('----------%s Preparation: Start----------', $this->getUcParentEntityType()));
        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber)
        {
            if (!$this->_checkPrepareEntityBefore($_key)) {
                continue;
            }

            $this->_obj = new stdClass();
            $this->_setEntityInfo($this->_loadEntityByCache($_key, $_orderNumber));

            if (!$this->_checkPrepareEntityAfter($_key)) {
                continue;
            }

            $this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))][$_orderNumber] = $this->_obj;
        }

        $this->_prepareEntityAfter();
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('----------%s Preparation: End----------', $this->getUcParentEntityType()));
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
            $this->_allResults[$skippedKey]++;

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
            $this->_allResults[$skippedKey]++;

            return false;
        }

        return true;
    }

    protected function _prepareEntityAfter()
    {
        return;
    }

    /**
     * @return false|Mage_Core_Model_Abstract
     */
    protected function _modelEntity()
    {
        return Mage::getModel($this->_magentoEntityModel);
    }

    /**
     * @param $_key
     * @return Mage_Core_Model_Abstract
     */
    protected function _loadEntity($_key)
    {
        return $this->_modelEntity()
            ->load($_key);
    }

    /**
     * @param $_key
     * @param $cachePrefix
     * @return Mage_Core_Model_Abstract
     */
    protected function _loadEntityByCache($_key, $cachePrefix = null)
    {
        $_entity = null;

        // Generate cache key
        if (is_null($cachePrefix)) {
            $_entity = $this->_loadEntity($_key);
            $cachePrefix = $this->_getEntityCachePrefix($_entity);
        }

        $entityRegistryKey = sprintf('%s_cached_%s', $this->_magentoEntityName, (string)$cachePrefix);
        if (!is_null($_entity)) {
            Mage::unregister($entityRegistryKey);
        }

        // Generate cache
        if (!Mage::registry($entityRegistryKey)) {
            $_entity = is_null($_entity) ? $this->_loadEntity($_key) : $_entity;
            Mage::register($entityRegistryKey, $_entity);
        }

        // Get entity
        return Mage::registry($entityRegistryKey);
    }

    /**
     * @param $_entity
     * @return mixed
     */
    protected function _getEntityCachePrefix($_entity)
    {
        return $_entity->getRealOrderId();
    }

    protected function _setEntityInfo($order)
    {
        return;
    }

    abstract protected function _pushEntityItems($chunk = array());
    abstract protected function _pushEntity();

    /**
     * Prepare Order items object(s) for upsert
     */
    protected function _prepareEntityItems()
    {
        $failedKey = sprintf('failed%s', $this->getManyParentEntityType());

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('----------Prepare %s items: Start----------', $this->_magentoEntityName));
        $this->_prepareEntityItemsBefore();

        foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
            if (in_array($_orderNumber, $this->_cache[$failedKey])) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('%s (%s): Skipping, issues with upserting an %s!',
                        strtoupper($this->_magentoEntityName), $_orderNumber, $this->_salesforceEntityName));

                continue;
            }

            $this->_prepareOrderItem($_orderNumber);
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf('----------Prepare %s items: End----------', $this->_magentoEntityName));
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

    protected function _checkPrepareEntityItem($_key)
    {
        return true;
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

    protected function _pushRemainingEntityData()
    {
        $itemKey = sprintf('%sToUpsert', lcfirst($this->getItemsField()));

        // Push Order Products
        if (!empty($this->_cache[$itemKey])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Cart Items: Start----------');

            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_products_send_before', $this->_magentoEntityName), array("data" => $this->_cache[$itemKey]));

            $orderItemsToUpsert = array_chunk($this->_cache[$itemKey], TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);
            foreach ($orderItemsToUpsert as $_itemsToPush) {
                $this->_pushEntityItems($_itemsToPush);
            }

            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_products_send_after', $this->_magentoEntityName), array(
                "data" => $this->_cache[$itemKey],
                "result" => $this->_cache['responses'][lcfirst($this->getItemsField())]
            ));

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Push Cart Items: End----------');
        }

        // Push Custom Data
        $this->_pushRemainingCustomEntityData();

        // Kick off the event to allow additional data to be pushed into salesforce
        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_sync_after_final', $this->_magentoEntityName),array(
            "all" => $this->_cache['entitiesUpdating'],
            "failed" => $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())]
        ));
    }

    protected function _pushRemainingCustomEntityData()
    {
        // Push Notes
        $this->pushDataNotes();
    }

    /**
     * @depricated Exists compatibility for
     * @comment call leads convertation method
     */
    protected function _convertLeads()
    {
        return Mage::helper('tnw_salesforce/salesforce_data_lead')
            ->setParent($this)->convertLeads($this->_magentoEntityName);
    }

    /**
     * Mass sync products that are part of the order
     */
    protected function syncProducts()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ INVENTORY SYNC: START ================");

        /** @var TNW_Salesforce_Helper_Bulk_Product $manualSync */
        $manualSync = Mage::helper('tnw_salesforce/bulk_product');
        $manualSync->setSalesforceServerDomain($this->getSalesforceServerDomain());
        $manualSync->setSalesforceSessionId($this->getSalesforceSessionId());

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SF Domain: " . $this->getSalesforceServerDomain());
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SF Session: " . $this->getSalesforceSessionId());

        foreach ($this->_stockItems as $_storeId => $_products) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Store Id: " . $_storeId);
            $manualSync->setOrderStoreId($_storeId);
            if ($manualSync->reset()) {
                $manualSync->massAdd($this->_stockItems[$_storeId]);
                $manualSync->process();
                if (!$this->isFromCLI()) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Store #' . $_storeId . ' ,Product inventory was synchronized with Salesforce'));
                }
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('WARNING: Salesforce Connection could not be established!');
            }
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ INVENTORY SYNC: END ================");
    }

    /**
     * Update Magento
     * @return bool
     */
    protected function _updateMagento()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- Start: Magento Update ----------");
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
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Failed to look up a contact after Lead was converted.");
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

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Updated: " . count($this->_cache['toSaveInMagento']) . " customers!");
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- End: Magento Update ----------");
        return true;
    }

    public function updateStatus($order)
    {
        if (Mage::getModel('tnw_salesforce/localstorage')->getObject($order->getId())) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice("SKIPPING: Order #" . $order->getRealOrderId() . " is already queued for update.");
            return;
        }

        $this->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
        $this->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));
        $this->reset();
        // Added a parameter to skip customer sync when updating order status
        $this->massAdd($order->getId(), false,
            Mage::getStoreConfig(TNW_Salesforce_Helper_Config_Sales::XML_PATH_ORDERS_STATUS_UPDATE_CUSTOMER)
        );

        $this->_obj = new stdClass();

        //Process mapping
        Mage::getSingleton(sprintf('tnw_salesforce/sync_mapping_order_%s', $this->_salesforceEntityName))
            ->setSync($this)
            ->processMapping($order);

        // Update order status
        $this->_updateEntityStatus($order);

        if ($order->getSalesforceId()) {
            $this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))][$order->getRealOrderId()] = $this->_obj;
            $this->_pushEntity();
        }
        else {
            // Need to do full sync instead
            $res = $this->process('full');
            if ($res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SUCCESS: Updating Order #" . $order->getRealOrderId());
            }
        }
    }

    protected function _updateEntityStatus($order)
    {
        return;
    }
}