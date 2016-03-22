<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Config_Website extends TNW_Salesforce_Helper_Config
{
    const SALESFORCE_WEBSITE_OBJECT = 'Magento_Website__c';
    const SALESFORCE_WEBSITE_OBJECT_PC = 'Magento_Website__pc';

    // Get Salesforce website object
    public function getSalesforceObject($_suffix = '')
    {
        $_constantName = 'self::SALESFORCE_WEBSITE_OBJECT' . strtoupper($_suffix);

        if (defined($_constantName)) {
            return constant($_constantName);
        }

        Mage::throwException('Salesforce Website constant is undefined! Contact PowerSync for resolution.');

        return NULL;
    }
}