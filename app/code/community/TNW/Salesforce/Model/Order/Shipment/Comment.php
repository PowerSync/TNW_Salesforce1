<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Order_Shipment_Comment extends Mage_Sales_Model_Order_Shipment_Comment
{
    /**
     * @return Mage_Core_Model_Abstract
     * @deprecated use standard event "sales_order_status_history_save_after"
     */
    protected function _afterSave()
    {
        Mage::dispatchEvent('tnw_salesforce_shipment_comments_save_after', array(
            'oid' => $this->getParentId(),
            'note' => $this,
            'type' => 'shipment'
        ));

        return parent::_afterSave();
    }
}