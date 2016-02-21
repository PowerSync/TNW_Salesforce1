<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
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
     * @return bool
     */
    protected function _doesCartItemExist($parentEntityNumber, $qty, $productIdentifier)
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
        return $this->_cache['upserted' . $this->getManyParentEntityType()][$parentEntityNumber];
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
                    $item->setData($this->getItemQtyField(), $qty);
                    $item->setPricebookEntryConfig($_helper->getFeeProduct($feeName));
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

        if ($qty == 0) {
            Mage::helper('tnw_salesforce')->log("NOTE: Product w/ SKU (" . $item->getSku() . ") is not synchronized, ordered quantity is zero!");
        }

        $storeId = $parentEntity->getStoreId();
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            if ($this->getCurrencyCode($parentEntity) != $parentEntity->getData('store_currency_code')) {
                $storeId = $this->_getStoreIdByCurrency($this->getCurrencyCode($parentEntity));
            }
        }

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

            if (!$product->getSalesforcePricebookId()) {

                throw new Exception("NOTICE: Product w/ SKU (" . $sku . ") is not synchronized, could not add to $this->_salesforceEntityName!");
            }

            /**
             * @var $mapping TNW_Salesforce_Model_Sync_Mapping_Abstract_Base
             */
            $mapping = Mage::getSingleton($this->getModulePrefix() . '/sync_mapping_' . $this->getMagentoEntityName() . '_' . $this->_salesforceEntityName . '_item');

            $mapping->setSync($this)
                ->processMapping($item, $product);

            $identifier = $sku;
            $pricebookEntryId = $product->getSalesforcePricebookId();

        } else {
            $product = new Varien_Object();
            $sku = $item->getSku();
            $pricebookEntryId = Mage::app()->getStore($storeId)->getConfig($item->getPricebookEntryConfig());
            $product->setSalesforceId($pricebookEntryId);

            $identifier = $pricebookEntryId;
            $this->_obj->Description = $item->getDescription();
        }

        /**
         * @comment try to fined item in lookup array. Search prodyct by the sku or tax/shipping/discount by the SalesforcePricebookId
         * @TODO: check, may be it sould be better search product by SalesforcePricebookId too
         */
        if ($cartItemFound = $this->_doesCartItemExist($parentEntityNumber, $qty, $identifier)) {
            $this->_obj->Id = $cartItemFound;
        } else {
            $this->_obj->PricebookEntryId = $pricebookEntryId;
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

        $this->_obj->Quantity = $qty;

        $this->_prepareItemObjFinish($item, $product);

        $this->_cache[lcfirst($this->getItemsField()) . 'ProductsToSync'][$this->_getParentEntityId($parentEntityNumber)] = array();

        if ($this->isItemObjectValid()) {
            /* Dump OpportunityLineItem object into the log */
            foreach ($this->_obj as $key => $_item) {
                Mage::helper('tnw_salesforce')->log("Opportunity/Order Item Object: " . $key . " = '" . $_item . "'");
            }

            $this->_cache[lcfirst($this->getItemsField()) . 'ProductsToSync'][$this->_getParentEntityId($parentEntityNumber)][] = $sku;

            $this->_cache[lcfirst($this->getItemsField()) . 'ToUpsert']['cart_' . $item->getId()] = $this->_obj;
        } else {
            Mage::helper('tnw_salesforce')->log('SKIPPING: Magento product is most likely deleted!');
        }

        Mage::helper('tnw_salesforce')->log('-----------------');

    }

    /*
     * Should Item object be added to SF
     * aka validation to prevent errors
     */
    protected function isItemObjectValid() {
        return (property_exists($this->_obj, 'PricebookEntryId') && $this->_obj->PricebookEntryId)
        || (property_exists($this->_obj, 'Product__c') && $this->_obj->Product__c)
        || (property_exists($this->_obj, 'Id') && $this->_obj->Id);
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
                if(!property_exists($this->_obj, $defaultName)) {
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
}