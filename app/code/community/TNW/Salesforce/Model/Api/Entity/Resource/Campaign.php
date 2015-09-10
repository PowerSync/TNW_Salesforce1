<?php

class TNW_Salesforce_Model_Api_Entity_Resource_Campaign extends TNW_Salesforce_Model_Api_Entity_Resource_Abstract
{
    protected function _construct()
    {
        $this->_init('tnw_salesforce_api_entity/campaign', 'Id');

        $this->_columns = array(
            'Name'
        );
    }
}