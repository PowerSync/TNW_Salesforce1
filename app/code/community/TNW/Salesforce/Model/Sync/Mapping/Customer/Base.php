<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

abstract class TNW_Salesforce_Model_Sync_Mapping_Customer_Base extends TNW_Salesforce_Model_Sync_Mapping_Abstract_Base
{


    /**
     * @comment user email
     * @var null
     */
    protected $_email = null;

    /**
     * @comment user website
     * @var null
     */
    protected $_websiteId = null;

    /**
     * @var null|string
     */
    protected $_magentoId = null;
    /**
     * @comment list of the allowed mapping types
     * @var array
     */
    protected $_allowedMappingTypes = array(
        'Customer',
        'Customer Group',
        'Billing',
        'Shipping',
        'Aitoc',
        'Custom'
    );

    /**
     * @var array
     */
    protected $_regions = array();

    /**
     * @param null $address
     * @return array
     */
    protected function _getRegions($address = NULL)
    {

        if ($address instanceof Varien_Object) {
            $countryId = $address->getCountryId();
        }

        if (empty($countryId)) {
            return array();
        }

        if (!$this->_regions || !isset($this->_regions[$countryId])) {

            $regionCollection = Mage::getModel('directory/region')->getCollection();
            $regionCollection->addCountryFilter($countryId);
            $this->_regions[$countryId] = $regionCollection;
        }

        return $this->_regions[$countryId];
    }

    /**
     * @param null $_field
     * @param null $_value
     * @return null
     */
    protected function _customizeAddressValue($_field = NULL, $_value = NULL, $address = NULL)
    {
        if ($_field == 'region_id') {
            $regions = $this->_getRegions($address);
            /**
             * use state region code instead region_id to send data to Salesforce
             */
            if (!empty($regions)) {
                foreach ($regions as $region) {
                    if ($region->getId() == $_value) {
                        $_value = $region->getCode();
                    }
                }
            }
        }
        return $_value;
    }

