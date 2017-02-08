<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Mysql4_Import_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/import');
    }

    /**
     * @return $this
     */
    public function filterPending()
    {
        return $this->addFieldToFilter('status', array('eq' => TNW_Salesforce_Model_Import::STATUS_NEW));
    }

    /**
     * @return $this
     */
    public function filterEnding()
    {
        $connection = $this->getConnection();
        $this->getSelect()->where(sprintf('(%s) OR ((%s) AND (%s))',
            $connection->prepareSqlCondition('status', array('eq' => TNW_Salesforce_Model_Import::STATUS_SUCCESS)),
            $connection->prepareSqlCondition('status', array('eq' => TNW_Salesforce_Model_Import::STATUS_ERROR)),
            'DATE_ADD(created_at, INTERVAL 1 HOUR) < NOW()'
        ));

        return $this;
    }

    /**
     * @param string|array $type
     * @return $this
     */
    public function filterObjectType($type)
    {
        $condition = is_array($type) ? array('in' => $type) : array('eq' => $type);
        return $this->addFieldToFilter('object_type', $condition);
    }

    /**
     * @return $this
     */
    public function removeAll()
    {
        $id = $this->getResource()->getIdFieldName();

        $select = clone $this->getSelect();
        $select->reset(Zend_Db_Select::COLUMNS);
        $select->columns($id);

        $query = sprintf("DELETE FROM %s WHERE $id IN (SELECT * FROM(%s) as t)",
            $this->getConnection()->quoteIdentifier($this->getMainTable()),
            $select->assemble());

        $this->getConnection()->query($query);
        return $this;
    }
}
