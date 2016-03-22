<?php

class TNW_Salesforce_Model_Mysql4_Mapping extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {
        $this->_init('tnw_salesforce/mapping', 'mapping_id');
    }

    /**
     * Perform actions before object delete
     *
     * @param Mage_Core_Model_Abstract $object
     * @return $this
     */
    protected function _beforeDelete(Mage_Core_Model_Abstract $object)
    {
        if (!$object->hasData('is_system')) {
            $this->load($object, $object->getId());
        }

        if ($object->getData('is_system')) {
            Mage::throwException('System attribute cannot be removed');
        }

        return $this;
    }

    /**
     * Perform actions before object save
     *
     * @param Mage_Core_Model_Abstract $object
     * @return $this
     */
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        $object->unsetData('is_system');
        return $this;
    }
}
