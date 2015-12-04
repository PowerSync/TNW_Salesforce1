<?php

/**
 * order status history comments
 *
 * class tnw_salesforce_model_order_status_history
 * @deprecated
 */
class TNW_Salesforce_Model_Order_Status_History extends Mage_Sales_Model_Order_Status_History
{
    /**
     * @return Mage_Core_Model_Abstract
     * @deprecated use standard event "sales_order_status_history_save_after"
     */
    protected function _afterSave()
    {
        Mage::dispatchEvent('tnw_salesforce_order_comments_save_after', array(
            'oid' => $this->getParentId(),
            'note' => $this,
            'type' => 'Order'
        ));

        return parent::_afterSave();
    }
}