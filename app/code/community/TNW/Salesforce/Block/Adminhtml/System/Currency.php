<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_System_Currency extends Mage_Adminhtml_Block_System_Currency
{
    protected function _prepareLayout()
    {
        $this->setChild('sync_currency',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(array(
                    'label'     => Mage::helper('adminhtml')->__('Synchronize Salesforce'),
                    'onclick'   => 'setLocation(\'' . $this->getUrl('*/salesforcesync_opportunitysync/syncCurrency') .'\')',
                    'class'     => 'save'
                )));

        return parent::_prepareLayout();
    }

    protected function getResetButtonHtml()
    {
        return $this->getChildHtml('sync_currency') .
            (Mage::helper('tnw_salesforce')->isMultiCurrency())
                ? $this->getChildHtml('reset_button') : '';
    }
}
