<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforce_Campaign_CatalogruleController extends TNW_Salesforce_Controller_Base_Mapping
{
    /**
     * name of  Salesforce object in case sensitive
     * @var string
     */
    protected $_sfEntity = 'Campaign';

    /**
     * name of Local object in case sensitive
     * @var string
     */
    protected $_localEntity = 'CampaignCatalogRule';

    /**
     * path to the blocks which will be rendered by controller
     * can be usefull if Salesforce entity name and block class name are different
     * @var string
     */
    protected $_blockPath = 'campaign_catalogrule';

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('tnw_salesforce/mappings/campaign_mapping/catalogrule_mapping');
    }
}
