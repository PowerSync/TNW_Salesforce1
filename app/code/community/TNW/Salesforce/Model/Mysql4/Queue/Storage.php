<?php

/**
 * Class TNW_Salesforce_Model_Mysql4_Queue_Storage
 */
class TNW_Salesforce_Model_Mysql4_Queue_Storage extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {
        $this->_init('tnw_salesforce/queue_storage', 'id');
    }
}