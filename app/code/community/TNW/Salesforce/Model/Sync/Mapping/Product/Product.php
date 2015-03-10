<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 09.03.15
 * Time: 22:22
 */
class TNW_Salesforce_Model_Sync_Mapping_Product_Product extends TNW_Salesforce_Model_Sync_Mapping_Product_Base
{
    protected $_type = 'Product2';

    /**
     * @comment list of the allowed mapping types
     * @var array
     */
    protected $_allowedMappingTypes = array(
        'Product',
        'Product Inventory',
        'Custom'
    );

    /**
     * @param Mage_Catalog_Model_Product $prod
     * @throws Mage_Core_Exception
     */
    protected function _processMapping($prod = NULL)
    {
        foreach ($this->getMappingCollection() as $_map) {
            $value = false;

            list($mappingType, $attributeCode) = explode(" : ", $_map->local_field);

            if (!$this->_mappingTypeAllowed($mappingType)) {
                continue;
            }

            $sf_field = $_map->sf_field;

            switch ($mappingType) {
                case "Product Inventory":
                    $stock = Mage::getModel('cataloginventory/stock_item')->setStoreId(Mage::helper('tnw_salesforce')->getStoreId())->loadByProduct($prod);
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                    $value = $stock ? $stock->$attr() : null;
                    break;
                case "Product":
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                    if ($attributeCode == 'website_ids') {
                        $value = join(',', $prod->$attr());
                    } else {
                        $value = ($prod->getAttributeText($attributeCode)) ? $prod->getAttributeText($attributeCode) : $prod->$attr();
                    }
                    break;
                case "Custom":
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
                        $value = Mage::app()->getStore()->getName();
                    } elseif ($attributeCode == "store_group_name") {
                        $value = Mage::app()->getStore()->getGroup()->getName();
                    } elseif ($attributeCode == "website_name") {
                        $value = Mage::app()->getWebsite()->getName();
                    } else {
                        $value = $_map->default_value;
                        /**
                         * deprecated conditionals
                         */
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
                if (!$this->isFromCLI()) {
                    Mage::helper('tnw_salesforce')->log('PRODUCT MAPPING: attribute ' . $sf_field . ' does not have a value in Magento, SKIPPING!');
                }
            }
        }
    }

}