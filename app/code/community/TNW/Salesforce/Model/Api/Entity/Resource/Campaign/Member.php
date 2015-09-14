<?php

class TNW_Salesforce_Model_Api_Entity_Resource_Campaign_Member extends TNW_Salesforce_Model_Api_Entity_Resource_Abstract
{
    protected function _construct()
    {
        $this->_init('tnw_salesforce_api_entity/campaign_member', 'Id');

        $this->_columns = array(

        );
    }
}