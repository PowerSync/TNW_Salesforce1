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

        if (isset($this->_cache[$this->getCachePrefix() . 'Customers'])
            && is_array($this->_cache[$this->getCachePrefix() . 'Customers'])
            && isset($this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId])
        ) {
            $_customer = $this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId];
        } else {
            $this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId] = $this->_getCustomer($entity);
            $_customer = $this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId];
        }

        $order = $entity;
        $objectMappings = array(
            'Store' => $order->getStore(),
            'Order' => $order,
            'Payment' => $order->getPayment(),
            'Customer' => $_customer,
            'Customer Group' => Mage::getModel('customer/group')->load($_customer->getGroupId()),
            'Billing' => $order->getBillingAddress(),
            'Shipping' => $order->getShippingAddress(),
        );

        foreach ($this->getMappingCollection() as $_map) {
            /** @var TNW_Salesforce_Model_Mapping  $_map */

            $mappingType = $_map->getLocalFieldType();
            $attributeCode = $_map->getLocalFieldAttributeCode();

            if (!$this->_mappingTypeAllowed($mappingType)) {
                continue;
            }

            $sf_field = $_map->getSfField();

            $value = '';
            $value = $this->_fieldMappingBefore($entity, $mappingType, $attributeCode, $value);

            switch ($mappingType) {
                case "Order":
                case "Cart":
                case "Quote":
                    if ($attributeCode == "number") {
                        $value = $cacheId;
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
            }

            if (!$value) {
                $value = $_map->getValue($objectMappings);
            }

            $value = $this->_fieldMappingAfter($entity, $mappingType, $attributeCode, $value);

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
     * @return false|Mage_Core_Model_Abstract|null
     */
    protected function _getCustomer($_entity)
    {
        return $this->getSync()->getCustomer($_entity);
    }
}
