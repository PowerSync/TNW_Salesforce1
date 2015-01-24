<?php

/**
 * Class TNW_Salesforce_Model_Order_Creditmemo_Comment
 */
class TNW_Salesforce_Model_Order_Creditmemo_Comment extends Mage_Sales_Model_Order_Creditmemo_Comment
{
    /**
     * @return Mage_Core_Model_Abstract
     */
    protected function _afterSave()
    {
        /* Needs to go into Reduce Order Notes
        $_item = Mage::getModel('sales/order_creditmemo')->load($this->getParentId());
        Mage::dispatchEvent('tnw_sales_order_comments_save_before', array(
                'oid' => $_item->getOrderId(),
                'note' => $this,
                'type' => 'Credit Memo')
        );
        */

        return parent::_afterSave();
    }
}