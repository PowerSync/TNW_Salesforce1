<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforce_Wishlist_OpportunitylineController extends TNW_Salesforce_Controller_Base_Mapping
{
    /**
     * name of  Salesforce object in case sensitive
     * @var string
     */
    protected $_sfEntity = 'OpportunityLineItem';

    /**
     * @var string
     */
    protected $_localEntity = 'WishlistOpportunityLine';

    /**
     * path to the blocks which will be rendered by
     * @var string
     */
    protected $_blockPath = 'mapping_wishlist_opportunityLine';

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('tnw_salesforce/mappings/wishlist_mapping/entity_item');
    }
}
