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
        $entityId = $this->getRequest()->getParam('customer_id');

        $this->syncEntity(array($entityId));
        $this->_redirectReferer($this->getUrl('*/*/index', array('_current' => true)));
    }

    public function massSyncAction()
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $itemIds = $this->getRequest()->getParam('customers');
        if (!is_array($itemIds)) {
            $this->_getSession()->addError($helper->__('Please select customer(s)'));
        } elseif (!$helper->isProfessionalEdition()) {
            $this->_getSession()->addError($helper->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
        } else {
            $this->syncEntity($itemIds);
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
            $website = Mage::app()->getWebsite($websiteId);
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($website->getDefaultStore()->getId());

            if (!$helper->isEnabled()) {
                $this->_getSession()->addError(sprintf('API Integration is disabled in Website: %s', $website->getName()));
            }
            else {
                $syncBulk = (count($entityIds) > 1);

                try {
                    if (count($entityIds) > $helper->getRealTimeSyncMaxCount() || !$helper->isRealTimeType()) {
                        $success = Mage::getModel('tnw_salesforce/localstorage')
                            ->addObject($entityIds, 'Customer', 'customer', $syncBulk);

                        if ($success) {
                            if ($syncBulk) {
                                $this->_getSession()->addNotice($this->__('ISSUE: Too many records selected.'));
                                $this->_getSession()->addSuccess($this->__('Selected records were added into <a href="%s">synchronization queue</a> and will be processed in the background.', $this->getUrl('*/salesforcesync_queue_to/bulk')));
                            }
                            else {
                                $this->_getSession()->addSuccess($this->__('Records are pending addition into the queue!'));
                            }
                        }
                        else {
                            $this->_getSession()->addError('Could not add to the queue!');
                        }
                    }
                    else {
                        /** @var TNW_Salesforce_Helper_Salesforce_Customer $manualSync */
                        $manualSync = Mage::helper(sprintf('tnw_salesforce/%s_customer', $syncBulk ? 'bulk' : 'salesforce'));
                        if ($manualSync->reset() && $manualSync->massAdd($entityIds) && $manualSync->process()) {
                            $this->_getSession()->addSuccess($this->__('Total of %d record(s) were successfully synchronized in Website: %s', count($entityIds), $website->getName()));
                        }
                    }
                } catch (Exception $e) {
                    $this->_getSession()->addError($e->getMessage());
                }
            }

            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
        }
    }
}
