<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Synchronize_Wishlist extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * TNW_Salesforce_Block_Adminhtml_Synchronize_Wishlist constructor.
     */
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_synchronize_wishlist';
        $this->_headerText = Mage::helper('tnw_salesforce')->__('Wishlist Synchronization');
        parent::__construct();
        $this->removeButton('add');
    }
}
