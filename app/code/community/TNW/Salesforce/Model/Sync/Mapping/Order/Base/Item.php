<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 10.03.15
 * Time: 23:32
 */
class TNW_Salesforce_Model_Sync_Mapping_Order_Base_Item extends TNW_Salesforce_Model_Sync_Mapping_Order_Base
{

    /**
     * @comment list of the allowed mapping types
     * @var array
     */
    protected $_allowedMappingTypes = array(
        'Cart',
        'Product Inventory',
        'Product',
        'Custom'
    );

    /**
     * @comment Gets field mapping from Magento and creates OpportunityLineItem object
     * @param Mage_Sales_Model_Order_Item $cartItem
     * @param Mage_Catalog_Model_Product $prod
     * @throws Mage_Core_Exception
     */
    protected function _processMapping($cartItem = NULL, $prod = NULL)
    {

        foreach ($this->getMappingCollection() as $_map) {

            $value = false;

            list($mappingType, $attributeCode) = explode(" : ", $_map->local_field);

            if (!$this->_mappingTypeAllowed($mappingType)) {
                continue;
            }

            $sf_field = $_map->sf_field;

            switch ($mappingType) {
                case "Cart":
                    if ($cartItem) {
                        if ($attributeCode == "total_product_price") {
                            $subtotal = number_format((($cartItem->getPrice() + $cartItem->getTaxAmount()) * $cartItem->getQtyOrdered()), 2, ".", "");
                            $value = number_format(($subtotal - $cartItem->getDiscountAmount()), 2, ".", "");
                        } else {
                            $value = $cartItem->getData($attributeCode);

                            // Reformat date fields
                            if ($attributeCode == 'created_at' || $attributeCode == 'updated_at') {
                                if ($cartItem->getData($attributeCode)) {
                                    $timestamp = strtotime($cartItem->getData($attributeCode));
                                    $value = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp($timestamp));
                                }
                            }
                        }
                    }
                    break;
                case "Product Inventory":
                    $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($prod);
                    $value = ($stock) ? (int)$stock->getQty() : NULL;
                    break;
                case "Product":
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                    $value = ($prod->getAttributeText($attributeCode)) ? $prod->getAttributeText($attributeCode) : $prod->$attr();
                    break;
                case "Custom":
                    if ($attributeCode == "current_url") {
                        $value = Mage::helper('core/url')->getCurrentUrl();
                    } elseif ($attributeCode == "todays_date") {
                        $value = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
                    } elseif ($attributeCode == "todays_timestamp") {
                        $value = gmdate(DATE_ATOM, strtotime(Mage::getModel('core/date')->timestamp(time())));
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
                Mage::helper('tnw_salesforce')->log( $this->_type . ' MAPPING: attribute ' . $sf_field . ' does not have a value in Magento, SKIPPING!');
            }
        }
    }

}