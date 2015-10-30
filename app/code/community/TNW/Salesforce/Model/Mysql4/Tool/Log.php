<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 * Date: 29.10.15
 * Time: 14:42
 */
class TNW_Salesforce_Model_Mysql4_Tool_Log extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Crear table if more that this count of records exists
     */
    const TOTAL_RECORD_LIMIT = 1000;

    /**
     * if we have more that TOTAL_RECORD_LIMIT records in table - remove following records count
     */
    const RECORD_TO_CLEAR_LIMIT = 100;

    /**
     * @var array
     */
    protected static $checkTable = true;

    /**
     * prepare object
     */
    protected function _construct()
    {
        $this->_init('tnw_salesforce/log', 'entity_id');
    }

    /**
     * Perform actions before object save
     *
     * @param Varien_Object $object
     * @return Mage_Core_Model_Resource_Db_Abstract
     */
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        /**
         * clear table if it contains too match records
         */
        if (self::$checkTable) {

            $count = $this->_getReadAdapter()->fetchOne('SELECT COUNT(*) FROM `'.$this->getMainTable().'`');

            if ($count > self::TOTAL_RECORD_LIMIT) {
                $this->_getWriteAdapter()->query(
                    'DELETE FROM `'.$this->getMainTable().'` ORDER BY `'.$this->getIdFieldName().'` ASC LIMIT ' . self::RECORD_TO_CLEAR_LIMIT
                );
            }
            self::$checkTable = false;
        }

        return parent::_beforeSave($object);
    }

}