<?php
/**
 * Author: Tech-N-Web, LLC (dba PowerSync) 
 * Email: support@powersync.biz 
 * Developer: Evgeniy Ermolaev
 * Date: 24.06.15
 * Time: 19:21
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

        Mage::dispatchEvent('tnw_salesforce_order_save_form', array('order' => $this->getOrder()));

        $this->_redirect('*/sales_order/view', array(
            'order_id' => $this->getOrder()->getId(),
            '_current' => true

        ));

    }

}