<?php

class TNW_Salesforce_Model_Mysql4_Entity_Cache extends Mage_Core_Model_Mysql4_Abstract
{
    /**
     * Primery key auto increment flag
     *
     * @var bool
     */
    protected $_isPkAutoIncrement    = false;

    /**
     *
     */
    public function _construct()
    {
        $this->_init('tnw_salesforce/entity_cache', 'id');
    }

    /**
     * @param $id
     * @param $objectType
     * @return array
     */
    public function toArraySearchById($id, $objectType)
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), array('id', 'name'))
            ->where('id LIKE :id')
            ->where('object_type = :object_type');

        $rowSet = $adapter->fetchAll($select, array(
            ':id' => $id,
            ':object_type' => $objectType
        ));

        return array_map(function ($item) {
            return array(
                'value' => $item['id'],
                'label' => $item['name']
            );
        }, $rowSet);
    }
}