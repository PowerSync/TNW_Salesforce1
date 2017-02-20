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
     * @throws Exception
     */
    protected function syncEntity(array $entityIds)
    {
        $groupWebsite = array();
        foreach (array_chunk($entityIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT) as $_entityIds) {
            /** @var Varien_Db_Select $select */
            $select = Mage::getSingleton('tnw_salesforce/localstorage')
                ->generateSelectForType('catalogrule/rule', $_entityIds);

            foreach ($select->getAdapter()->fetchAll($select) as $row) {
                $groupWebsite[$row['website_id']][] = $row['object_id'];
            }
        }

        foreach ($groupWebsite as $websiteId => $entityIds) {
            Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($websiteId, function () use($entityIds) {
                /** @var TNW_Salesforce_Helper_Data $helper */
                $helper = Mage::helper('tnw_salesforce');

                if (!$helper->isEnabled()) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveTrace('API Integration is disabled');

                    return;
                }

                try {
                    if (!$helper->isRealTimeType() || count($entityIds) > $helper->getRealTimeSyncMaxCount()) {
                        $syncBulk = (count($entityIds) > 1);

                        $success = Mage::getModel('tnw_salesforce/localstorage')
                            ->addObject($entityIds, 'Campaign_CatalogRule', 'catalogrule', $syncBulk);

                        if (!$success) {
                            Mage::getSingleton('tnw_salesforce/tool_log')
                                ->saveError('Could not add catalog rule(s) to the queue!');
                        } elseif ($syncBulk) {
                            Mage::getSingleton('tnw_salesforce/tool_log')
                                ->saveNotice($helper->__('ISSUE: Too many records selected.'));
                            Mage::getSingleton('tnw_salesforce/tool_log')
                                ->saveSuccess($helper->__('Selected records were added into <a href="%s">synchronization queue</a> and will be processed in the background.', Mage::helper('adminhtml')->getUrl('*/salesforcesync_queue_to/bulk')));
                        }
                        else {
                            Mage::getSingleton('tnw_salesforce/tool_log')
                                ->saveSuccess($helper->__('Records are pending addition into the queue!'));
                        }
                    }
                    else {
                        /** @var TNW_Salesforce_Helper_Salesforce_Campaign_Catalogrule $campaignMember */
                        $campaignMember = Mage::helper('tnw_salesforce/salesforce_campaign_catalogrule');
                        if ($campaignMember->reset() && $campaignMember->massAdd($entityIds) && $campaignMember->process()) {
                            Mage::getSingleton('tnw_salesforce/tool_log')
                                ->saveSuccess($helper->__('Total of %d record(s) were successfully synchronized', count($entityIds)));
                        }
                    }
                } catch (Exception $e) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError($e->getMessage());
                }
            });
        }
    }
}
