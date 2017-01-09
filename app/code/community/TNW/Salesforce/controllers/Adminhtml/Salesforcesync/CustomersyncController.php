<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforcesync_CustomersyncController extends Mage_Adminhtml_Controller_Action
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
            ->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb(Mage::helper('tnw_salesforce')->__('Manual Customer Synchronization'), Mage::helper('tnw_salesforce')->__('Manual Customer Synchronization'));

        return $this;
    }

    /**
     * Index Action
     *
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Manual Sync'))->_title($this->__('Customer'));
        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_customersync'));
        Mage::helper('tnw_salesforce')->addAdminhtmlVersion('TNW_Salesforce');
        $this->renderLayout();
    }

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
            Mage::getSingleton('adminhtml/session')->addError("API Integration is disabled.");
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit", array('section' => 'salesforce')));
            Mage::app()->getResponse()->sendResponse();
        }
        if ($this->getRequest()->getParam('customer_id') > 0) {
            try {
                if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
                    // pass data to local storage
                    // TODO add level up abstract class with Order as static values, now we have word 'Customer' as parameter
                    $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array($this->getRequest()->getParam('customer_id')), 'Customer', 'customer');
                    if (!$res) {
                        Mage::getSingleton('adminhtml/session')->addError('Could not add customer to the queue!');
                    } else {
                        if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                            Mage::getSingleton('adminhtml/session')->addSuccess(
                                Mage::helper('adminhtml')->__('Record was added to synchronization queue!')
                            );
                        }
                    }
                } else {
                    $manualSync = Mage::helper('tnw_salesforce/salesforce_customer');
                    if ($manualSync->reset()) {

                        if ($manualSync->massAdd(array($this->getRequest()->getParam('customer_id')))){
                            $manualSync->process();
                        }
                        if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                            Mage::getSingleton('adminhtml/session')->addSuccess(
                                Mage::helper('adminhtml')->__('Record was syncronized successfully!')
                            );
                        }
                    } else {
                        Mage::getSingleton('adminhtml/session')->addError('Salesforce Connection failed!');
                    }
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirectReferer($this->getUrl('*/*/index', array('_current' => true)));
    }

    public function massSyncAction()
    {
        $session = Mage::getSingleton('adminhtml/session');
        $helper  = Mage::helper('tnw_salesforce');

        if (!$helper->isEnabled()) {
            $session->addError("API Integration is disabled.");
            $this->_redirect("adminhtml/system_config/edit", array('section' => 'salesforce'));
            return;
        }

        $itemIds = $this->getRequest()->getParam('customers');
        if (!is_array($itemIds)) {
            $session->addError($helper->__('Please select customer(s)'));
        } elseif (!$helper->isProfessionalEdition()) {
            $session->addError($helper->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
        } else {

        }
        $this->_redirect('*/*/index');
    }

    /**
     * @param array $entityIds
     */
    protected function syncEntity(array $entityIds)
    {
        /** check empty */
        if (empty($entityIds)) {
            return;
        }

        /** @var Mage_Adminhtml_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');

        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        /** @var Varien_Db_Select $select */
        $select = TNW_Salesforce_Model_Localstorage::generateSelectForType('customer/customer', $entityIds);

        $groupWebsite = array();
        foreach ($select->getAdapter()->fetchAll($select) as $row) {
            $groupWebsite[$row['website_id']][] = $row['object_id'];
        }

        /** @var Mage_Core_Model_App_Emulation $appEmulation */
        $appEmulation = Mage::getSingleton('core/app_emulation');
        foreach ($groupWebsite as $websiteId => $entityIds) {
            $storeId = Mage::app()->getWebsite($websiteId)->getDefaultStore()->getId();
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);

            if (!$helper->isEnabled()) {
                $session->addError(sprintf('API Integration is disabled in Website: %s', Mage::app()->getWebsite($websiteId)->getName()));
            }
            else {
                $syncBulk = (count($entityIds) > 1);

                try {
                    if (count($entityIds) > $helper->getRealTimeSyncMaxCount() || !$helper->isRealTimeType()) {
                        $success = Mage::getModel('tnw_salesforce/localstorage')
                            ->addObject($entityIds, 'Customer', 'customer', $syncBulk);

                        if ($success) {
                            if ($syncBulk) {
                                $session->addNotice($this->__('ISSUE: Too many records selected.'));
                                $session->addSuccess($this->__('Selected records were added into <a href="%s">synchronization queue</a> and will be processed in the background.', $this->getUrl('*/salesforcesync_queue_to/bulk')));
                            }
                            else {
                                $session->addSuccess($this->__('Records are pending addition into the queue!'));
                            }
                        }
                        else {
                            $session->addError('Could not add to the queue!');
                        }
                    }
                    else {
                        /** @var TNW_Salesforce_Helper_Salesforce_Customer $manualSync */
                        $manualSync = Mage::helper(sprintf('tnw_salesforce/%s_customer', $syncBulk ? 'bulk' : 'salesforce'));
                        if ($manualSync->reset() && $manualSync->massAdd($entityIds) && $manualSync->process()) {
                            $session->addSuccess($this->__('Total of %d record(s) were successfully synchronized', count($entityIds)));
                        }
                    }
                } catch (Exception $e) {
                    $session->addError($e->getMessage());
                }
            }

            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
        }
    }
}
