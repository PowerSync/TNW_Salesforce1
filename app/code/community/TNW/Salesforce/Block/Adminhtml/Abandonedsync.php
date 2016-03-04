<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Abandonedsync extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_abandonedsync';
        $this->_headerText = Mage::helper('tnw_salesforce')->__('Abandoned carts Synchronization');
        parent::__construct();
        $this->removeButton('add');
    }
}
