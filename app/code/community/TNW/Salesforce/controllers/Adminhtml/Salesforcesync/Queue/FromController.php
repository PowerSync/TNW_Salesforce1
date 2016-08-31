<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforcesync_Queue_FromController extends Mage_Adminhtml_Controller_Action
{
    /**
     * @return $this
     */
    protected function _initLayout()
    {
        if (!Mage::helper('tnw_salesforce')->isEnabled() || !Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
            Mage::getSingleton('adminhtml/session')->addNotice("Salesforce integration is not working! Refer to the config or the log files for more information.");
        }

        $this->loadLayout()
            ->_setActiveMenu('tnw_salesforce');

        return $this;
    }

    /**
     * Index Action
     *
     */
    public function indexAction()
    {
        if (Mage::helper('tnw_salesforce')->getType() != "PRO") {
            Mage::getSingleton('adminhtml/session')
                ->addError("Please upgrade Powersync module to the enterprise version in order to use queue.");

            $this->_redirect("adminhtml/system_config/edit", array('section' => 'salesforce'));
            return;
        }

        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Manual Sync'))->_title($this->__('Queue Objects Synchronization'));
        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_queue_from'));
        Mage::helper('tnw_salesforce')->addAdminhtmlVersion('TNW_Salesforce');

        $this->renderLayout();
    }

    /**
     * Grid Action
     */
    public function gridAction()
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }
}