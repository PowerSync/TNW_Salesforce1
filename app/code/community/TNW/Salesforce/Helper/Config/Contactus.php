<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Config_Contactus extends TNW_Salesforce_Helper_Config
{
    const EMAIL_TEMPLATE = 'contacts/email/email_template';

    // Create new customers from Salesforce
    public function getDefaultEmailTemplate()
    {
        return $this->getStroreConfig(self::EMAIL_TEMPLATE);
    }
}