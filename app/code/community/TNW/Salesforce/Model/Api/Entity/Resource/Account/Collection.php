<?php

class TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection
    extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce_api_entity/account');
    }
}