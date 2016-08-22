<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */


class TNW_Salesforce_Adminhtml_Sales_OrderController extends Mage_Adminhtml_Controller_Action {


    protected $_orderId = null;
    protected $_order = null;

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
     */
    public function saveSalesforceAction()
    {
        $salesforceOrderData = $this->getRequest()->getParam('order');

        $order = $this->getOrder();
        $order->addData($salesforceOrderData);
        $order->save();

        if ($order->getData('owner_salesforce_id') !== $order->getOrigData('owner_salesforce_id')) {
            if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
                $invoiceIds = $order->getInvoiceCollection()->walk('getId');
                if (Mage::helper('tnw_salesforce/config_sales_invoice')->syncInvoices() && count($invoiceIds) > 0) {
                    $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array_values($invoiceIds), 'Invoice', 'invoice');
                    if (!$res) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveWarning('Invoice from the order were not added to the queue');
                    }
                }

                $shipmentIds = $order->getShipmentsCollection()->walk('getId');
                if (Mage::helper('tnw_salesforce/config_sales_shipment')->syncShipments() && count($shipmentIds) > 0) {
                    $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array_values($shipmentIds), 'Shipment', 'shipment');
                    if (!$res) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveWarning('Shipment from the order were not added to the queue');
                    }
                }

                $creditMemoIds = $order->getCreditmemosCollection()->walk('getId');
                if (Mage::helper('tnw_salesforce/config_sales_creditmemo')->syncCreditMemo() && count($creditMemoIds) > 0) {
                    $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array_values($creditMemoIds), 'Creditmemo', 'creditmemo');
                    if (!$res) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveWarning('Creditmemo from the order were not added to the queue');
                    }
                }

                if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(
                        Mage::helper('adminhtml')->__('Order was added to the queue!')
                    );
                }
            }
            else {
                $invoiceIds = $order->getInvoiceCollection()->walk('getId');
                if (Mage::helper('tnw_salesforce/config_sales_invoice')->syncInvoices() && count($invoiceIds) > 0) {
                    $_syncType = strtolower(Mage::helper('tnw_salesforce')->getInvoiceObject());
                    Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                        'invoiceIds' => array_values($invoiceIds),
                        'message'    => $this->__('Invoice: total of %d record(s) were successfully synchronized', count($invoiceIds)),
                        'type'       => 'salesforce'
                    ));
                }

                $shipmentIds = $order->getShipmentsCollection()->walk('getId');
                if (Mage::helper('tnw_salesforce/config_sales_shipment')->syncShipments() && count($shipmentIds) > 0) {
                    $_syncType = strtolower(Mage::helper('tnw_salesforce')->getShipmentObject());
                    Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                        'shipmentIds' => array_values($shipmentIds),
                        'message'     => $this->__('Shipment: total of %d record(s) were successfully synchronized', count($shipmentIds)),
                        'type'        => 'salesforce'
                    ));
                }

                $creditMemoIds = $order->getCreditmemosCollection()->walk('getId');
                if (Mage::helper('tnw_salesforce/config_sales_creditmemo')->syncCreditMemo() && count($creditMemoIds) > 0) {
                    $_syncType = strtolower(Mage::helper('tnw_salesforce')->getCreditmemoObject());
                    Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                        'creditmemoIds' => array_values($creditMemoIds),
                        'message'       => $this->__('Credit Memo: total of %d record(s) were successfully synchronized', count($creditMemoIds)),
                        'type'          => 'salesforce'
                    ));
                }
            }
        }

        Mage::dispatchEvent('tnw_salesforce_order_save_form', array('order' => $this->getOrder()));

        $this->_redirect('*/sales_order/view', array(
            'order_id' => $this->getOrder()->getId(),
            '_current' => true

        ));

    }

}