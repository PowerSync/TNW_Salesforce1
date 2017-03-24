<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Mapping_Wishlist_OpportunityLine_Grid extends TNW_Salesforce_Block_Adminhtml_Base_Grid
{

    /**
     * name of  Salesforce object in lower case
     * @var string
     */
    protected $_sfEntity = 'OpportunityLineItem';

    /**
     * name of Local object in case sensitive
     * @var string
     */
    protected $_localEntity = 'WishlistOpportunityLine';
}
