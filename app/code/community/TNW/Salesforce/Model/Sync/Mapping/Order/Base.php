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
     * Apply mapping rules
     */
    protected function _processMapping()
    {
        /** @var  $order Mage_Sales_Model_Order */
        $order = func_get_arg(0);
        $cacheId = $order->getData($this->getCacheIdField());

        if (isset($this->_cache[$this->getCachePrefix() . 'Customers'])
            && is_array($this->_cache[$this->getCachePrefix() . 'Customers'])
            && isset($this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId])
        ) {
            $_customer = $this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId];
        } else {
            $this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId] = $this->_getCustomer($order);
            $_customer = $this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId];
        }

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
            $value = $this->_fieldMappingBefore($order, $mappingType, $attributeCode, $value);

            if (!$value) {
                $value = $_map->getValue($objectMappings);
            }

            $value = $this->_fieldMappingAfter($order, $mappingType, $attributeCode, $value);

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
