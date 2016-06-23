<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

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

    /**
     * @param $activate
     * @param array $where
     * @return int
     * @throws Zend_Db_Adapter_Exception
     */
    public function massUpdateEnable($activate, array $where)
    {
        $where = array_map(function ($where) {
            return '(' . implode(' AND ', array_map(function($key, $value) {
                return "$key = '$value'";
            }, array_keys($where), $where)) . ')';
        }, $where);

        return $this->_getConnection('core_write')->update($this->getMainTable(), array(
            'magento_sf_enable' => $activate,
            'sf_magento_enable' => $activate
        ), implode(' OR ', $where));
    }
}
