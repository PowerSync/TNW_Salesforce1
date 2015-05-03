<?php

/**
 * Class TNW_Salesforce_Helper_Field
 */
class TNW_Salesforce_Helper_Field extends TNW_Salesforce_Helper_Data
{
    public function getOrderLabels() {
        return array(
            'salesforce_id' =>  'Order',
            'contact_salesforce_id' =>  'Contact',
            'account_salesforce_id' =>  'Account',
        );
    }
}