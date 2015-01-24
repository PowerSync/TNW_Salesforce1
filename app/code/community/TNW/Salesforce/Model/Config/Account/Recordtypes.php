<?php

class TNW_Salesforce_Model_Config_Account_Recordtypes
{
    const B2B_BOTH = 0;
    const B2B_ACCOUNT = 1;
    const B2C_ACCOUNT = 2;

    public function toOptionArray()
    {
        $_entity = array();
        if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
            $_entity[] = array(
                'label' => 'Use both B2B and B2C',
                'value' => self::B2B_BOTH
            );
        }

        $_entity[] = array(
            'label' => 'Use B2B as default',
            'value' => self::B2B_ACCOUNT
        );

        if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
            $_entity[] = array(
                'label' => 'Use B2C as default',
                'value' => self::B2C_ACCOUNT
            );
        }
        return $_entity;
    }

}
