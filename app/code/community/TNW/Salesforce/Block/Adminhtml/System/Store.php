<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_System_Store extends Mage_Adminhtml_Block_System_Store_Store
{
    protected function _prepareLayout()
    {
        $this->_addButton('sync_websites', array(
            'label'     => Mage::helper('core')->__('Synchronize w/ Salesforce'),
            'onclick'   => 'setLocation(\'' . $this->getUrl('*/salesforcesync_opportunitysync/syncWebsites') .'\')',
            'class'     => 'add',
        ));

        return parent::_prepareLayout();
    }
}