    /**
     * @comment Apply base mapping for the customer entity
     * @param Mage_Customer_Model_Customer $entity
     */
    protected function _processMapping($entity = NULL)
    {

        $this->_email = strtolower($entity->getEmail());
        $this->_websiteId = $entity->getData('website_id');

        if ($entity->getGroupId() !== NULL) {
            if (is_array($this->_customerGroups) && (!array_key_exists($entity->getGroupId(), $this->_customerGroups) || !$this->_customerGroups[$entity->getGroupId()])) {
                $this->_customerGroups[$entity->getGroupId()] = Mage::getModel('customer/group')->load($entity->getGroupId());
            }
        }

        foreach ($this->getMappingCollection() as $_map) {
            /** @var TNW_Salesforce_Model_Mapping $_map */
            $_doSkip = $value = false;

            $mappingType = $_map->getLocalFieldType();
            $attributeCode = $_map->getLocalFieldAttributeCode();

            if (!$this->_mappingTypeAllowed($mappingType)) {
                continue;
            }

            $sf_field = $_map->getSfField();

            $value = $this->_fieldMappingBefore($entity, $mappingType, $attributeCode, $value);

            if (!$this->isBreak()) {

                switch ($mappingType) {
                    case "Customer":
                        $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                        $_attr = $entity->getAttribute($attributeCode);

                        if (
                            is_object($_attr) && $_attr->getFrontendInput() == "select"
                        ) {
                            $newAttribute = $entity->getResource()->getAttribute($attributeCode)->getSource()->getOptionText($entity->$attr());
                        } elseif (is_object($_attr) && $_attr->getFrontendInput() == "multiselect") {
                            $values = explode(",", $entity->$attr());
                            $newValues = array();
                            foreach ($values as $_val) {
                                $newValues[] = $entity->getResource()->getAttribute($attributeCode)->getSource()->getOptionText($_val);
                            }
                            $newAttribute = join(";", $newValues);
                        } else {
                            $newAttribute = $entity->$attr();
                        }
                        // Reformat date fields
                        if (
                            is_object($_attr) &&
                            (
                                $_map->getBackendType() == "datetime"
                                || $attributeCode == 'created_at'
                                || $_map->getBackendType() == "date"
                                || $_attr->getFrontendInput() == "date"
                                || $_attr->getFrontendInput() == "datetime"
                            )
                        ) {
                            if ($entity->$attr()) {
                                $timestamp = Mage::getModel('core/date')->timestamp(strtotime($entity->$attr()));
                                if ($attributeCode == 'created_at' || $_attr->getFrontendInput() == "datetime") {
                                    $newAttribute = gmdate(DATE_ATOM, $timestamp);
                                } else {
                                    $newAttribute = date("Y-m-d", $timestamp);
                                }
                            } else {
                                $_doSkip = true; //Skip this filed if empty
                            }
                        }
                        if (!$_doSkip) {
                            $value = $newAttribute;
                        }
                        break;
                    case "Customer Group":
                        //Common attributes
                        $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                        $value = $this->_customerGroups[$entity->getGroupId()]->$attr();
                        break;
                    case "Billing":
                    case "Shipping":
                        $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));

                        $var = 'get';
                        if ($entity->getId()) {
                            $var .= 'Default';
                        }
                        $var .= $mappingType . 'Address';

                        /* only push default address if set */
                        $address = $entity->$var();
                        if ($address) {
                            $value = $address->$attr();

                            if (!$value) {
                                $value = $entity->$attr();
                            }

                            if (is_array($value)) {
                                $value = implode(", ", $value);
                            } else {
                                $value = ($value && !empty($value)) ? $value : NULL;
                            }
                        }
                        $value = $this->_customizeAddressValue($attributeCode, $value, $address);
                        break;
                    case "Aitoc":
                        $modules = Mage::getConfig()->getNode('modules')->children();
                        $value = NULL;
                        if (property_exists($modules, 'Aitoc_Aitcheckoutfields')) {
                            $aCustomAtrrList = Mage::getModel('aitcheckoutfields/transport')->loadByCustomerId($entity->getId());
                            foreach ($aCustomAtrrList->getData() as $_key => $_data) {
                                if ($_data['code'] == $attributeCode) {
                                    $value = $_data['value'];
                                    if ($_data['type'] == "date") {
                                        $value = date("Y-m-d", strtotime($value));
                                    }
                                    break;
                                }
                            }
                            unset($aCustomAtrrList);
                        }
                        break;
                    case "Custom":
                        $value = $_map->getCustomValue($entity->getStoreId());
                }
            } else {
                $this->setBreak(false);
            }

            $value = $this->_fieldMappingAfter($entity, $mappingType, $attributeCode, $value);
            if ($value) {
                $this->getObj()->$sf_field = trim($value);
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(strtoupper($this->_type) . ' MAPPING: attribute ' . $sf_field . ' does not have a value in Magento, SKIPPING!');
            }
        }
        unset($collection, $_map, $group);

        if ($entity->getId()) {
            $this->getObj()->{$this->getMagentoId()} = $entity->getId();
        }

        if (Mage::helper('tnw_salesforce')->getCustomerNewsletterSync()) {
            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($this->_email);
            $this->getObj()->HasOptedOutOfEmail = (!is_object($subscriber) || !$subscriber->isSubscribed()) ? 1 : 0;
        }

    }

    /**
     * @param null $email
     * @return null
     */
    protected function _getCustomerAccountId($email = null)
    {
        return $this->getSync()->getCustomerAccountId($email);
    }

    /**
     * @param null $_accountId
     * @return $this
     */
    protected function _setCustomerAccountId($_accountId = null)
    {
        return $this->getSync()->setCustomerAccountId($_accountId);

    }

    /**
     * @param null $_customerOwnerId
     * @return $this;
     */
    protected function _setCustomerOwnerId($_customerOwnerId = null)
    {
        $this->getSync()->setCustomerOwnerId($_customerOwnerId);
    }


    /**
     * @return null|string
     */
    protected function _getCustomerOwnerId()
    {
        return $this->getSync()->getCustomerOwnerId();
    }
}