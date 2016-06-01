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
        $this->prepare();
    }

    protected function prepare()
    {
        if (empty($this->_attributes)) {
            $resource = Mage::getResourceModel('eav/entity_attribute');
            $this->_attributes['salesforce_id'] = $resource->getIdByCode('catalog_product', 'salesforce_id');
            $this->_attributes['salesforce_pricebook_id'] = $resource->getIdByCode('catalog_product', 'salesforce_pricebook_id');
            $this->_attributes['sku'] = $resource->getIdByCode('catalog_product', 'sku');
            $this->_attributes['sf_insync'] = $resource->getIdByCode('catalog_product', 'sf_insync');
        }

        $this->_mapProductCollection = Mage::getModel('tnw_salesforce/mapping')
            ->getCollection()
            ->addObjectToFilter('Product2')
            ->addFieldToFilter('sf_magento_enable', 1);

        if (!$this->_product) {
            $this->_product = Mage::getModel('catalog/product');
        }
        if (!$this->_write) {
            $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
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
            $_product->setInSync(1);

            $_stock = array();

            $this->_mapProductCollection->clear()
                ->addFieldToFilter('sf_magento_type', array(
                    TNW_Salesforce_Model_Mapping::SET_TYPE_UPSERT,
                    ($_product->isObjectNew())
                        ? TNW_Salesforce_Model_Mapping::SET_TYPE_INSERT : TNW_Salesforce_Model_Mapping::SET_TYPE_UPDATE
                ));

            /** @var TNW_Salesforce_Model_Mapping $_mapping */
            foreach ($this->_mapProductCollection as $_mapping) {
                if (strpos($_mapping->getLocalField(), 'Product : ') === 0) {
                    $value = property_exists($object, $_mapping->getSfField())
                        ? $object->{$_mapping->getSfField()} : null;

                    Mage::getSingleton('tnw_salesforce/mapping_type_product')
                        ->setMapping($_mapping)
                        ->setValue($_product, $value);

                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveTrace('Product: ' . $_mapping->getLocalFieldAttributeCode() . ' = ' . var_export($_product->getData($_mapping->getLocalFieldAttributeCode()), true));
                } elseif (strpos($_mapping->getLocalField(), 'Product Inventory : ') === 0) {
                    // Inventory
                    $_magentoFieldName = str_replace('Product Inventory : ', '', $_mapping->getLocalField());
                    if (property_exists($object, $_mapping->getSfField())) {
                        $_stock[$_magentoFieldName] = $object->{$_mapping->getSfField()};
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Product Inventory: ' . $_magentoFieldName . ' = ' . $object->{$_mapping->getSfField()});
                    }
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

        $appEmulation = Mage::getSingleton('core/app_emulation');
        foreach (array_merge($storeIds, array((int)Mage::app()->getStore('admin')->getId())) as $storeId) {
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);

            $currencyBase = Mage::helper('tnw_salesforce')->isMultiCurrency()
                ? Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE)
                : null;

            $pricebookId = Mage::getStoreConfig(TNW_Salesforce_Helper_Data::PRODUCT_PRICEBOOK);

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

            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
        }
    }

    /**
     * Accepts a single customer object and upserts a contact into the DB
     *
     * @param null $object
     * @return bool|false|Mage_Core_Model_Abstract
     */
    public function syncFromSalesforce($object = null)
    {
        $this->prepare();

        $_isNew = false;

        $_sku = (property_exists($object, "ProductCode") && $object->ProductCode) ? $object->ProductCode : null;
        $_salesforceId = (property_exists($object, "Id") && $object->Id) ? $object->Id : null;

        $_magentoId = (property_exists($object, $this->_magentoIdField) && $object->{$this->_magentoIdField}) ? $object->{$this->_magentoIdField} : null;

        if (!$_salesforceId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting product into Magento: Product2 ID is missing");
            $this->_addError('Could not upsert Product into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }
        if (!$_sku && !$_magentoId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting product into Magento: Email and Magento ID are missing");
            $this->_addError('Error upserting product into Magento: Email and Magento ID are missing', 'SKU_AND_MAGENTO_ID_MISSING');
            return false;
        }

        // Lookup product by Magento Id
        if ($_magentoId) {
            //Test if user exists
            $sql = "SELECT entity_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('catalog_product_entity') . "` WHERE entity_id = '" . $_magentoId . "'";
            $row = $this->_write->query($sql)->fetch();
            if (!$row) {
                // Magento ID exists in Salesforce, user must have been deleted. Will re-create with the same ID
                $_isNew = true;
            }
        }
        if ($_magentoId && !$_isNew) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Product loaded using Magento ID: " . $_magentoId);
        } else {
            // No Magento ID
            if ($_salesforceId) {
                // Try to find the user by SF Id
                $sql = "SELECT entity_id FROM `" . Mage::helper('tnw_salesforce')->getTable('catalog_product_entity_varchar') . "` WHERE value = '" . $_salesforceId . "' AND attribute_id = '" . $this->_attributes['salesforce_id'] . "' AND entity_type_id = '" . $this->_productEntityTypeId . "'";
                $row = $this->_write->query($sql)->fetch();
                $_magentoId = ($row) ? $row['entity_id'] : null;
            }

            if ($_magentoId) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Customer #" . $_magentoId . " Loaded by using Salesforce ID: " . $_salesforceId);
            } else {
                //Last reserve, try to find by SKU
                $sql = "SELECT entity_id FROM `" . Mage::helper('tnw_salesforce')->getTable('catalog_product_entity_varchar') . "` WHERE value = '" . $_sku . "' AND attribute_id = '" . $this->_attributes['sku'] . "' AND entity_type_id = '" . $this->_productEntityTypeId . "'";
                $row = $this->_write->query($sql)->fetch();
                $_magentoId = ($row) ? $row['entity_id'] : null;

                if ($_magentoId) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Customer #" . $_magentoId . " Loaded by using SKU: " . $_sku);
                } else {
                    //Brand new user
                    $_isNew = true;
                }
            }
        }

        return $this->_updateMagento($object, $_magentoId, $_sku, $_salesforceId, $_isNew);
    }
}
