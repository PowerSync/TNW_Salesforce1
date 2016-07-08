<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

/**
 * @method string getSfField()
 * @method string getLocalField()
 * @method string getBackendType()
 * @method string getLocalFieldType()
 * @method string getLocalFieldAttributeCode()
 * @method string getDefaultValue()
 */
class TNW_Salesforce_Model_Mapping extends Mage_Core_Model_Abstract
{
    const SET_TYPE_UPSERT = 'upsert';
    const SET_TYPE_INSERT = 'insert';
    const SET_TYPE_UPDATE = 'update';

    /**
     * @var array
     */
    protected $_aliasType = array(
        'payment'           => 'order_payment',
        'invoice'           => 'order_invoice',
        'billing item'      => 'order_invoice_item',
        'shipment'          => 'order_shipment',
        'shipment item'     => 'order_shipment_item',
        'credit memo'       => 'order_creditmemo',
        'credit memo item'  => 'order_creditmemo_item',
        'billing'           => 'address_billing',
        'shipping'          => 'address_shipping',
        'shopping cart rule'=> 'sales_rule',
    );

    /**
     * @param array $objectMappings
     * @return null|string
     */
    public function getValue(array $objectMappings = array())
    {
        if (!isset($objectMappings[$this->getLocalFieldType()])) {
            return null;
        }

        return $this->getModelType()
            ->getValue($objectMappings[$this->getLocalFieldType()]);
    }

    /**
     * @param $objectMappings Mage_Core_Model_Abstract[]
     * @param $value string
     * @return Mage_Core_Model_Abstract|null
     */
    public function setValue($objectMappings, $value)
    {
        if (!isset($objectMappings[$this->getLocalFieldType()])) {
            return;
        }

        $this->getModelType()
            ->setValue($objectMappings[$this->getLocalFieldType()], $value);
    }

    /**
     * @return TNW_Salesforce_Model_Mapping_Type_Abstract
     */
    public function getModelType()
    {
        $type = strtolower($this->getLocalFieldType());
        if (isset($this->_aliasType[$type])) {
            $type = $this->_aliasType[$type];
        }

        $typeModel = Mage::getSingleton(sprintf('tnw_salesforce/mapping_type_%s', str_replace(' ', '_', trim($type))));
        if (!$typeModel instanceof TNW_Salesforce_Model_Mapping_Type_Abstract) {
            Mage::throwException(sprintf('Unknown mapping type "%s"', $type));
        }

        return $typeModel->setMapping($this);
    }

    protected function _construct()
    {
        parent::_construct();

        $this->_init('tnw_salesforce/mapping');
    }

    protected function _afterLoad()
    {
        parent::_afterLoad();

        $cutLocalField = explode(" : ", $this->getLocalField());
        if (count($cutLocalField) > 1) {
            $this->setLocalFieldType($cutLocalField[0]);
            $this->setLocalFieldAttributeCode($cutLocalField[1]);
        }

        return $this;
    }

    /**
     * @param $_type
     * @return string
     */
    public function getNameBySetType($_type)
    {
        switch($_type) {
            case self::SET_TYPE_INSERT:
                return Mage::helper('tnw_salesforce')->__('Insert Only');

            case self::SET_TYPE_UPDATE:
                return Mage::helper('tnw_salesforce')->__('Update Only');

            case self::SET_TYPE_UPSERT:
                return Mage::helper('tnw_salesforce')->__('Upsert');

            default:
                return Mage::helper('tnw_salesforce')->__('Unknown');
        }
    }
}