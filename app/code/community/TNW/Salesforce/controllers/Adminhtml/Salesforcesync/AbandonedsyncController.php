<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforcesync_AbandonedsyncController extends Mage_Adminhtml_Controller_Action
{

    protected function _initLayout()
    {
        $this->loadLayout()
            ->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb(Mage::helper('tnw_salesforce')->__('Manual Abandoned cart Synchronization'), Mage::helper('tnw_salesforce')->__('Manual Abandoned cart Synchronization'));

        return $this;
    }

    /**
     * Index Action
     *
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Manual Sync'))->_title($this->__('Abandoneds'));
        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_synchronize_abandoned'));
        Mage::helper('tnw_salesforce')->addAdminhtmlVersion('TNW_Salesforce');

        $this->renderLayout();
    }

    /**
     * Abandoned grid
     */
    public function gridAction()
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    /**
     * Sync Action
     * @throws Exception
     */
    public function syncAction()
    {
        $entityId = $this->getRequest()->getParam('abandoned_id');
        Mage::getSingleton('tnw_salesforce/abandoned')->syncAbandoned(array($entityId));

        $this->_redirectReferer();
    }

    public function massSyncForceAction()
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $itemIds = $this->getRequest()->getParam('abandoneds');
        if (!is_array($itemIds)) {
            $this->_getSession()->addError($helper->__('Please select abandoneds(s)'));
        } elseif (!$helper->isProfessionalEdition()) {
            $this->_getSession()->addError($helper->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
        } else {
            Mage::getSingleton('tnw_salesforce/abandoned')->syncAbandoned($itemIds, true);
        }

        $this->_redirect('*/*/index');
    }
}
