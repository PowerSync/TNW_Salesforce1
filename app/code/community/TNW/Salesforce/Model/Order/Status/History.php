<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Order_Status_History extends Mage_Sales_Model_Order_Status_History
{
    /**
     * @return Mage_Core_Model_Abstract
     */
    protected function _afterSave()
    {
        Mage::dispatchEvent('tnw_sales_order_comments_save_before', array(
                'oid' => $this->getParentId(),
                'note' => $this,
                'type' => 'Order')
        );

        return parent::_afterSave();
    }
}