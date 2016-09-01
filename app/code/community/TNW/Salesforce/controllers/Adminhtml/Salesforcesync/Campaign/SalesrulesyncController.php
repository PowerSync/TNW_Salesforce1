<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforcesync_Campaign_SalesrulesyncController extends Mage_Adminhtml_Controller_Action
{
    protected function _initLayout()
    {
        if (!Mage::helper('tnw_salesforce')->isEnabled() || !Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
            Mage::getSingleton('adminhtml/session')
                ->addNotice("Salesforce integration is not working! Refer to the config or the log files for more information.");
        }

        $this->loadLayout()
            ->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb(Mage::helper('tnw_salesforce')->__('Manual Catalog Rule Synchronization'), Mage::helper('tnw_salesforce')->__('Manual Catalog Rule Synchronization'));

        return $this;
    }

    /**
     * Index Action
     *
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Manual Sync'))->_title($this->__('Catalog Rule'));
        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_campaign_salesrulesync'));
        Mage::helper('tnw_salesforce')->addAdminhtmlVersion('TNW_Salesforce');

        $this->renderLayout();
    }

    /**
     * Order grid
     */
    public function gridAction()
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    /**
     * Sync Action
     *
     */
    public function syncAction()
    {
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            Mage::getSingleton('adminhtml/session')
                ->addError("API Integration is disabled.");

            $this->_redirect("adminhtml/system_config/edit", array('section' => 'salesforce'));
            return;
        }

        $salesruleId = $this->getRequest()->getParam('salesrule_id');
        if (empty($salesruleId)) {
            $this->_redirect('*/*/');
            return;
        }

        try {
            if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
                $res = Mage::getModel('tnw_salesforce/localstorage')
                    ->addObject(array($salesruleId), 'Campaign_SalesRule', 'salesrule');

                if (!$res) {
                    Mage::getSingleton('adminhtml/session')->addError('Could not add catalogrule to the queue!');
                }
                else if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(
                        Mage::helper('adminhtml')->__('Rule was added to the queue!')
                    );
                }
            }
            else {
                $campaignMember = Mage::helper('tnw_salesforce/salesforce_campaign_salesrule');
                if ($campaignMember->reset() && $campaignMember->massAdd(array($salesruleId)) && $campaignMember->process()) {
                    $this->_getSession()->addSuccess(Mage::helper('tnw_salesforce')->__('Rule was successfully synchronized'));
                }
            }
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')
                ->addError($e->getMessage());
        }

        $this->_redirectReferer();
    }

    public function massSyncAction()
    {
        set_time_limit(0);
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            Mage::getSingleton('adminhtml/session')->addError("API Integration is disabled.");

            $this->_redirect("adminhtml/system_config/edit", array('section' => 'salesforce'));
            return;
        }

        $itemIds = $this->getRequest()->getParam('salesrules');
        if (!is_array($itemIds)) {
            Mage::getSingleton('adminhtml/session')
                ->addError(Mage::helper('tnw_salesforce')->__('Please select catalog rule(s)'));

            $this->_redirect('*/*/index');
            return;
        }

        if (Mage::helper('tnw_salesforce')->getType() != "PRO") {
            Mage::getSingleton('adminhtml/session')
                ->addError(Mage::helper('tnw_salesforce')->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));

            $this->_redirect('*/*/index');
            return;
        }

        if((Mage::helper('tnw_salesforce')->getObjectSyncType() == 'sync_type_realtime') && (count($itemIds) > 50)) {
            Mage::getSingleton('adminhtml/session')
                ->addError(Mage::helper('tnw_salesforce')->__('For history synchronization containing more than 50 records change configuration to use interval based synchronization.'));

            $this->_redirect('*/*/index');
            return;
        }

        try {
            if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {

                $res = Mage::getModel('tnw_salesforce/localstorage')
                    ->addObject($itemIds, 'Campaign_SalesRule', 'salesrule', (count($itemIds) > 1));

                if (!$res) {
                    Mage::getSingleton('adminhtml/session')->addError('Could not add catalog rule(s) to the queue!');
                }
                else if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(
                        $this->__('Records are pending addition into the queue!')
                    );
                }
            }
            else {
                $campaignMember = Mage::helper('tnw_salesforce/salesforce_campaign_salesrule');
                if ($campaignMember->reset() && $campaignMember->massAdd($itemIds)) {
                    $campaignMember->process();
                }
            }
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/index');
    }
}
