<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */


class TNW_Salesforce_Adminhtml_Sales_OrderController extends Mage_Adminhtml_Controller_Action
{

    protected $_orderId = null;
    protected $_order = null;

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('tnw_salesforce');
    }

    /**
     * @comment get current order id
     * @return mixed
     */
    public function getOrderId()
    {
        if (empty($this->_orderId)) {
            $this->_orderId = $this->getRequest()->getParam('order_id');
        }

        return $this->_orderId;
    }

    /**
     * @comment get current order
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (!$this->_order) {
            $orderId = $this->getOrderId();
            $this->_order = Mage::getModel('sales/order')->load($orderId);
        }


        return $this->_order;
    }



    /**
     * @comment sale salesforce tab data
     * @throws Exception
     * @deprecated
     */
    public function saveSalesforceAction()
    {
        $order = $this->getOrder();

        $salesforceOrderData = $this->getRequest()->getParam('order');
        if (!Mage::getSingleton('admin/session')->isAllowed('tnw_salesforce/edit_sales_owner')) {
            unset($salesforceOrderData['owner_salesforce_id']);
        }

        $order->addData($salesforceOrderData);
        $order->save();

        if ($order->getData('owner_salesforce_id') !== $order->getOrigData('owner_salesforce_id')) {
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
        }

        Mage::dispatchEvent('tnw_salesforce_order_save_form', array('order' => $this->getOrder()));

        $this->_redirect('*/sales_order/view', array(
            'order_id' => $this->getOrder()->getId(),
            '_current' => true

        ));

    }

    public function salesPersonAction()
    {
        /** @var TNW_Salesforce_Block_Adminhtml_Sales_Order_Create_Salesforce $block */
        $block = $this->getLayout()->createBlock('tnw_salesforce/adminhtml_sales_order_create_salesforce');
        $this->getResponse()->setBody($block->toHtml());
    }
}