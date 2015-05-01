<?php

class TNW_Salesforce_Model_Mysql4_Import extends Mage_Core_Model_Mysql4_Abstract
{
    /**
     * Primery key auto increment flag
     *
     * @var bool
     */
    protected $_isPkAutoIncrement    = false;

    public function _construct()
    {
        $this->_init('tnw_salesforce/import', 'import_id');
    }

    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        //generate primary key
        if (!$object->getId()) {
            $object->setId(uniqid("ctmr_", true));
        }

        //save object into json field
        $object->setData('json', serialize($object->getObject()));

        return parent::_beforeSave($object);
    }

    protected function _afterLoad(Mage_Core_Model_Abstract $object)
    {
        $object->setObject(unserialize($object->getData('json')));

        return parent::_afterLoad($object);
    }
}
