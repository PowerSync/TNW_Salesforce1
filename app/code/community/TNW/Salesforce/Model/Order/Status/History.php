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
     * @deprecated use standard event "sales_order_status_history_save_commit_after"
     */
    public function afterCommitCallback()
    {
        Mage::dispatchEvent('tnw_salesforce_order_comments_save_after', array(
            'oid' => $this->getParentId(),
            'note' => $this,
            'type' => 'Order'
        ));

        return parent::afterCommitCallback();
    }
}