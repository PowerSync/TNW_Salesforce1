<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 09.03.15
 * Time: 22:22
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
     * @param null $_field
     * @param null $_value
     * @return null
     */
    protected function _customizeAddressValue($_field = NULL, $_value = NULL)
    {
        return $_value;
    }

    /**
     * @comment Apply base mapping for the customer entity
     * @param Mage_Customer_Model_Customer $_customer
     */
    protected function _processMapping($_customer = NULL)
    {

        $this->_email = strtolower($_customer->getEmail());
        $this->_websiteId = $_customer->getData('website_id');

        if ($_customer->getGroupId()) {

            if (is_array($this->_customerGroups) && array_key_exists($_customer->getGroupId(), $this->_customerGroups) && !$this->_customerGroups[$_customer->getGroupId()]) {
                $this->_customerGroups[$_customer->getGroupId()] = $this->_customerGroupModel->load($_customer->getGroupId());
            }
        }

        foreach ($this->getMappingCollection() as $_map) {
            $_doSkip = $value = false;

            list($mappingType, $attributeCode) = explode(" : ", $_map->local_field);

            if (!$this->_mappingTypeAllowed($mappingType)) {
                continue;
            }

            $sf_field = $_map->sf_field;

            switch ($mappingType) {
                case "Customer":
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                    $_attr = $_customer->getAttribute($attributeCode);
                    if (
                        is_object($_attr) && $_attr->getFrontendInput() == "select"
                    ) {
                        $newAttribute = $_customer->getResource()->getAttribute($attributeCode)->getSource()->getOptionText($_customer->$attr());
                    } elseif (is_object($_attr) && $_attr->getFrontendInput() == "multiselect") {
                        $values = explode(",", $_customer->$attr());
                        $newValues = array();
                        foreach ($values as $_val) {
                            $newValues[] = $_customer->getResource()->getAttribute($attributeCode)->getSource()->getOptionText($_val);
                        }
                        $newAttribute = join(";", $newValues);
                    } else {
                        $newAttribute = $_customer->$attr();
                    }
                    // Reformat date fields
                    if ($_map->getBackendType() == "datetime" || $attributeCode == 'created_at' || $_map->getBackendType() == "date") {
                        if ($_customer->$attr()) {
                            $timestamp = Mage::getModel('core/date')->timestamp(strtotime($_customer->$attr()));
                            if ($attributeCode == 'created_at') {
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
                    $value = $this->_customerGroups[$_customer->getGroupId()]->$attr();
                    break;
                case "Billing":
                case "Shipping":
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                    $var = 'getDefault' . $mappingType . 'Address';
                    /* only push default address if set */
                    $address = $_customer->$var();
                    if ($address) {
                        $value = $address->$attr();
                        if (is_array($value)) {
                            $value = implode(", ", $value);
                        } else {
                            $value = ($value && !empty($value)) ? $value : NULL;
                        }
                    }
                    $value = $this->_customizeAddressValue($attributeCode, $value);
                    break;
                case "Aitoc":
                    $modules = Mage::getConfig()->getNode('modules')->children();
                    $value = NULL;
                    if (property_exists($modules, 'Aitoc_Aitcheckoutfields')) {
                        $aCustomAtrrList = Mage::getModel('aitcheckoutfields/transport')->loadByCustomerId($_customer->getId());
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
                    $store = ($_customer->getStoreId()) ? Mage::getModel('core/store')->load($_customer->getStoreId()) : NULL;
                    if ($attributeCode == "current_url") {
                        $value = Mage::helper('core/url')->getCurrentUrl();
                    } elseif ($attributeCode == "todays_date") {
                        $value = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
                    } elseif ($attributeCode == "todays_timestamp") {
                        $value = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(time()));
                    } elseif ($attributeCode == "end_of_month") {
                        $lastday = mktime(0, 0, 0, date("n") + 1, 0, date("Y"));
                        $value = date("Y-m-d", Mage::getModel('core/date')->timestamp($lastday));
                    } elseif ($attributeCode == "store_view_name") {
                        $value = (is_object($store)) ? $store->getName() : NULL;
                    } elseif ($attributeCode == "store_group_name") {
                        $value = (
                            is_object($store)
                            && is_object($store->getGroup())
                        ) ? $store->getGroup()->getName() : NULL;
                    } elseif ($attributeCode == "website_name") {
                        $value = (
                            is_object($store)
                            && is_object($store->getWebsite())
                        ) ? $store->getWebsite()->getName() : NULL;
                    } else {
                        $value = $_map->default_value;
                        if ($value == "{{url}}") {
                            $value = Mage::helper('core/url')->getCurrentUrl();
                        } elseif ($value == "{{today}}") {
                            $value = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
                        } elseif ($value == "{{end of month}}") {
                            $lastday = mktime(0, 0, 0, date("n") + 1, 0, date("Y"));
                            $value = date("Y-m-d", $lastday);
                        } elseif ($value == "{{contact id}}") {
                            /**
                             * @deprecated
                             */
                            $value = null;//$this->_contactId;
                        } elseif ($value == "{{store view name}}") {
                            $value = Mage::app()->getStore()->getName();
                        } elseif ($value == "{{store group name}}") {
                            $value = Mage::app()->getStore()->getGroup()->getName();
                        } elseif ($value == "{{website name}}") {
                            $value = Mage::app()->getWebsite()->getName();
                        }
                    }
                    break;
                default:
                    break;
            }
            if ($value) {
                $this->getObj()->$sf_field = trim($value);
            } else {
                Mage::helper('tnw_salesforce')->log(strtoupper($this->_type) . ' MAPPING: attribute ' . $sf_field . ' does not have a value in Magento, SKIPPING!');
            }
        }
        unset($collection, $_map, $group);

        $syncParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . "disableMagentoSync__c";
        $this->getObj()->$syncParam = true;

        if ($_customer->getId()) {
            $this->getObj()->{$this->getMagentoId()} = $_customer->getId();
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
        $this->getSync()->getCustomerOwnerId();
    }
}