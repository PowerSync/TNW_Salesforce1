<?php

/**
 * Class TNW_Salesforce_Helper_Magento_Products
 */
class TNW_Salesforce_Helper_Magento_Products extends TNW_Salesforce_Helper_Magento_Abstract
{
    /**
     * @var null
     */
    protected $_attributes = null;

    /**
     * @var null
     */
    protected $_product = null;

    /**
     * @var TNW_Salesforce_Model_Mysql4_Mapping_Collection
     */
    protected $_mapProductCollection = array();

    /**
     * @var null
     */
    protected $_isMultiSync = null;

    /***
     * @var null
     */
    protected $_productEntityTypeId = null;


    /**
     * @comment contains list of the all available product types
     * @var
     */
    protected $_productTypes;

    /**
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->_prepare();
    }

    protected function _prepare()
    {
        parent::_prepare();

        if (empty($this->_attributes)) {
            $resource = Mage::getResourceModel('eav/entity_attribute');
            $this->_attributes['salesforce_id'] = $resource->getIdByCode('catalog_product', 'salesforce_id');
        }

        if ($this->_isMultiSync === null) {
            if (!Mage::app()->isSingleStoreMode()) {
                if (
                    Mage::helper('tnw_salesforce')->getStoreId() == 0
                    && Mage::helper('tnw_salesforce')->getWebsiteId() == 0
                ) {
                    $this->_isMultiSync = true;
                }
            }
            if (Mage::helper('tnw_salesforce')->getPriceScope() == 0) {
                $this->_isMultiSync = true;
            }
        }

        if (!$this->_productEntityTypeId) {
            // Try to find the user by SF Id
            $sql = "SELECT entity_type_id FROM `" . Mage::helper('tnw_salesforce')->getTable('eav_entity_type') . "` WHERE entity_type_code = 'catalog_product'";
            $row = $this->_write->query($sql)->fetch();
            $this->_productEntityTypeId = ($row) ? $row['entity_type_id'] : null;
        }
    }


    /**
     * @param  $name string|null
     * @return array|null
     */
    public function getProductTypeId($name)
    {
        if (empty($this->_productTypes)) {
            $this->_productTypes = array_map(function($type) {
                return $type['label'];
            }, Mage::getConfig()->getNode('global/catalog/product/type')->asArray());
        }

        $result = array_search($name, $this->_productTypes);

        if ($result === false) {
            // Temporary solution
            $result = array_search($name, Mage::getModel('catalog/product_type')->getOptionArray());
            if ($result !== false) {
                return $result;
            }

            $result = $name;
        }

        return $result;
    }

    /**
     * @param $object
     * @param $_magentoId
     * @param $_sku
     * @param $_salesforceId
     * @param $_isNew
     * @return bool|false|Mage_Core_Model_Abstract
     */
    protected function _updateMagento($object, $_magentoId, $_sku, $_salesforceId, $_isNew)
    {
        try {
            set_time_limit(30);

            // Creating Customer Entity
            /** @var Mage_Catalog_Model_Product $_product */
            $_product = Mage::getModel('catalog/product');
            if ($_isNew) {
                $_product->setAttributeSetId($_product->getDefaultAttributeSetId());
                $_product->setStatus(1);

                $this->_response->created = true;
            } else {
                $_product->load($_magentoId);
                $this->_response->created = false;
            }

            //Defaults
            $_product->setSku($_sku);
            $_product->setSalesforceId($_salesforceId);
            $_product->setSfInsync(1);

            $_stock = array();

            $this->_mapProductCollection = Mage::getResourceModel('tnw_salesforce/mapping_collection')
                ->addObjectToFilter('Product2')
                ->addFilterTypeSM(!$_product->isObjectNew())
                ->firstSystem();

            /** @var TNW_Salesforce_Model_Mapping $_mapping */
            foreach ($this->_mapProductCollection as $_mapping) {
                $value = property_exists($object, $_mapping->getSfField())
                    ? $object->{$_mapping->getSfField()} : null;

                if (strpos($_mapping->getLocalField(), 'Product : ') === 0) {
                    Mage::getSingleton('tnw_salesforce/mapping_type_product')
                        ->setMapping($_mapping)
                        ->setValue($_product, $value);

                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveTrace('Product: ' . $_mapping->getLocalFieldAttributeCode() . ' = ' . var_export($_product->getData($_mapping->getLocalFieldAttributeCode()), true));
                } elseif (strpos($_mapping->getLocalField(), 'Product Inventory : ') === 0) {
                    // Inventory
                    if (empty($value)) {
                        $value = $_mapping->getDefaultValue();
                    }

                    $_magentoFieldName = str_replace('Product Inventory : ', '', $_mapping->getLocalField());
                    $_stock[$_magentoFieldName] = $value;
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Product Inventory: ' . $_magentoFieldName . ' = ' . $value);
                }
            }

            if (!empty($_stock)) {
                $_product->setStockData($_stock);
            }

            // Increase the timeout
            set_time_limit(120);

            $_flag = false;
            if (!Mage::getSingleton('core/session')->getFromSalesForce()) {
                Mage::getSingleton('core/session')->setFromSalesForce(true);
                $_flag = true;
            }

            $_currentTime = $this->_getTime();
            if (!$_product->getData('updated_at') || $_product->getData('updated_at') == '') {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Product: updated_at = ' . $_currentTime);
                $_product->setData('updated_at', $_currentTime);
            }
            if (!$_product->getData('created_at') || $_product->getData('created_at') == '') {
                if (property_exists($object, 'CreatedDate')) {
                    $_currentTime = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($object->CreatedDate)));
                }
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Product: created_at = ' . $_currentTime);
                $_product->setData('created_at', $_currentTime);
            }

