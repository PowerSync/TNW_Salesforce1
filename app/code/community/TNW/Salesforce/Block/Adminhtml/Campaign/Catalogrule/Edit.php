<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Campaign_Catalogrule_Edit extends TNW_Salesforce_Block_Adminhtml_Base_Edit
{
    /**
     * name of  Salesforce object in case-sensitive case
     * @var string
     */
    protected $_sfEntity = 'Campaign';

    /**
     * @var string
     */
    protected $_controller = 'adminhtml_campaign_catalogrule';
}
