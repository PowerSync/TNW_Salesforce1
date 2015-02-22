<?php

/**
 * Class TNW_Salesforce_Model_Mysql4_Queue_Collection
 */
class TNW_Salesforce_Model_Mysql4_Queue_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/queue');
    }
}