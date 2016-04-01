<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Campaign_Salesrule_Grid extends TNW_Salesforce_Block_Adminhtml_Base_Grid
{
    /**
     * name of  Salesforce object in case-sensitive case
     * @var string
     */
    protected $_sfEntity = 'Campaign';

    /**
     * name of Local object in case sensitive
     * @var string
     */
    protected $_localEntity = 'CampaignSalesRule';
}
