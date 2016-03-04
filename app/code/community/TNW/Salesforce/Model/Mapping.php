<?php

/**
 * @method string getLocalField
 * @method string getLocalFieldType
 * @method string getLocalFieldAttributeCode
 * @method string getSfField
 * @method string getAttributeId
 * @method string getBackendType
 * @method string getSfObject
 * @method string getDefaultValue
 *
 * Class TNW_Salesforce_Model_Mapping
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
        'cart'              => 'order_item',
        'payment'           => 'order_payment',
        'billing item'      => 'invoice_item',
        'shipment item'     => 'shipment_item',
        'product inventory' => 'product_inventory',
        'billing'           => 'address_billing',
        'shipping'          => 'address_shipping',
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
     * @return TNW_Salesforce_Model_Mapping_Type_Abstract
     */
    public function getModelType()
    {
        $type = strtolower($this->getLocalFieldType());
        if (isset($this->_aliasType[$type])) {
            $type = $this->_aliasType[$type];
        }

        return Mage::getSingleton('tnw_salesforce/mapping_type_'.$type)
            ->setMapping($this);
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