<?php

/**
 * @method TNW_Salesforce_Model_Api_Entity_Resource_Abstract getResource
 *
 * Class TNW_Salesforce_Model_Api_Entity_Resource_Collection_Abstract
 */
class TNW_Salesforce_Model_Api_Entity_Resource_Collection_Abstract
extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Init collection select
     *
     * @return Mage_Core_Model_Resource_Db_Collection_Abstract
     */
    protected function _initSelect()
    {
        $this->getSelect()->from(
            $this->getMainTable(),
            array_map(function ($value) {
                return new Zend_Db_Expr($value);
            }, $this->getResource()->getDefaultColumns())
        );

        return $this;
    }

    /**
     * Get SQL for get record count
     *
     * @return Varien_Db_Select
     */
    public function getSelectCountSql()
    {
        $this->_renderFilters();

        $countSelect = clone $this->getSelect();
        $countSelect->reset(Zend_Db_Select::ORDER);
        $countSelect->reset(Zend_Db_Select::LIMIT_COUNT);
        $countSelect->reset(Zend_Db_Select::LIMIT_OFFSET);
        $countSelect->reset(Zend_Db_Select::COLUMNS);

        $countSelect->columns('COUNT(' . $this->getResource()->getIdFieldName() . ')');

        return $countSelect;
    }

    /**
     * Get collection size
     *
     * @return int
     */
    public function getSize()
    {
        return count($this);
    }
}