<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 10.03.15
 * Time: 22:18
 */
abstract class TNW_Salesforce_Model_Sync_Mapping_Order_Base extends TNW_Salesforce_Model_Sync_Mapping_Abstract_Base
{


    /**
     * @comment list of the allowed mapping types
     * @var array
     */
    protected $_allowedMappingTypes = array(
        'Customer',
        'Billing',
        'Shipping',
        'Custom',
        'Order',
        'Customer Group',
        'Payment',
        'Aitoc'
    );

    /**
     * @var string
     */
    protected $_cachePrefix = 'order';

    /**
     * @var string
     */
    protected $_cacheIdField = 'increment_id';

    /**
     * @comment Apply mapping rules
     * @param Mage_Sales_Model_Order $entity
     */
    protected function _processMapping($entity = null)
    {

        $cacheId = $entity->getData($this->getCacheIdField());

        if (is_array($this->_cache[$this->getCachePrefix() . 'Customers']) && array_key_exists($cacheId, $this->_cache[$this->getCachePrefix() . 'Customers'])) {
            $_customer = $this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId];
        } else {
            $this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId] = $this->_getCustomer($entity);
            $_customer = $this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId];
        }

        if ($_customer->getGroupId()) {

            if (!isset($this->_customerGroups[$_customer->getGroupId()])) {
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

                    $attrName = str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                    if ($attrName == "Email") {
                        $email = $entity->getCustomerEmail();
                        if (!$email) {
                            //TODO: add email to the order via direct SQL
                            $email = $_customer->getEmail();
                        }
                        $value = $email;
                    } else {
                        $attr = "get" . $attrName;

                        // Make sure getAttribute is called on the object
                        if ($_customer->getAttribute($attributeCode)->getFrontendInput() == "select" && is_object($_customer->getResource())) {
                            $newAttribute = $_customer->getResource()->getAttribute($attributeCode)->getSource()->getOptionText($_customer->$attr());
                        } else {
                            $newAttribute = $_customer->$attr();
                        }

                        // Reformat date fields
                        if ($_map->getBackendType() == "datetime" || $attributeCode == 'created_at') {
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
                        unset($attributeInfo);
                    }
                    break;
                case "Billing":
                case "Shipping":
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                    $var = 'get' . $mappingType . 'Address';
                    if (is_object($entity->$var())) {
                        $value = $entity->$var()->$attr();
                        if (is_array($value)) {
                            $value = implode(", ", $value);
                        }
                    }
                    break;
                case "Custom":
                    $store = ($entity->getStoreId()) ? Mage::getModel('core/store')->load($entity->getStoreId()) : NULL;
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
                case "Order":
                case "Cart":
                    if ($attributeCode == "cart_all") {
                        $value = $this->_getDescriptionCart($entity);
                    } elseif ($attributeCode == "number") {
                        $value = $cacheId;
                    } elseif ($attributeCode == "created_at") {
                        $value = ($entity->getCreatedAt()) ? gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($entity->getCreatedAt()))) : date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
                    } elseif ($attributeCode == "payment_method") {
                        if (is_object($entity->getPayment())) {
                            $paymentMethods = Mage::helper('payment')->getPaymentMethodList(true);
                            $method = $entity->getPayment()->getMethod();
                            if (array_key_exists($method, $paymentMethods)) {
                                $value = $paymentMethods[$method];
                            } else {
                                $value = $method;
                            }
                        } else {
                            Mage::helper('tnw_salesforce')->log($this->_type . ' MAPPING: Payment Method is not set in magento for the order: ' . $cacheId . ', SKIPPING!');
                        }
                    } elseif ($attributeCode == "notes") {
                        $allNotes = NULL;
                        foreach ($entity->getStatusHistoryCollection() as $_comment) {
                            $comment = trim(strip_tags($_comment->getComment()));
                            if (!$comment || empty($comment)) {
                                continue;
                            }
                            if (!$allNotes) {
                                $allNotes = "";
                            }
                            $allNotes .= Mage::helper('core')->formatTime($_comment->getCreatedAtDate(), 'medium') . " | " . $_comment->getStatusLabel() . "\n";
                            $allNotes .= strip_tags($_comment->getComment()) . "\n";
                            $allNotes .= "-----------------------------------------\n\n";
                        }
                        $value = $allNotes;
                    } else {
                        //Common attributes
                        $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                        $value = ($entity->getAttributeText($attributeCode)) ? $entity->getAttributeText($attributeCode) : $entity->$attr();
                        break;
                    }
                    break;
                case "Customer Group":
                    //Common attributes
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                    $value = $this->_customerGroups[$_customer->getGroupId()]->$attr();
                    break;
                case "Payment":
                    //Common attributes
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                    $value = $entity->getPayment()->$attr();
                    break;
                case "Aitoc":
                    $modules = Mage::getConfig()->getNode('modules')->children();
                    $value = NULL;
                    if (property_exists($modules, 'Aitoc_Aitcheckoutfields')) {
                        $aCustomAtrrList = Mage::getModel('aitcheckoutfields/transport')->loadByOrderId($entity->getId());
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
                default:
                    break;
            }
            if ($value) {
                $this->getObj()->$sf_field = trim($value);
            } else {
                Mage::helper('tnw_salesforce')->log($this->_type . ' MAPPING: attribute ' . $sf_field . ' does not have a value in Magento, SKIPPING!');
            }
        }
        unset($collection, $_map);
    }


    /**
     * @param $_entity
     * @return string
     * Construct and return a text version of the shopping cart
     */
    protected function _getDescriptionCart($_entity)
    {
        $_currencyCode = '';
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $_currencyCode = $_entity->getData('order_currency_code') . " ";
        }

        ## Put Products into Single field
        $descriptionCart = "";
        $descriptionCart .= "Items ordered:\n";
        $descriptionCart .= "=======================================\n";
        $descriptionCart .= "SKU, Qty, Name";
        $descriptionCart .= ", Price";
        $descriptionCart .= ", Tax";
        $descriptionCart .= ", Subtotal";
        $descriptionCart .= ", Net Total";
        $descriptionCart .= "\n";
        $descriptionCart .= "=======================================\n";

        //foreach ($_entity->getAllItems() as $itemId=>$item) {
        foreach ($_entity->getAllVisibleItems() as $itemId => $item) {
            $descriptionCart .= $item->getSku() . ", " . number_format($item->getQtyOrdered()) . ", " . $item->getName();
            //Price
            $unitPrice = number_format(($item->getPrice()), 2, ".", "");
            $descriptionCart .= ", " . $_currencyCode . $unitPrice;
            //Tax
            $tax = number_format(($item->getTaxAmount()), 2, ".", "");
            $descriptionCart .= ", " . $_currencyCode . $tax;
            //Subtotal
            $subtotal = number_format((($item->getPrice() + $item->getTaxAmount()) * $item->getQtyOrdered()), 2, ".", "");
            $descriptionCart .= ", " . $_currencyCode . $subtotal;
            //Net Total
            $netTotal = number_format(($subtotal - $item->getDiscountAmount()), 2, ".", "");
            $descriptionCart .= ", " . $_currencyCode . $netTotal;
            $descriptionCart .= "\n";
        }
        $descriptionCart .= "=======================================\n";
        $descriptionCart .= "Sub Total: " . $_currencyCode . number_format(($_entity->getSubtotal()), 2, ".", "") . "\n";
        $descriptionCart .= "Tax: " . $_currencyCode . number_format(($_entity->getTaxAmount()), 2, ".", "") . "\n";
        $descriptionCart .= "Shipping (" . $_entity->getShippingDescription() . "): " . $_currencyCode . number_format(($_entity->getShippingAmount()), 2, ".", "") . "\n";
        $descriptionCart .= "Discount Amount : " . $_currencyCode . number_format($_entity->getGrandTotal() - ($_entity->getShippingAmount() + $_entity->getTaxAmount() + $_entity->getSubtotal()), 2, ".", "") . "\n";
        $descriptionCart .= "Total: " . $_currencyCode . number_format(($_entity->getGrandTotal()), 2, ".", "");
        $descriptionCart .= "\n";

        return $descriptionCart;
    }

    /**
     * @param $_entity
     * @return false|Mage_Core_Model_Abstract|null
     */
    protected function _getCustomer($_entity)
    {
        return $this->getSync()->getCustomer($_entity);
    }
}