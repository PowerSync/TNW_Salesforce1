<?php

class TNW_Salesforce_Model_Api_Entity_Resource_Contact extends TNW_Salesforce_Model_Api_Entity_Resource_Abstract
{
    protected function _construct()
    {
        $this->_init('tnw_salesforce_api_entity/contact', 'ID');

        $this->_columns = array(
            'OwnerId',
            'LastName',
            'Email',
        );
    }
}