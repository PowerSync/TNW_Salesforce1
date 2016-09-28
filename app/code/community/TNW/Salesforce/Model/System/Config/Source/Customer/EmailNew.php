<?php

class TNW_Salesforce_Model_System_Config_Source_Customer_EmailNew
{
    const SEND_PASSWORD = 'send_password';
    const SEND_WELCOME  = 'send_welcome';
    const NOT_SEND      = 'not_send';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'label' => Mage::helper('tnw_salesforce')->__('Send email with the password'),
                'value' => self::SEND_PASSWORD
            ),
            array(
                'label' => Mage::helper('tnw_salesforce')->__('Send a welcome and password emails'),
                'value' => self::SEND_WELCOME
            ),
            array(
                'label' => Mage::helper('tnw_salesforce')->__('Do not send any emails'),
                'value' => self::NOT_SEND
            ),
        );
    }
}