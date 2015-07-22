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
     * @var array
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

    public function __construct()
    {
        parent::__construct();
        $this->prepare();
    }

    /**
     * @param null $_object
     * @return bool|false|Mage_Core_Model_Abstract
     */
    public function process($_object = null)
    {
        if (
            !$_object
            || !Mage::helper('tnw_salesforce')->isWorking()
        ) {
            Mage::helper('tnw_salesforce')->log("No Salesforce object passed on connector is not working");

            return false;
        }
        $this->_response = new stdClass();
        $_type = $_object->attributes->type;
        unset($_object->attributes);
        Mage::helper('tnw_salesforce')->log("** " . $_type . " #" . $_object->Id . " **");
        $_entity = $this->syncFromSalesforce($_object);
        Mage::helper('tnw_salesforce')->log("** finished upserting " . $_type . " #" . $_object->Id . " **");

        // Handle success and fail
        if (is_object($_entity)) {
            $this->_response->success = true;
            Mage::helper('tnw_salesforce')->log("Salesforce " . $_type . " #" . $_object->Id . " upserted!");
            Mage::helper('tnw_salesforce')->log("Magento Id: " . $_entity->getId());
        } else {
            $this->_response->success = false;
            Mage::helper('tnw_salesforce')->log("Could not upsert " . $_type . " into Magento, see Magento log for details");
            $_entity = false;
        }

        if (Mage::helper('tnw_salesforce')->isRemoteLogEnabled()) {
            $logger = Mage::helper('tnw_salesforce/report');
            $logger->reset();

            $logger->add('Magento', 'Product', array($_object->Id => $_object), array($_object->Id => $this->_response));

            $logger->send();
        }
        return $_entity;
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
        $this->_mapProductCollection = Mage::getModel('tnw_salesforce/mapping')->getCollection()->addObjectToFilter('Product2');

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
            // Creating Product Entity
            if ($_isNew) {
                $_product = Mage::getModel('catalog/product');
                if ($_magentoId) {
                    $_product->setId($_magentoId);
                }
                $_product->setAttributeSetId($_product->getDefaultAttributeSetId());
                $_product->setStatus(1);

                $this->_response->created = true;
            } else {
                $_product = Mage::getModel('catalog/product')->load($_magentoId);

                $this->_response->created = false;
            }

            //Defaults
            $_product->setSku($_sku);
            $_product->setSalesforceId($_salesforceId);
            $_product->setInSync(1);

            $_stock = array();

            // get attribute collection
            foreach ($this->_mapProductCollection as $_mapping) {
                if (strpos($_mapping->getLocalField(), 'Product : ') === 0) {
                    // Product
                    $_magentoFieldName = str_replace('Product : ', '', $_mapping->getLocalField());

                    if (property_exists($object, $_mapping->getSfField())) {
                        // get attribute object
                        $localFieldAr = explode(":", $_mapping->getLocalField());
                        $localField = trim(array_pop($localFieldAr));
                        $attOb = Mage::getModel('eav/config')->getAttribute('catalog_product', $localField);

                        // here we set value depending of the attr type
                        if ($attOb->getFrontendInput() == 'select') {
                            // it's drop down attr type
                            $attOptionList = $attOb->getSource()->getAllOptions(true, true);
                            $_value = false;
                            foreach ($attOptionList as $key => $value) {

                                // we compare sf value with mage default value or mage locate related value (if not english lang is set)
                                $sfField = mb_strtolower($object->{$_mapping->getSfField()}, 'UTF-8');
                                $mageAttValueDefault = mb_strtolower($value['label'], 'UTF-8');

                                //if (in_array($sfField, array($mageAttValueDefault, $mageAttValueLocaleRelated))) {
                                if (in_array($sfField, array($mageAttValueDefault))) {
                                    $_value = $value['value'];
                                }
                            }
                            // the product code not found, skipping
                            if (empty($_value)) {
                                $sfValue = $object->{$_mapping->getSfField()};
                                Mage::helper('tnw_salesforce')->log("SKIPPING: product code $sfValue not found in magento");
                                continue;
                            }
                        } elseif ($_mapping->getBackendType() == "datetime" || $_magentoFieldName == 'created_at' || $_magentoFieldName == 'updated_at' || $_mapping->getBackendType() == "date") {
                            $_value = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($this->_salesforceObject->{$_mapping->getSfField()})));
                        } elseif ($_magentoFieldName == 'website_ids') {
                            // websiteids hack
                            $_value = explode(',', $object->{$_mapping->getSfField()});
                        } elseif ($_magentoFieldName == 'status') {
                            // status hack
                            $_value = ($object->{$_mapping->getSfField()} === 1 || $object->{$_mapping->getSfField()} === true) ? 'Enabled' : 'Disabled';
                        } else {
                            $_value = $object->{$_mapping->getSfField()};
                        }
                    } elseif ($_isNew && $_mapping->getDefaultValue()) {
                        $_value = $_mapping->getDefaultValue();
                    }
                    if ($_value) {
                        Mage::helper('tnw_salesforce')->log('Product: ' . $_magentoFieldName . ' = ' . $_value);
                        $_product->setData($_magentoFieldName, $_value);
                    } else {
                        Mage::helper('tnw_salesforce')->log('SKIPPING Product: ' . $_magentoFieldName . ' - no value specified in Salesforce');
                    }
                } elseif (strpos($_mapping->getLocalField(), 'Product Inventory : ') === 0) {
                    // Inventory
                    $_magentoFieldName = str_replace('Product Inventory : ', '', $_mapping->getLocalField());
                    if (property_exists($object, $_mapping->getSfField())) {
                        $_stock[$_magentoFieldName] = $object->{$_mapping->getSfField()};
                        Mage::helper('tnw_salesforce')->log('Product Inventory: ' . $_magentoFieldName . ' = ' . $object->{$_mapping->getSfField()});
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
                Mage::helper('tnw_salesforce')->log('Product: updated_at = ' . $_currentTime);
                $_product->setData('updated_at', $_currentTime);
            }
            if (!$_product->getData('created_at') || $_product->getData('created_at') == '') {
                if (property_exists($this->_salesforceObject, 'CreatedDate')) {
                    $_currentTime = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($this->_salesforceObject->CreatedDate)));
                }
                Mage::helper('tnw_salesforce')->log('Product: created_at = ' . $_currentTime);
                $_product->setData('created_at', $_currentTime);
            }

            // Save Product
            $_product->save();

            if ($_flag) {
                Mage::getSingleton('core/session')->setFromSalesForce(false);
            }
            // Reset timeout
            set_time_limit(30);

            return $_product;
        } catch (Exception $e) {
            $this->_addError('Error upserting product into Magento: ' . $e->getMessage(), 'MAGENTO_PRODUCT_UPSERT_FAILED');
            Mage::helper('tnw_salesforce')->log("Error upserting product into Magento: " . $e->getMessage());
            unset($e);
            return false;
        }
    }

    /**
     * Accepts a single product object and upserts a contact into the DB
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
            Mage::helper('tnw_salesforce')->log("Error upserting product into Magento: Product2 ID is missing");
            $this->_addError('Could not upsert Product into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }
        if (!$_sku && !$_magentoId) {
            Mage::helper('tnw_salesforce')->log("Error upserting product into Magento: Email and Magento ID are missing");
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
            Mage::helper('tnw_salesforce')->log("Product loaded using Magento ID: " . $_magentoId);
        } else {
            // No Magento ID
            if ($_salesforceId) {
                // Try to find the user by SF Id
                $sql = "SELECT entity_id FROM `" . Mage::helper('tnw_salesforce')->getTable('catalog_product_entity_varchar') . "` WHERE value = '" . $_salesforceId . "' AND attribute_id = '" . $this->_attributes['salesforce_id'] . "' AND entity_type_id = '" . $this->_productEntityTypeId . "'";
                $row = $this->_write->query($sql)->fetch();
                $_magentoId = ($row) ? $row['entity_id'] : null;
            }

            if ($_magentoId) {
                Mage::helper('tnw_salesforce')->log("Product #" . $_magentoId . " Loaded by using Salesforce ID: " . $_salesforceId);
            } else {
                //Last reserve, try to find by SKU
                $sql = "SELECT entity_id FROM `" . Mage::helper('tnw_salesforce')->getTable('catalog_product_entity_varchar') . "` WHERE value = '" . $_sku . "' AND attribute_id = '" . $this->_attributes['sku'] . "' AND entity_type_id = '" . $this->_productEntityTypeId . "'";
                $row = $this->_write->query($sql)->fetch();
                $_magentoId = ($row) ? $row['entity_id'] : null;

                if ($_magentoId) {
                    Mage::helper('tnw_salesforce')->log("Product #" . $_magentoId . " Loaded by using SKU: " . $_sku);
                } else {
                    //Brand new user
                    $_isNew = true;
                }
            }
        }

        return $this->_updateMagento($object, $_magentoId, $_sku, $_salesforceId, $_isNew);
    }
}