<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Abstract
 */
abstract class TNW_Salesforce_Helper_Salesforce_Abstract_Order extends TNW_Salesforce_Helper_Salesforce_Abstract
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
    protected function _doesCartItemExist($parentEntityNumber, $qty, $productIdentifier, $description = 'default')
    {
        $_cartItemFound = false;

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

                        || (property_exists($_cartItem, 'PricebookEntry')
                            && property_exists($_cartItem->PricebookEntry, 'ProductCode')
                            && ($_cartItem->PricebookEntry->ProductCode == trim($productIdentifier))
                            && ($description === 'default' || !$description || (property_exists($_cartItem, 'Description') && $_cartItem->Description == $description))
                        )
                    )
                    && $_cartItem->Quantity == (float)$qty
                ) {
                    $_cartItemFound = $_cartItem->Id;
                    break;
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
        $netTotal = 0;

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

        Mage::helper('tnw_salesforce')->log('******** ' . strtoupper($this->getMagentoEntityName()) . ' (' . $parentEntityNumber . ') ********');

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
                Mage::helper('tnw_salesforce')->log($e->getMessage());
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

                    Mage::helper('tnw_salesforce')->log("Add $feeName");

                    $qty = 1;

                    $item = new Varien_Object();

                    $feeData = Mage::app()->getStore($parentEntity->getStoreId())->getConfig($_helper->getFeeProduct($feeName));
                    if ($feeData) {
                        $feeData = unserialize($feeData);
                    } else {
                        continue;
                    }

                    $item->setData($feeData);
                    $item->setData($this->getItemQtyField(), $qty);

                    $item->setDescription($_helper->__($ucFee));

                    $item->setRowTotalInclTax($this->getEntityPrice($parentEntity, $ucFee . 'Amount'));
                    $item->setRowTotal($this->getEntityPrice($parentEntity, $ucFee . 'Amount'));

                    $this->_prepareItemObj($parentEntity, $parentEntityNumber, $item);

                } else {
                    Mage::helper('tnw_salesforce')->log("CRITICAL ERROR: $feeName product is not configured!", 1, "sf-errors");
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Could not add Tax Fee product to the Order!');
                    }
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
            Mage::helper('tnw_salesforce')->log("NOTE: Product w/ SKU (" . $item->getSku() . ") is not synchronized, ordered quantity is zero!");
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

            /**
             * @var $mapping TNW_Salesforce_Model_Sync_Mapping_Abstract_Base
             */
            $mapping = Mage::getSingleton($this->getModulePrefix() . '/sync_mapping_' . $this->getMagentoEntityName() . '_' . $this->_salesforceEntityName . '_item');

            $mapping->setSync($this)
                ->processMapping($item, $product);

            $identifier = $sku;

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

            $identifier = $pricebookEntryId;
            $this->_obj->Description = $item->getDescription();
            $id = $item->getData('Id');;
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
                $opt[] = '<tr><td align="left">' . $_option['label'] . '</td><td align="left">' . $_option['print_value'] . '</td></tr>';
                $_summary[] = $_option['print_value'];
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

        $cartItemFound = $this->_doesCartItemExist($parentEntityNumber, $qty, $identifier, $description);
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
                Mage::helper('tnw_salesforce')->log("Opportunity/Order Item Object: " . $key . " = '" . $_item . "'");
            }

            $this->_cache[lcfirst($this->getItemsField()) . 'ProductsToSync'][$this->_getParentEntityId($parentEntityNumber)][] = $sku;

            $this->_cache[lcfirst($this->getItemsField()) . 'ToUpsert']['cart_' . $item->getItemId()] = $this->_obj;
        } else {
            Mage::helper('tnw_salesforce')->log('SKIPPING: Magento product is most likely deleted or quantity is zero!');
        }

        Mage::helper('tnw_salesforce')->log('-----------------');

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
        if (Mage::helper('tnw_salesforce/abandoned')->isEnabled() && !empty($quotes)) {
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
            Mage::helper('tnw_salesforce')->log("INFO: Could not load pricebook based on the order ID. Loading default pricebook based on current store ID.");
            Mage::helper('tnw_salesforce')->log("ERROR: " . $e->getMessage());
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
     * @param array $_ids
     * @param bool $_isCron
     * @param bool $orderStatusUpdateCustomer
     * @return bool
     */
    public function massAdd($_ids = NULL, $_isCron = false, $orderStatusUpdateCustomer = true)
    {
        if (!$_ids) {
            Mage::helper('tnw_salesforce')->log("Order Id is not specified, don't know what to synchronize!");
            return false;
        }
        // test sf api connection
        $_client = Mage::getSingleton('tnw_salesforce/connection');
        if (!$_client->tryWsdl()
            || !$_client->tryToConnect()
            || !$_client->tryToLogin()
        ) {
            Mage::helper('tnw_salesforce')->log("error on sync orders, sf api connection failed");

            return true;
        }

        try {
            $this->_isCron = $_isCron;
            $_guestCount = 0;
            $_skippedOrders = $_quotes = $_emails = $_websites = array();

            if (!is_array($_ids)) {
                $_ids = array($_ids);
            }

            foreach ($_ids as $_id) {
                // Clear Order ID
                $this->resetOrder($_id);

                // Load order by ID
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
                    $this->logNotice('SKIPPED: Sync for order #' . $_order->getRealOrderId() . ', grand total is zero and synchronization for these order is disabled in configuration!');
                    $skippedOrders[$_order->getId()] = $_order->getId();
                    continue;
                }

                if (!Mage::helper('tnw_salesforce')->syncAllOrders()
                    && !in_array($_order->getStatus(), $this->_allowedOrderStatuses)
                ) {
                    $this->logNotice('SKIPPED: Sync for order #' . $_order->getId() . ', sync for order status "' . $_order->getStatus() . '" is disabled!');
                    $skippedOrders[$_order->getId()] = $_order->getId();
                    continue;
                }

                // Order could not be loaded for some reason
                if (!$_order->getId() || !$_order->getRealOrderId()) {
                    $this->logError('WARNING: Sync for order #' . $_id . ', order could not be loaded!');
                    $skippedOrders[$_order->getId()] = $_order->getId();
                    continue;
                }

                // Get Magento customer object
                $this->_cache['orderCustomers'][$_order->getRealOrderId()] = $this->_getCustomer($_order);

                // Associate order Number with a customer ID
                $_customerId = ($this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId()) ? $this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId() : 'guest-' . $_guestCount;
                $this->_cache['orderToCustomerId'][$_order->getRealOrderId()] = $_customerId;

                if (!$this->_cache['orderCustomers'][$_order->getRealOrderId()]->getId()) {
                    $_guestCount++;
                }

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
                    $skippedOrders[$_order->getId()] = $_order->getId();
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
                    $skippedOrders[$_order->getId()] = $_order->getId();
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

            $this->_findAbandonedCart($_quotes);

            $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emails, $_websites);
            $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($_emails, $_websites);
            $this->_cache['leadsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($_emails, $_websites);

            $this->_prepareOrderLookup();

            if ($orderStatusUpdateCustomer) {

                /**
                 * Force sync of the customer
                 * Or if it's guest checkout: customer->getId() is empty
                 * Or customer was not synchronized before: no account/contact ids ot lead not converted
                 */

                $_customersToSync = array();

                foreach ($this->_cache['orderCustomers'] as $orderIncrementId => $customer) {
                    $customerId = $this->_cache['orderToCustomerId'][$orderIncrementId];
                    $websiteSfId = $_websites[$customerId];

                    $email = $this->_cache['orderToEmail'][$orderIncrementId];

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
                        $_customersToSync[$orderIncrementId] = $customer;
                    }
                }

                if (!empty($_customersToSync)) {
                    Mage::helper("tnw_salesforce")->log('Syncronizing Guest/New customer...');

                    $helperType = 'salesforce';
                    if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
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

                    Mage::helper("tnw_salesforce")->log('SUCCESS: Automatic customer synchronization.');

                } else {
                    /**
                     * No customers for this order in salesforce - error
                     */
                    // Something is wrong, could not create / find Magento customer in SalesForce
                    $this->logError('CRITICAL ERROR: Contact or Lead for Magento customer (' . $_orderEmail . ') could not be created / found!');
                    $skippedOrders[$id] = $id;

                    continue;
                }
            }

            if (!empty($_skippedOrders)) {
                $chunk = array_chunk($_skippedOrders, TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT);

                foreach ($chunk as $_skippedOrdersChunk) {
                    $sql = "DELETE FROM `" . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . "` WHERE object_id IN ('" . join("','", $_skippedOrders) . "') and mage_object_type = 'sales/order';";
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

            /**
             * all orders fails - return false otherwise return true
             */
            return (count($_skippedOrders) != count($_ids));
        } catch (Exception $e) {
            $this->logError("CRITICAL: " . $e->getMessage());
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
     * @param $_id
     * Reset Salesforce ID in Magento for the order
     */
    public function resetOrder($ids)
    {
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET salesforce_id = NULL, sf_insync = 0 WHERE entity_id IN (" . join(',', $ids) . ");";

        Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

        Mage::helper('tnw_salesforce')->log("Order ID and Sync Status for order (#" . join(',', $ids) . ") were reset.");

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
            && array_key_exists($_orderEmail, $this->_cache['accountsLookup'][0])
        ) {
            $_accountId = $this->_cache['accountsLookup'][0][$_orderEmail]->Id;
        } elseif (
            $_customerEmail && $_orderEmail != $_customerEmail
            && is_array($this->_cache['accountsLookup'])
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['accountsLookup'])
            && array_key_exists($_customerEmail, $this->_cache['accountsLookup'][0])
        ) {
            $_accountId = $this->_cache['accountsLookup'][0][$_customerEmail]->Id;
        }

        return $_accountId;
    }

}