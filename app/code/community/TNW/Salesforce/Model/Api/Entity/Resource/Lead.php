<?php

class TNW_Salesforce_Model_Api_Entity_Resource_Lead extends TNW_Salesforce_Model_Api_Entity_Resource_Abstract
{
    protected function _construct()
    {
        $this->_init('tnw_salesforce_api_entity/lead', 'ID');

        $configHelper = Mage::helper('tnw_salesforce/config');
        $this->_columns = array(
            'OwnerId',
            'Email',
            'IsConverted',
            'ConvertedAccountId',
            'ConvertedContactId',
            $configHelper->getMagentoIdField(),
            $configHelper->getMagentoWebsiteField(),
        );
    }
}