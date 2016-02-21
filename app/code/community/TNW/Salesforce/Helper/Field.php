<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Field extends TNW_Salesforce_Helper_Data
{
    public function getOrderLabels() {
        $labels = array(
            'salesforce_id' =>  'Order',
            'contact_salesforce_id' =>  'Contact',
            'account_salesforce_id' =>  'Account',
        );

        if (Mage::helper('tnw_salesforce')->getOrderObject() != TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_OBJECT) {
            $labels['opportunity_id'] = 'Opportunity';
        }

        return $labels;
    }
}