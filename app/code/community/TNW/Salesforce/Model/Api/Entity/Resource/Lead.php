<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Api_Entity_Resource_Lead extends TNW_Salesforce_Model_Api_Entity_Resource_Abstract
{
    protected function _construct()
    {
        $this->_init('tnw_salesforce_api_entity/lead', 'Id');

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