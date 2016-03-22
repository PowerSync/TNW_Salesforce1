<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Api_Entity_Resource_Opportunity extends TNW_Salesforce_Model_Api_Entity_Resource_Abstract
{
    protected function _construct()
    {
        $this->_init('tnw_salesforce_api_entity/opportunity', 'Id');
//        $configHelper = Mage::helper('tnw_salesforce/config');
//        $_magentoId = $configHelper->getSalesforcePrefix() . "Magento_ID__c";


        $this->_columns = array(
//            "AccountId",
//            "Pricebook2Id",
//            "OwnerId",
//            $_magentoId,
//            "(SELECT ID, MasterLabel FROM OpportunityStage)"
        );
    }
}