<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforcesync_OpportunitysyncController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var array
     */
    protected $_publicActions = array('grid', 'index');

    /**
     * Array of product ID's from each order
     * @var array
     */
    protected $_productIds = array();

    protected function _construct()
    {
        $this->setUsedModuleName('Mage_Sales');
    }

    protected function _initLayout()
    {
        if (!Mage::helper('tnw_salesforce')->isEnabled() || !Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
            Mage::getSingleton('adminhtml/session')->addNotice("Salesforce integration is not working! Refer to the config or the log files for more information.");
        }
        $this->loadLayout()
            ->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb(Mage::helper('tnw_salesforce')->__('Manual Order Synchronization'), Mage::helper('tnw_salesforce')->__('Manual Order Synchronization'));

        return $this;
    }

    /**
     * Index Action
     *
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Manual Sync'))->_title($this->__('Orders'));
        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_ordersync'));
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
            Mage::getSingleton('adminhtml/session')->addError("API Integration is disabled.");
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit", array('section' => 'salesforce')));
            Mage::app()->getResponse()->sendResponse();
        }
        if ($this->getRequest()->getParam('order_id') > 0) {
            try {
                $itemIds = array($this->getRequest()->getParam('order_id'));

                if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
                    $order = Mage::getModel('sales/order')->load($this->getRequest()->getParam('order_id'));

                    $_productIds = Mage::helper('tnw_salesforce/salesforce_opportunity')->getProductIdsFromEntity($order);
                    $res = Mage::getModel('tnw_salesforce/localstorage')->addObjectProduct($_productIds, 'Product', 'product');
                    if (!$res) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveWarning('products from the order were not saved in local storage');
                    }

                    // pass data to local storage
                    $res = Mage::getModel('tnw_salesforce/localstorage')->addObject($itemIds, 'Order', 'order');
                    if (!$res) {
                        Mage::getSingleton('adminhtml/session')->addError('Could not add order to the queue!');
                    } else {
                        if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                            Mage::getSingleton('adminhtml/session')->addSuccess(
                                Mage::helper('adminhtml')->__('Record was added to synchronization queue!')
                            );
                        }
                    }
                } else {
                    Mage::dispatchEvent(
                        'tnw_salesforce_opportunity_process',
                        array(
                            'orderIds'      => array($this->getRequest()->getParam('order_id')),
                            'message'       => Mage::helper('adminhtml')->__('Total of %d record(s) were successfully synchronized', count($itemIds)),
                            'type'   => 'salesforce'
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

    public function cartItemsCallback($_args) {
        $_product = Mage::getModel('catalog/product');
        $_product->setData($_args['row']);
        $_id = (int) $this->_getProductIdFromCart($_product);
        if (!in_array($_id, $this->_productIds)) {
            $this->_productIds[] = $_id;
        }
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

        $itemIds = $this->getRequest()->getParam('orders');
        if (!is_array($itemIds)) {
            $session->addError($helper->__('Please select orders(s)'));
        } elseif (!$helper->isProfessionalEdition()) {
            $session->addError($helper->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
        } else {
            try {
                if (count($itemIds) > $helper->getRealTimeSyncMaxCount() || !$helper->isRealTimeType()) {
                    $syncBulk = (count($itemIds) > 1);

                    $_collection = Mage::getResourceModel('sales/order_item_collection');
                    $_collection->getSelect()->reset(Zend_Db_Select::COLUMNS)
                        ->columns(array('sku','order_id','product_id','product_type','product_options'))
                        ->where(new Zend_Db_Expr('order_id IN (' . join(',', $itemIds) . ')'));

                    Mage::getSingleton('core/resource_iterator')->walk(
                        $_collection->getSelect(),
                        array(array($this, 'cartItemsCallback'))
                    );

                    $success = Mage::getModel('tnw_salesforce/localstorage')
                        ->addObjectProduct($this->_productIds, 'Product', 'product', $syncBulk);

                    $success = $success && Mage::getModel('tnw_salesforce/localstorage')
                        ->addObject($itemIds, 'Order', 'order', $syncBulk);

                    if ($success) {
                        if ($syncBulk) {
                            $session->addNotice($this->__('ISSUE: Too many records selected.'));
                            $session->addSuccess($this->__('Selected records were added into <a href="%s">synchronization queue</a> and will be processed in the background.', $this->getUrl('*/salesforcesync_queue_to/bulk')));
                        } else {
                            $session->addSuccess($this->__('Records are pending addition into the queue!'));
                        }
                    }
                    else {
                        $session->addError('Could not add to the queue!');
                    }
                }
                else {
                    Mage::dispatchEvent('tnw_salesforce_opportunity_process', array(
                        'orderIds'  => $itemIds,
                        'message'   => $this->__('Total of %d record(s) were successfully synchronized', count($itemIds)),
                        'type'      => 'bulk'
                    ));
                }
            } catch (Exception $e) {
                $session->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    public function syncWebsitesAction() {
        try {
            $_ids = array();
            $_ids[] = 0;
            foreach (Mage::app()->getWebsites() as $website) {
                $_ids[] = (int) $website->getData('website_id');
            }

            $manualSync = Mage::helper('tnw_salesforce/salesforce_website');
            if ($manualSync->reset() && $manualSync->massAdd($_ids) && $manualSync->process()) {
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('%d Magento website entities were successfully synchronized', count($_ids))
                );
            }
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }
        $this->_redirect('*/system_store/index');
    }

    public function syncCurrencyAction() {
        if (!Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_redirect('*/system_currency/index');
            return;
        }

        try {
            $currencyModel = Mage::getModel('directory/currency');
            $currencies = $currencyModel->getConfigAllowCurrencies();

            $manualSync = Mage::helper('tnw_salesforce/salesforce_currency');
            if ($manualSync->reset() && $manualSync->massAdd($currencies) && $manualSync->process()) {
                Mage::getSingleton('adminhtml/session')
                    ->addSuccess($this->__('%d Magento currency entities were successfully synchronized', count($currencies)));
            }
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }
        $this->_redirect('*/system_currency/index');
    }

    public function massCartSyncAction()
    {
        $this->_redirect('*/*/index');
    }

    public function massNotesSyncAction()
    {
        $this->_redirect('*/*/index');
    }

    protected function _getProductIdFromCart($_item) {
        $_options = unserialize($_item->getData('product_options'));
        if(
            $_item->getData('product_type') == 'bundle'
            || (is_array($_options) && array_key_exists('options', $_options))
        ) {
            $id = $_item->getData('product_id');
        } else {
            $id = (int) Mage::getModel('catalog/product')->getIdBySku($_item->getSku());
        }
        return $id;
    }
}
