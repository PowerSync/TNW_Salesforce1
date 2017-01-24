<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforcesync_Campaign_CatalogrulesyncController extends Mage_Adminhtml_Controller_Action
{
    protected function _initLayout()
    {
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
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_campaign_catalogrulesync'));
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
     */
    public function syncAction()
    {
        $catalogruleId = $this->getRequest()->getParam('catalogrule_id');

        $this->syncEntity(array($catalogruleId));
        $this->_redirectReferer();
    }

    public function massSyncAction()
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $itemIds = $this->getRequest()->getParam('catalogrules');
        if (!is_array($itemIds)) {
            $this->_getSession()->addError($helper->__('Please select catalog rule(s)'));
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
        $select = Mage::getSingleton('tnw_salesforce/localstorage')
            ->generateSelectForType('catalogrule/rule', $entityIds);

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
                $this->_getSession()->addError('API Integration is disabled');
            }
            else {
                $syncBulk = (count($entityIds) > 1);

                try {
                    if (count($entityIds) > $helper->getRealTimeSyncMaxCount() || !$helper->isRealTimeType()) {
                        $success = Mage::getModel('tnw_salesforce/localstorage')
                            ->addObject($entityIds, 'Campaign_CatalogRule', 'catalogrule', $syncBulk);

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
                            $this->_getSession()->addError('Could not add catalog rule(s) to the queue!');
                        }
                    }
                    else {
                        /** @var TNW_Salesforce_Helper_Salesforce_Campaign_Catalogrule $campaignMember */
                        $campaignMember = Mage::helper(sprintf('tnw_salesforce/%s_campaign_catalogrule', $syncBulk ? 'bulk' : 'salesforce'));
                        if ($campaignMember->reset() && $campaignMember->massAdd($entityIds) && $campaignMember->process()) {
                            $this->_getSession()->addSuccess($this->__('Total of %d record(s) were successfully synchronized', count($entityIds)));
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
