<?php

class TNW_Salesforce_Helper_Config_Contactus extends TNW_Salesforce_Helper_Config
{
    const EMAIL_TEMPLATE = 'contacts/email/email_template';

    // Create new customers from Salesforce
    public function getDefaultEmailTemplate()
    {
        return $this->getStroreConfig(self::EMAIL_TEMPLATE);
    }
}