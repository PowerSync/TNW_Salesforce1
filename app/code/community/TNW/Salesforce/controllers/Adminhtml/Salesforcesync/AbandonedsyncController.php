<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforcesync_AbandonedsyncController extends Mage_Adminhtml_Controller_Action
{

    protected function _initLayout()
    {
        if (
            !Mage::helper('tnw_salesforce')->isEnabled() ||
            !Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()
        ) {
            Mage::getSingleton('adminhtml/session')->addNotice("Salesforce integration is not working! Refer to the config or the log files for more information.");
        }
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
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_abandonedsync'));
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
     *
     */
    public function syncAction()
    {
        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getAbandonedObject());
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            Mage::getSingleton('adminhtml/session')->addError("API Integration is disabled.");
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit", array('section' => 'salesforce')));
            Mage::app()->getResponse()->sendResponse();
        }
        if (!$_syncType) {
            Mage::getSingleton('adminhtml/session')->addError("Integration Type is not set.");
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit", array('section' => 'salesforce_abandoned')));
            Mage::app()->getResponse()->sendResponse();
        }

        if ($this->getRequest()->getParam('abandoned_id') > 0) {
            try {
                $itemIds = array($this->getRequest()->getParam('abandoned_id'));

                if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
                    $stores = Mage::app()->getStores(true);
                    $storeIds = array_keys($stores);

                    $abandoned = Mage::getModel('sales/quote')->setSharedStoreIds($storeIds)->load($this->getRequest()->getParam('abandoned_id'));

                    $_productIds = Mage::helper('tnw_salesforce/salesforce_abandoned_opportunity')->getProductIdsFromEntity($abandoned);
                    $res = Mage::getModel('tnw_salesforce/localstorage')->addObjectProduct($_productIds, 'Product', 'product');
                    if (!$res) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveWarning('Products from the abandoned were not added to the queue');
                    }

                    // pass data to local storage
                    $res = Mage::getModel('tnw_salesforce/localstorage')->addObject($itemIds, 'Abandoned', 'abandoned');
                    if (!$res) {
                        Mage::getSingleton('adminhtml/session')->addError('Could not add abandoned to the queue!');
                    } else {
                        if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                            Mage::getSingleton('adminhtml/session')->addSuccess(
                                Mage::helper('adminhtml')->__('Abandoned was added to the queue!')
                            );
                        }
                    }
                } else {
                    Mage::dispatchEvent(
                        sprintf('tnw_salesforce_%s_process', $_syncType),
                        array(
                            'orderIds' => $itemIds,
                            'object_type' => 'abandoned',
                            'message' => Mage::helper('adminhtml')->__('Total of %d record(s) were successfully synchronized', count($itemIds)),
                            'type' => 'salesforce'
                        )
                    );
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/');
            }
        }
        $this->_redirect('*/*/');
    }

    public function massSyncForceAction()
    {
        $session = Mage::getSingleton('adminhtml/session');
        $helper  = Mage::helper('tnw_salesforce');

        if (!$helper->isEnabled()) {
            $session->addError("API Integration is disabled.");
            $this->_redirect("adminhtml/system_config/edit", array('section' => 'salesforce'));
            return;
        }

        $itemIds = $this->getRequest()->getParam('abandoneds');
        if (!is_array($itemIds)) {
            $session->addError($helper->__('Please select abandoneds(s)'));
        } elseif (!$helper->isProfessionalEdition()) {
            $session->addError($helper->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
        } else {
            try {
                if (count($itemIds) > $helper->getRealTimeSyncMaxCount() || !$helper->isRealTimeType()) {
                    $syncBulk = (count($itemIds) > 1);

                    $_collection = Mage::getResourceModel('sales/quote_item_collection')
                        ->addFieldToFilter('quote_id', array('in' => $itemIds));

                    $productIds = $_collection->walk(array(
                        Mage::helper('tnw_salesforce/salesforce_abandoned_opportunity'), 'getProductIdFromCart'
                    ));

                    $success = Mage::getModel('tnw_salesforce/localstorage')
                        ->addObjectProduct(array_unique($productIds), 'Product', 'product', $syncBulk);

                    $success = $success && Mage::getModel('tnw_salesforce/localstorage')
                        ->addObject($itemIds, 'Abandoned', 'abandoned', $syncBulk);

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
                    $_syncType = strtolower(Mage::helper('tnw_salesforce')->getAbandonedObject());
                    Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                        'orderIds' => $itemIds,
                        'message' => $this->__('Total of %d abandoned(s) were synchronized', count($itemIds)),
                        'type' => 'bulk',
                        'object_type' => 'abandoned'
                    ));
                }
            } catch (Exception $e) {
                $session->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/index');
    }
}
