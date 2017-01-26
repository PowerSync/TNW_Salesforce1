<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Mapping_Wishlist_OpportunityLine extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * TNW_Salesforce_Block_Adminhtml_Mapping_Wishlist_Opportunity constructor.
     */
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_mapping_wishlist_opportunityLine';
        $this->_headerText = $this->__('Opportunity Product Mapping');
        $this->_addButtonLabel = $this->__('Add New Mapping');
        parent::__construct();
    }
}
