<?php

class TNW_Salesforce_Model_Service_Order extends Mage_Sales_Model_Service_Order
{
    protected $_shipment = array();

    /**
     * Prepare order shipment based on order items and requested items qty
     *
     * @param array $qtys
     * @return Mage_Sales_Model_Order_Shipment
     */
    public function prepareShipment($qtys = array())
    {
        // Only update session if SF connection is set
        if (Mage::helper('tnw_salesforce')->canPush()) {

            $this->_shipment[$this->_order->getId()] = array();
            foreach ($this->_order->getAllItems() as $orderItem) {
                if (!$this->_canShipItem($orderItem, $qtys)) {
                    continue;
                }

                /* Save to session */
                if (array_key_exists($orderItem->getId(), $qtys) && $qtys[$orderItem->getId()] > 0) {
                    $this->_shipment[$this->_order->getId()][$orderItem->getSku()] = $qtys[$orderItem->getId()];
                }
            }
            if (!empty($this->_shipment[$this->_order->getId()])) {
                Mage::helper('tnw_salesforce/test_authentication')->setStorage(serialize($this->_shipment), 'shipped_items');
            }
        }

        return parent::prepareShipment($qtys);
    }
}