            // Event change
            $this->fieldUpdateEvent($_product);

            // Save Product
            $_product->save();

            // Update Price
            $this->updatePrice($object, $_product->getId());

            if ($_flag) {
                Mage::getSingleton('core/session')->setFromSalesForce(false);
            }
            // Reset timeout
            set_time_limit(30);

            return $_product;
        } catch (Exception $e) {
            $this->_addError('Error upserting product into Magento: ' . $e->getMessage(), 'MAGENTO_PRODUCT_UPSERT_FAILED');
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting product into Magento: " . $e->getMessage());
            unset($e);
            return false;
        }
    }

    /**
     * @param $object stdClass
     * @param $productId
     * @throws Mage_Core_Exception
     */
    protected function updatePrice($object, $productId)
    {
        // Update Price
        if (!property_exists($object, 'PricebookEntries') || $object->PricebookEntries->totalSize < 1) {
            return;
        }

        $storeIds = array_keys(Mage::app()->getStores());
        if (Mage::getStoreConfig(Mage_Catalog_Helper_Data::XML_PATH_PRICE_SCOPE) == Mage_Catalog_Helper_Data::PRICE_SCOPE_GLOBAL) {
            $storeIds = array(Mage::app()->getWebsite(true)->getDefaultStore()->getId());
        }

        foreach (array_merge($storeIds, array((int)Mage::app()->getStore('admin')->getId())) as $storeId) {
            $currencyBase = Mage::helper('tnw_salesforce')->isMultiCurrency()
                ? Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE, $storeId)
                : null;

            $pricebookId = Mage::getStoreConfig(TNW_Salesforce_Helper_Data::PRODUCT_PRICEBOOK, $storeId);

            $price = null;
            foreach ($object->PricebookEntries->records as $_price) {
                if (!$_price->IsActive) {
                    continue;
                }

                if ($_price->Pricebook2Id != $pricebookId) {
                    continue;
                }

                if (!property_exists($_price, 'CurrencyIsoCode')) {
                    $_price->CurrencyIsoCode = null;
                }

                if ($_price->CurrencyIsoCode != $currencyBase) {
                    continue;
                }

                $price = $_price->UnitPrice;
                break;
            }

            if (!empty($price)) {
                Mage::getSingleton('catalog/product_action')
                    ->updateAttributes(array($productId), array('price' => (float)$price), $storeId);
            }

            $currencyAllow = Mage::helper('tnw_salesforce')->isMultiCurrency()
                ? explode(',', Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_ALLOW, $storeId))
                : array(null);

            $priceBook = array();
            foreach ($object->PricebookEntries->records as $_price) {
                if (!$_price->IsActive) {
                    continue;
                }

                if ($_price->Pricebook2Id != $pricebookId) {
                    continue;
                }

                if (!property_exists($_price, 'CurrencyIsoCode')) {
                    $_price->CurrencyIsoCode = null;
                }

                if (!in_array($_price->CurrencyIsoCode, array_merge($currencyAllow, array($currencyBase)))) {
                    continue;
                }

                $priceBook[] = implode(':', array_filter(array($_price->CurrencyIsoCode, $_price->Id)));
            }

            if (!empty($priceBook)) {
                Mage::getSingleton('catalog/product_action')
                    ->updateAttributes(array($productId), array('salesforce_pricebook_id' => implode("\n", $priceBook)), $storeId);
            }
        }
    }

    /**
     * Accepts a single customer object and upserts a contact into the DB
     *
     * @param stdClass $object
     * @return bool|false|Mage_Core_Model_Abstract
     */
    public function syncFromSalesforce($object = null)
    {
        $this->_prepare();

        $_mTypeId = TNW_Salesforce_Model_Config_Products_Type::TYPE_UNKNOWN;
        $_mMagentoId = null;

        $_sSku          = (!empty($object->ProductCode)) ? $object->ProductCode : null;
        $_sSalesforceId = (!empty($object->Id)) ? $object->Id : null;
        $_sMagentoId    = (!empty($object->{$this->_magentoIdField})) ? $object->{$this->_magentoIdField} : null;

        if (!$_sSalesforceId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting product into Magento: Product2 ID is missing");
            $this->_addError('Could not upsert Product into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }

        if ($this->isProductFee($_sSalesforceId)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("NOTICE. Detected product fee. Skipped");

            return false;
        }

        if (!$_sSku && !$_sMagentoId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting product into Magento: Email and Magento ID are missing");
            $this->_addError('Error upserting product into Magento: Email and Magento ID are missing', 'SKU_AND_MAGENTO_ID_MISSING');
            return false;
        }

        $entityTable = Mage::helper('tnw_salesforce')->getTable('catalog_product_entity');

        // Lookup product by Magento Id
        if (!empty($_sMagentoId)) {
            //Test if user exists
            $sql = "SELECT entity_id, type_id  FROM `$entityTable` WHERE entity_id = '$_sMagentoId'";
            $row = $this->_write->query($sql)->fetch();
            if ($row) {
                $_mMagentoId = $row['entity_id'];
                $_mTypeId    = $row['type_id'];

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Product loaded using Magento ID: " . $_sMagentoId);
            }
        }

        // Lookup product by Salesforce Id
        if (is_null($_mMagentoId) && !empty($_sSalesforceId)) {
            $entityVarcharTable = Mage::helper('tnw_salesforce')->getTable('catalog_product_entity_varchar');
            // Try to find the user by SF Id
            $sql = "SELECT entity.entity_id, entity.type_id FROM `$entityVarcharTable` as attr "
                ."INNER JOIN `$entityTable` as entity "
                    ."ON attr.entity_id = entity.entity_id "
                ."WHERE attr.value = '$_sSalesforceId ' "
                    ."AND attr.attribute_id = '{$this->_attributes['salesforce_id']}' "
                    ."AND attr.entity_type_id = '{$this->_productEntityTypeId}'";

            $row = $this->_write->query($sql)->fetch();
            if ($row) {
                $_mMagentoId = $row['entity_id'];
                $_mTypeId    = $row['type_id'];

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Product #".$_mMagentoId." loaded using Salesforce ID: " . $_sSalesforceId);
            }
        }

        // Lookup product by SKU
        if (is_null($_mMagentoId) && !empty($_sSku)) {
            //Last reserve, try to find by SKU
            $sql = "SELECT entity_id, type_id  FROM `$entityTable` WHERE sku = '$_sSku'";
            $row = $this->_write->query($sql)->fetch();
            if ($row) {
                $_mMagentoId = $row['entity_id'];
                $_mTypeId    = $row['type_id'];

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Product #" . $_mMagentoId . " Loaded by using SKU: " . $_sSku);
            }
        }

        $_isNew = is_null($_mMagentoId);
        if ($_isNew && !empty($object->tnw_mage_basic__Product_Type__c)) {
            $_mTypeId = $this->getProductTypeId($object->tnw_mage_basic__Product_Type__c);
        }

        if (!in_array($_mTypeId, Mage::helper('tnw_salesforce/config_product')->getSyncTypesAllow())) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice('SKIPPING: Sync for product type "' . $_mTypeId . '" is disabled!');

            return false;
        }

        return $this->_updateMagento($object, $_mMagentoId, $_sSku, $_sSalesforceId, $_isNew);
    }

    /**
     * @param $salesforceId
     * @return bool
     */
    protected function isProductFee($salesforceId)
    {
        $fees = array_filter(array_map('unserialize', array_filter(array(
            Mage::helper('tnw_salesforce')->getTaxProduct(),
            Mage::helper('tnw_salesforce')->getShippingProduct(),
            Mage::helper('tnw_salesforce')->getDiscountProduct(),
        ))));

        $fees = array_map(function ($product) {
            if (empty($product['Id'])) {
                return null;
            }

            return $product['Id'];
        }, $fees);

        return in_array($salesforceId, $fees);
    }
}
