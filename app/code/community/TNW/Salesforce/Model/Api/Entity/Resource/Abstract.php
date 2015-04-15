<?php

abstract class TNW_Salesforce_Model_Api_Entity_Resource_Abstract extends Mage_Core_Model_Resource_Db_Abstract
{
    protected $_columns = array();

    protected $_resourcePrefix = 'tnw_salesforce_api_entity';

    protected function _getConnection($connectionName)
    {
        return Mage::getSingleton('tnw_salesforce/api_entity_adapter');
    }

    public function getDefaultColumns()
    {
        $columns = $this->_columns;
        if (!isset($this->_columns[$this->getIdFieldName()])) {
            array_unshift($columns, $this->getIdFieldName());
        }

        return (array)$columns;
    }

    /**
     * Retrieve select object for load object data
     *
     * @param string $field
     * @param mixed $value
     * @param Mage_Core_Model_Abstract $object
     * @return Zend_Db_Select
     */
    protected function _getLoadSelect($field, $value, $object)
    {
        $field  = $this->_getReadAdapter()->quoteIdentifier($field);
        $select = $this->_getReadAdapter()->select()
            ->from($this->getMainTable(), array_map(function ($value) {
                return new Zend_Db_Expr($value);
            }, $this->getDefaultColumns()))
            ->where($field . '=?', $value);
        return $select;
    }
}