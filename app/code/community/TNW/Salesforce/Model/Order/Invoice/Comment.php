<?php

/**
 * Class TNW_Salesforce_Model_Order_Invoice_Comment
 */
class TNW_Salesforce_Model_Order_Invoice_Comment extends Mage_Sales_Model_Order_Invoice_Comment
{
    /**
     * @return Mage_Core_Model_Abstract
     */
    protected function _afterSave()
    {
        /* Needs to go into Invoice Notes
        $_item = Mage::getModel('sales/order_invoice')->load($this->getParentId());
        Mage::dispatchEvent('tnw_sales_order_comments_save_before', array(
                'oid' => $_item->getOrderId(),
                'note' => $this,
                'type' => 'Invoice')
        );
        */

        return parent::_afterSave();
    }
}