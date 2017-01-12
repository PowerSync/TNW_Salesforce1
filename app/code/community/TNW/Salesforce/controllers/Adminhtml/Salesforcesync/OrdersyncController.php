<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforcesync_OrdersyncController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var array
     */
    protected $_publicActions = array('grid', 'index');

    protected function _construct()
    {
        $this->setUsedModuleName('Mage_Sales');
    }

    protected function _initLayout()
    {
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
        $entityId = $this->getRequest()->getParam('order_id');
        Mage::getSingleton('tnw_salesforce/sale_observer')->syncOrder(array($entityId));

        $this->_redirectReferer();
    }

    /**
     * Sync All
     */
    public function syncAllAction()
    {
        $entityId = $this->getRequest()->getParam('order_id');
        if (empty($entityId)) {
            $this->_redirectReferer();
            return;
        }

        $entityIds = array($entityId);

        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        /** @var Varien_Db_Select $select */
        $select = TNW_Salesforce_Model_Localstorage::generateSelectForType('sales/order', $entityIds);

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
                try {
                    /** @var Mage_Sales_Model_Order $order */
                    $order = Mage::getModel('sales/order')->load($entityId);

                    if (!$helper->isRealTimeType()) {
                        $_productIds = Mage::helper('tnw_salesforce/salesforce_order')
                            ->getProductIdsFromEntity($order);

                        $res = Mage::getModel('tnw_salesforce/localstorage')
                            ->addObjectProduct($_productIds, 'Product', 'product');

                        $res = $res && Mage::getModel('tnw_salesforce/localstorage')
                            ->addObject($entityIds, 'Order', 'order');

                        if (!$res) {
                            $this->_getSession()->addError('Could not add order to the queue!');
                        }
                        else {
                            $invoiceIds = $order->getInvoiceCollection()->walk('getId');
                            if (count($invoiceIds) > 0 && Mage::helper('tnw_salesforce/config_sales_invoice')->syncInvoices()) {
                                $res = Mage::getModel('tnw_salesforce/localstorage')
                                    ->addObject(array_values($invoiceIds), 'Invoice', 'invoice');

                                if (!$res) {
                                    Mage::getSingleton('tnw_salesforce/tool_log')
                                        ->saveWarning('Invoice from the order were not added to the queue');
                                }
                            }

                            $shipmentIds = $order->getShipmentsCollection()->walk('getId');
                            if (count($shipmentIds) > 0 && Mage::helper('tnw_salesforce/config_sales_shipment')->syncShipments()) {
                                $res = Mage::getModel('tnw_salesforce/localstorage')
                                    ->addObject(array_values($shipmentIds), 'Shipment', 'shipment');

                                if (!$res) {
                                    Mage::getSingleton('tnw_salesforce/tool_log')
                                        ->saveWarning('Shipment from the order were not added to the queue');
                                }
                            }

                            $creditMemoIds = $order->getCreditmemosCollection()->walk('getId');
                            if (count($creditMemoIds) > 0 && Mage::helper('tnw_salesforce/config_sales_creditmemo')->syncCreditMemo()) {
                                $res = Mage::getModel('tnw_salesforce/localstorage')
                                    ->addObject(array_values($creditMemoIds), 'Creditmemo', 'creditmemo');

                                if (!$res) {
                                    Mage::getSingleton('tnw_salesforce/tool_log')
                                        ->saveWarning('Creditmemo from the order were not added to the queue');
                                }
                            }

                            if (!$this->_getSession()->getMessages()->getErrors()) {
                                $this->_getSession()->addSuccess($helper->__('Order was added to the queue!'));
                            }
                        }
                    }
                    else {
                        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
                        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                            'orderIds' => $entityIds,
                            'message' => $this->__('Order: total of %d record(s) were successfully synchronized in Website: %s', count($entityIds), $website->getName()),
                            'type' => 'salesforce'
                        ));

                        $invoiceIds = $order->getInvoiceCollection()->walk('getId');
                        if (count($invoiceIds) > 0 && Mage::helper('tnw_salesforce/config_sales_invoice')->syncInvoices()) {
                            $_syncType = strtolower(Mage::helper('tnw_salesforce')->getInvoiceObject());
                            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                                'invoiceIds' => array_values($invoiceIds),
                                'message' => $this->__('Invoice: total of %d record(s) were successfully synchronized in Website: %s', count($invoiceIds), $website->getName()),
                                'type' => 'salesforce'
                            ));
                        }

                        $shipmentIds = $order->getShipmentsCollection()->walk('getId');
                        if (count($shipmentIds) > 0 && Mage::helper('tnw_salesforce/config_sales_shipment')->syncShipments()) {
                            $_syncType = strtolower(Mage::helper('tnw_salesforce')->getShipmentObject());
                            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                                'shipmentIds' => array_values($shipmentIds),
                                'message' => $this->__('Shipment: total of %d record(s) were successfully synchronized in Website: %s', count($shipmentIds), $website->getName()),
                                'type' => 'salesforce'
                            ));
                        }

                        $creditMemoIds = $order->getCreditmemosCollection()->walk('getId');
                        if (count($creditMemoIds) > 0 && Mage::helper('tnw_salesforce/config_sales_creditmemo')->syncCreditMemo()) {
                            $_syncType = strtolower(Mage::helper('tnw_salesforce')->getCreditmemoObject());
                            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                                'creditmemoIds' => array_values($creditMemoIds),
                                'message' => $this->__('Credit Memo: total of %d record(s) were successfully synchronized in Website: %s', count($creditMemoIds), $website->getName()),
                                'type' => 'salesforce'
                            ));
                        }
                    }
                } catch (Exception $e) {
                    $this->_getSession()->addError($e->getMessage());
                }
            }

            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
        }
    }

    public function massSyncForceAction()
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper  = Mage::helper('tnw_salesforce');

        $itemIds = $this->getRequest()->getParam('orders');
        if (!is_array($itemIds)) {
            $this->_getSession()->addError(Mage::helper('tnw_salesforce')->__('Please select orders(s)'));
        } elseif (!$helper->isProfessionalEdition()) {
            $this->_getSession()->addError($helper->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
        } else {
            Mage::getSingleton('tnw_salesforce/sale_observer')->syncOrder($itemIds);
        }

        $this->_redirect('*/*/index');
    }
}
