<?php

/**
 * Class TNW_Salesforce_Model_Order_Shipment_Comment
 */
class TNW_Salesforce_Model_Order_Shipment_Comment extends Mage_Sales_Model_Order_Shipment_Comment
{
    /**
     * @return Mage_Core_Model_Abstract
     */
    protected function _afterSave()
    {
        /* Needs to go into Shipment Notes
        $_item = Mage::getModel('sales/order_shipment')->load($this->getParentId());
        Mage::dispatchEvent('tnw_sales_order_comments_save_before', array(
                'oid' => $_item->getOrderId(),
                'note' => $this,
                'type' => 'Shipment')
        );
        */

        return parent::_afterSave();
    }
}