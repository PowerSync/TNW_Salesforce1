<?php

/**
 * Class TNW_Salesforce_Model_Order_Invoice_Comment
 */
class TNW_Salesforce_Model_Order_Invoice_Comment extends Mage_Sales_Model_Order_Invoice_Comment
{
    /**
     * @deprecated use standard event "sales_order_status_history_save_commit_after"
     */
    public function afterCommitCallback()
    {
        Mage::dispatchEvent('tnw_salesforce_invoice_comments_save_after', array(
            'oid' => $this->getParentId(),
            'note' => $this,
            'type' => 'invoice'
        ));

        return parent::afterCommitCallback();
    }
}