<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Lead_Status
{

    public function toOptionArray()
    {
        $_useCache = Mage::app()->useCache('tnw_salesforce');
        $cache = Mage::app()->getCache();

        if ($_useCache && $cache->load("tnw_salesforce_lead_states")) {
            $_leadStates = unserialize($cache->load("tnw_salesforce_lead_states"));
        } else {
            $_leadStates = Mage::helper('tnw_salesforce')->getLeadStates();
            if ($_useCache) {
                $cache->save(serialize($_leadStates), 'tnw_salesforce_lead_states', array("TNW_SALESFORCE"));
            }
        }

        return $_leadStates;
    }

}
