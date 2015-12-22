<?php

abstract class TNW_Salesforce_Model_Sync_Mapping_Shipment_Base extends TNW_Salesforce_Model_Sync_Mapping_Abstract_Base
{
    /**
     * @comment list of the allowed mapping types
     * @var array
     */
    protected $_allowedMappingTypes = array(
        'Customer',
        'Customer Group',
        'Custom',
        'Order',
        'Payment',
        'Billing',
        'Shipping',
    );

    /**
     * @var string
     */
    protected $_cachePrefix = 'shipment';

    /**
     * @var string
     */
    protected $_cacheIdField = 'increment_id';

    /**
     * @comment Gets field mapping from Magento and creates Shipment object
     * @param Mage_Sales_Model_Order_Shipment $entity
     * @param null $additionalObject
     */
    protected function _processMapping($entity = null, $additionalObject = null)
    {
        $cacheId = $entity->getData($this->getCacheIdField());
        if (isset($this->_cache[$this->getCachePrefix() . 'Customers'])
            && is_array($this->_cache[$this->getCachePrefix() . 'Customers'])
            && isset($this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId])
        ) {
            $_customer = $this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId];
        } else {
            $this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId] = $this->_getCustomer($entity->getOrder());
            $_customer = $this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId];
        }

        $_groupId = ($entity->getOrder()->getCustomerGroupId() !== NULL)
            ? $entity->getOrder()->getCustomerGroupId()
            : $entity->getOrder()->getGroupId();

        $objectMappings = array(
            'Store'    => $entity->getStore(),
            'Order'    => $entity->getOrder(),
            'Payment'  => $entity->getOrder()->getPayment(),
            'Customer' => $_customer,
            'Customer Group' => Mage::getModel('customer/group')->load($_groupId),
            'Billing'  => $entity->getBillingAddress(),
            'Shipping' => $entity->getShippingAddress(),
        );

        /** @var TNW_Salesforce_Model_Mapping $_map */
        foreach ($this->getMappingCollection() as $_map) {
            $value         = false;
            $mappingType   = $_map->getLocalFieldType();
            $attributeCode = $_map->getLocalFieldAttributeCode();

            if (!$this->_mappingTypeAllowed($mappingType)) {
                continue;
            }

            $value         = $this->_fieldMappingBefore($entity, $mappingType, $attributeCode, $value);
            if (!$value) {
                $value = $_map->getValue($objectMappings);
            }

            $sf_field      = $_map->getSfField();
            $value         = $this->_fieldMappingAfter($entity, $mappingType, $attributeCode, $value);
            if (!$value) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($this->_type . ' MAPPING: attribute ' . $sf_field . ' does not have a value in Magento, SKIPPING!');
                continue;
            }

            $this->getObj()->$sf_field = trim($value);
            $this->setBreak(false);
        }
    }
}