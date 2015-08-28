<?php

class TNW_Salesforce_Adminhtml_Salesforcesync_OrdersyncController extends Mage_Adminhtml_Controller_Action
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
        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            Mage::getSingleton('adminhtml/session')->addError("API Integration is disabled.");
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit", array('section' => 'salesforce')));
            Mage::app()->getResponse()->sendResponse();
        }
        if (!$_syncType) {
            Mage::getSingleton('adminhtml/session')->addError("Integration Type is not set.");
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit", array('section' => 'salesforce_order')));
            Mage::app()->getResponse()->sendResponse();
        }
        if ($this->getRequest()->getParam('order_id') > 0) {
            try {
                $itemIds = array($this->getRequest()->getParam('order_id'));

                if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
                    $order = Mage::getModel('sales/order')->load($this->getRequest()->getParam('order_id'));
                    $_productIds = array();
                    foreach ($order->getAllVisibleItems() as $_item) {
                        if (Mage::getStoreConfig(TNW_Salesforce_Helper_Config_Sales::XML_PATH_ORDERS_BUNDLE_ITEM_SYNC)) {
                            if ($_item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                                $_productIds = array_merge(
                                    $_productIds,
                                    $this->_getChildProductIdsFromCart($_item)
                                );
                            } else {
                                $_productIds[] = (int) $this->_getProductIdFromCart($_item);
                            }
                        } else {
                            $_productIds[] = (int) $this->_getProductIdFromCart($_item);
                        }
                    }

                    $res = Mage::getModel('tnw_salesforce/localstorage')->addObjectProduct($_productIds, 'Product', 'product');
                    if (!$res) {
                        Mage::helper("tnw_salesforce")->log('ERROR: Products from the order were not added to the queue');
                    }

                    // pass data to local storage
                    $res = Mage::getModel('tnw_salesforce/localstorage')->addObject($itemIds, 'Order', 'order');
                    if (!$res) {
                        Mage::getSingleton('adminhtml/session')->addError('Could not add order to the queue!');
                    } else {
                        if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                            Mage::getSingleton('adminhtml/session')->addSuccess(
                                Mage::helper('adminhtml')->__('Order was added to the queue!')
                            );
                        }
                    }
                } else {
                    Mage::dispatchEvent(
                        'tnw_sales_process_' . $_syncType,
                        array(
                            'orderIds'      => $itemIds,
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

    public function massSyncForceAction()
    {
        set_time_limit(0);
        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            Mage::getSingleton('adminhtml/session')->addError("API Integration is disabled.");
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit", array('section' => 'salesforce')));
            Mage::app()->getResponse()->sendResponse();
        }
        if (!$_syncType) {
            Mage::getSingleton('adminhtml/session')->addError("Integration Type is not set.");
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit", array('section' => 'salesforce_order')));
            Mage::app()->getResponse()->sendResponse();
        }
        $itemIds = $this->getRequest()->getParam('orders');
        if (!is_array($itemIds)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('Please select orders(s)'));
        } elseif (Mage::helper('tnw_salesforce')->getType() != "PRO") {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
        } elseif(((Mage::helper('tnw_salesforce')->getObjectSyncType() == 'sync_type_realtime')) && (count($itemIds) > 50)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('For history synchronization containing more than 50 records change configuration to use interval based synchronization.'));
        } else {
            try {
                if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
                    $_collection = Mage::getResourceModel('sales/order_item_collection');
                    $_collection->getSelect()->reset(Zend_Db_Select::COLUMNS)
                        ->columns(array('sku','order_id','product_id','product_type','product_options'))
                        ->where(new Zend_Db_Expr('order_id IN (' . join(',', $itemIds) . ')'));

                    Mage::getSingleton('core/resource_iterator')->walk(
                        $_collection->getSelect(),
                        array(array($this, 'cartItemsCallback'))
                    );

                    $_productChunks = array_chunk($this->_productIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT);
                    foreach($_productChunks as $_chunk) {
                        Mage::helper('tnw_salesforce/queue')->prepareRecordsToBeAddedToQueue($_chunk, 'Product', 'product');
                    }

                    $_chunks = array_chunk($itemIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT);
                    unset($itemIds, $_chunk);
                    foreach($_chunks as $_chunk) {
                        Mage::helper('tnw_salesforce/queue')->prepareRecordsToBeAddedToQueue($_chunk, 'Order', 'order');
                    }

                    if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                        Mage::getSingleton('adminhtml/session')->addSuccess(
                            $this->__('Records are pending addition into the queue!')
                        );
                    }
                } else {
                    Mage::dispatchEvent(
                        'tnw_sales_process_' . $_syncType,
                        array(
                            'orderIds'      => $itemIds,
                            'message'       => Mage::helper('adminhtml')->__('Total of %d order(s) were synchronized', count($itemIds)),
                            'type'   => 'bulk'
                        )
                    );
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/index');
    }

    public function cartItemsCallback($_args) {
        $_product = Mage::getModel('catalog/product');
        $_product->setData($_args['row']);
        $_id = (int) $this->_getProductIdFromCart($_product);
        if (!in_array($_id, $this->_productIds)) {
            $this->_productIds[] = $_id;
        }
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

    /**
     * Get child product ids
     *
     * @param Mage_Sales_Model_Order_Item $_item
     * @return array
     */
    protected function _getChildProductIdsFromCart(Mage_Sales_Model_Order_Item $_item) {
        $Ids = array();
        $productId = $_item->getItemId();
        $Ids[] = (int) $_item->getProductId();

        foreach ($_item->getOrder()->getAllItems() as $_itemProduct) {
            if ($_itemProduct->getParentItemId() == $productId) {
                $Ids[] = (int) $_itemProduct->getProductId();
            }
        }
        return $Ids;
    }
}
