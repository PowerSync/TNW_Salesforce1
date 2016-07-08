<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Order_Creditmemo_Comment extends Mage_Sales_Model_Order_Creditmemo_Comment
{
    /**
     * @deprecated use standard event "tnw_salesforce_creditmemo_comments_save_after"
     */
    public function afterCommitCallback()
    {
        Mage::dispatchEvent('tnw_salesforce_creditmemo_comments_save_after', array(
            'oid' => $this->getParentId(),
            'note' => $this,
            'type' => 'creditmemo'
        ));

        return parent::afterCommitCallback();
    }
}