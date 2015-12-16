<?php

abstract class TNW_Salesforce_Model_Sync_Mapping_Invoice_Base extends TNW_Salesforce_Model_Sync_Mapping_Order_Base
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
        'Aitoc',
        'Invoice'
    );

    /**
     * @var string
     */
    protected $_cachePrefix = 'invoice';

    /**
     * @var string
     */
    protected $_cacheIdField = 'increment_id';

    /**
     * @param $entity
     * @param $mappingType
     * @param $attributeCode
     * @param $value
     * @return $this
     */
    protected function _fieldMappingBefore($entity, $mappingType, $attributeCode, $value)
    {
        if ($mappingType == "Invoice") {
            //Common attributes
            $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
            $value = $entity->$attr();
        }

        return parent::_fieldMappingBefore($entity, $mappingType, $attributeCode, $value);
    }

    /**
     * @comment Apply mapping rules
     * @param  $entity
     */
    protected function _processMapping($entity = null)
    {
        $order = $entity->getOrder();

        parent::_processMapping($order);
    }

}
