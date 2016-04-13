<?php

class TNW_Salesforce_Model_Api_Entity_Resource_Currency extends TNW_Salesforce_Model_Api_Entity_Resource_Abstract
{
    protected function _construct()
    {
        $this->_init('tnw_salesforce_api_entity/currency', 'Id');

        $this->_columns = array(
            'ConversionRate',
            'DecimalPlaces',
            'IsActive',
            'IsCorporate',
            'IsoCode',
        );
    }
}