<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Client_Roles
{

    public function toOptionArray()
    {
        $_useCache = Mage::app()->useCache('tnw_salesforce');
        $cache = Mage::app()->getCache();

        if ($_useCache && $cache->load("tnw_salesforce_opportunity_customer_roles")) {
            $_data = unserialize($cache->load("tnw_salesforce_opportunity_customer_roles"));
        } else {
            $_data = Mage::helper('tnw_salesforce')->getCustomerRoles();
            if ($_useCache) {
                $cache->save(serialize($_data), 'tnw_salesforce_opportunity_customer_roles', array("TNW_SALESFORCE"));
            }
        }

        return $_data;
    }
}
