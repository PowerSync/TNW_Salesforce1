<?php

class TNW_Salesforce_Model_Queue_Storage extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/queue_storage');
    }
}