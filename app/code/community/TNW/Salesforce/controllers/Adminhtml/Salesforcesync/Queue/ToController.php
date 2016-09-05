<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforcesync_Queue_ToController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var array
     */
    protected $_publicActions = array('grid', 'index');

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
     * Outgoing Grid Action
     *
     */
    public function outgoingAction()
    {
        if (Mage::helper('tnw_salesforce')->getType() != "PRO") {
            Mage::getSingleton('adminhtml/session')
                ->addError("Please upgrade Powersync module to the enterprise version in order to use queue.");

            $this->_redirect("adminhtml/system_config/edit", array('section' => 'salesforce'));
            return;
        }
        if (Mage::getModel('tnw_salesforce/queue')->getCollection()->count() > 0) {
            Mage::getSingleton('adminhtml/session')->addNotice("One or more records are still pending to be added to the synchronization queue. Check back later if you don't see records you are looking for...");
        }
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Manual Sync'))->_title($this->__('Queue Objects Synchronization'));

        /** @var TNW_Salesforce_Block_Adminhtml_Queue_To $block */
        $block = $this->getLayout()->createBlock('tnw_salesforce/adminhtml_queue_to', null, array(
            'type' => TNW_Salesforce_Block_Adminhtml_Queue_To::TYPE_OUTGOING
        ));

        $this->_initLayout()->_addContent($block);
        Mage::helper('tnw_salesforce')->addAdminhtmlVersion('TNW_Salesforce');

        $this->renderLayout();
    }

    /**
     * Outgoing Grid Action
     *
     */
    public function bulkAction()
    {
        if (Mage::helper('tnw_salesforce')->getType() != "PRO") {
            Mage::getSingleton('adminhtml/session')
                ->addError("Please upgrade Powersync module to the enterprise version in order to use queue.");

            $this->_redirect("adminhtml/system_config/edit", array('section' => 'salesforce'));
            return;
        }
        if (Mage::getModel('tnw_salesforce/queue')->getCollection()->count() > 0) {
            Mage::getSingleton('adminhtml/session')->addNotice("One or more records are still pending to be added to the synchronization queue. Check back later if you don't see records you are looking for...");
        }
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Manual Sync'))->_title($this->__('Queue Objects Synchronization'));

        /** @var TNW_Salesforce_Block_Adminhtml_Queue_To $block */
        $block = $this->getLayout()->createBlock('tnw_salesforce/adminhtml_queue_to', null, array(
            'type' => TNW_Salesforce_Block_Adminhtml_Queue_To::TYPE_BULK
        ));

        $this->_initLayout()->_addContent($block);
        Mage::helper('tnw_salesforce')->addAdminhtmlVersion('TNW_Salesforce');

        $this->renderLayout();
    }

    public function deleteAction()
    {
        if ($this->getRequest()->getParam('queue_id') > 0) {
            try {
                Mage::getModel('tnw_salesforce/localstorage')->deleteObject(array($this->getRequest()->getParam('queue_id')), true);
                if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('tnw_salesforce')->__('Queued item was successfully removed!'));
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect($this->_getRedirectUrl());
    }

    public function massDeleteAction()
    {
        $itemIds = $this->getRequest()->getParam('queue');
        if (!is_array($itemIds)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('Please select item(s) in the queue'));
        } else {
            try {
                Mage::getModel('tnw_salesforce/localstorage')->deleteObject($itemIds, true);
                if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('tnw_salesforce')->__('Successfully removed selected queued item(s)!'));
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect($this->_getRedirectUrl());
    }

    public function massResyncAction()
    {
        $itemIds = $this->getRequest()->getParam('queue');
        if (!is_array($itemIds)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('Please select item(s) in the queue'));
        } else {
            try {
                Mage::getModel('tnw_salesforce/localstorage')->updateObjectStatusById($itemIds, 'new');
                if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('tnw_salesforce')->__('Queued item(s) updated!'));
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect($this->_getRedirectUrl());
    }

    public function processAction()
    {
        if ($this->getRequest()->getParam('queue_id') > 0) {
            if (Mage::helper("tnw_salesforce/queue")->processItems(array($this->getRequest()->getParam('queue_id')))){
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper("tnw_salesforce")->__("Item where processed"));
            }
        }
        $this->_redirect($this->_getRedirectUrl());
    }

    public function massProcessAction()
    {
        $itemIds = $this->getRequest()->getParam('queue');
        if (!is_array($itemIds)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('Please select item(s) in the queue'));
        } else {
            if (Mage::helper("tnw_salesforce/queue")->processItems($itemIds)){
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper("tnw_salesforce")->__("Items where processed"));
            }
        }
        $this->_redirect($this->_getRedirectUrl());
    }

    /**
     * returns redirect url with correct store Id
     *
     * @return string
     */
    protected function _getRedirectUrl()
    {
        $url = '*/*/index';
        if (Mage::helper('tnw_salesforce')->getStoreId() != 0) {
            $url .= '/store/' . Mage::helper('tnw_salesforce')->getStoreId();
        }
        return $url;
    }
}