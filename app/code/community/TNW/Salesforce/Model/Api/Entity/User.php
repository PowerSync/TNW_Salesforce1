<?php

class TNW_Salesforce_Model_Api_Entity_User extends TNW_Salesforce_Model_Api_Entity_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce_api_entity/user');
    }
}