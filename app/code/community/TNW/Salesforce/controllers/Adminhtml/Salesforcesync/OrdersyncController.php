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
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_synchronize_order'));
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
     * @throws Exception
     */
    public function syncAction()
    {
        $entityId = $this->getRequest()->getParam('order_id');
        Mage::getSingleton('tnw_salesforce/sale_observer')->syncOrder(array($entityId), true);

        $this->_redirectReferer();
    }

    /**
     * Sync All
     * @throws Exception
     */
    public function syncAllAction()
    {
        $entityId = $this->getRequest()->getParam('order_id');
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($entityId);
        if (is_null($order->getId())) {
            $this->_redirectReferer();
            return;
        }

        Mage::getSingleton('tnw_salesforce/sale_observer')
            ->syncOrder(array($order->getId()), true);

        $invoiceIds = $order->getInvoiceCollection()->walk('getId');
        if (!empty($invoiceIds)) {
            Mage::getSingleton('tnw_salesforce/order_invoice_observer')
                ->syncInvoice($invoiceIds);
        }

        $shipmentIds = $order->getShipmentsCollection()->walk('getId');
        if (!empty($shipmentIds)) {
            Mage::getSingleton('tnw_salesforce/order_shipment_observer')
                ->syncShipment($shipmentIds);
        }

        $creditMemoIds = $order->getCreditmemosCollection()->walk('getId');
        if (!empty($creditMemoIds)) {
            Mage::getSingleton('tnw_salesforce/order_creditmemo_observer')
                ->syncCreditMemo($creditMemoIds);
        }

        $this->_redirectReferer();
    }

    /**
     * @throws Exception
     */
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
            Mage::getSingleton('tnw_salesforce/sale_observer')->syncOrder($itemIds, true);
        }

        $this->_redirect('*/*/index');
    }
}
