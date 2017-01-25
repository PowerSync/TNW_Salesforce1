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
     * @param $websiteId
     * @return array
     */
    public function toArraySearchById($id, $objectType, $websiteId)
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), array('id', 'name'))
            ->where('id LIKE :id')
            ->where('object_type = :object_type')
            ->where('website_id = :website_id');

        $rowSet = $adapter->fetchAll($select, array(
            ':id' => $id,
            ':object_type' => $objectType,
            ':website_id' => $websiteId
        ));

        return array_map(function ($item) {
            return array(
                'value' => $item['id'],
                'label' => $item['name']
            );
        }, $rowSet);
    }

    /**
     * @param array $data
     * @return int
     */
    public function massImport(array $data)
    {
        return $this->_getWriteAdapter()
            ->insertOnDuplicate($this->getMainTable(), $data, array('name'));
    }

    /**
     * @param $type
     * @param $websiteId
     */
    public function clearType($type, $websiteId)
    {
        $this->_getWriteAdapter()->delete($this->getMainTable(), array('object_type = ?'=>$type, 'website_id = ?'=>$websiteId));
    }

    /**
     *
     */
    public function clearAll()
    {
        $this->_getWriteAdapter()->truncateTable($this->getMainTable());
    }
}