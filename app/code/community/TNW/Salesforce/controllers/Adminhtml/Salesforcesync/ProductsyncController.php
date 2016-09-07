<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforcesync_ProductsyncController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var array
     */
    protected $_publicActions = array('grid', 'index');

    /**
     * @return $this
     */
    protected function _initLayout()
    {
        if (!Mage::helper('tnw_salesforce')->isEnabled() || !Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
            Mage::getSingleton('adminhtml/session')->addNotice("Salesforce integration is not working! Refer to the config or the log files for more information.");
        }
        $this->loadLayout()
            ->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb(Mage::helper('tnw_salesforce')->__('Manual Product Synchronization'), Mage::helper('tnw_salesforce')->__('Manual Product Synchronization'));

        return $this;
    }

    /**
     * Index Action
     *
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Manual Sync'))->_title($this->__('Products'));

        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('adminhtml/store_switcher'));
        $this->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_productsync'));

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
        $session = $this->_getSession();
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            $session->addError("API Integration is disabled.");
            $this->_redirect('adminhtml/system_config/edit', array('section' => 'salesforce'));
            return;
        }

        $productId = $this->getRequest()->getParam('product_id');
        if (!$productId) {
            $session->addError($this->__('Incorrect product id'));
        } else {
            try {
                if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
                    // pass data to local storage
                    $res = Mage::getModel('tnw_salesforce/localstorage')
                        ->addObjectProduct(array($productId), 'Product', 'product');
                    if ($res) {
                        $session->addSuccess($this->__('Record was added to synchronization queue!'));
                    } else {
                        $session->addError($this->__('Could not add products to the queue!'));
                    }
                } else {
                    $sync = Mage::helper('tnw_salesforce/salesforce_product');
                    if ($sync->reset()) {

                        if ($sync->massAdd(array($this->getRequest()->getParam('product_id')))){
                            $sync->process();
                        }
                        if (!$session->getMessages()->getErrors()
                            && Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()
                        ) {
                            $session->addSuccess($this->__('Product was successfully synchronized'));
                        }
                    }
                }
            } catch (Exception $e) {
                $session->addError($e->getMessage());
                Mage::logException($e);
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

        $itemIds = $this->getRequest()->getParam('products');
        if (!is_array($itemIds)) {
            $session->addError($helper->__('Please select products(s)'));
        } elseif (!$helper->isProfessionalEdition()) {
            $session->addError($helper->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
        } else {
            try {
                if (count($itemIds) > $helper->getRealTimeSyncMaxCount() || !$helper->isRealTimeType()) {
                    $syncBulk = (count($itemIds) > 1);

                    $success = Mage::getModel('tnw_salesforce/localstorage')
                        ->addObjectProduct($itemIds, 'Product', 'product', $syncBulk);

                    if ($success) {
                        if ($syncBulk) {
                            $session->addNotice($this->__('ISSUE: Too many records selected.'));
                            $session->addSuccess($this->__('Selected records were added into synchronization queue and will be processed in the background.'));
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
                    $manualSync = Mage::helper('tnw_salesforce/bulk_product');
                    if ($manualSync->reset() && $manualSync->massAdd($itemIds) && $manualSync->process()) {
                        $session->addSuccess($this->__('Total of %d record(s) were successfully synchronized', count($itemIds)));
                    }
                }
            } catch (Exception $e) {
                $session->addError($e->getMessage());
            }
        }
        $url = '*/*/index';
        if (Mage::helper('tnw_salesforce')->getStoreId() != 0) {
            $url .= '/store/' . Mage::helper('tnw_salesforce')->getStoreId();
        }
        $this->_redirect($url);
    }
}