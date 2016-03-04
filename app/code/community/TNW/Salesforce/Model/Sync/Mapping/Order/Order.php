<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Sync_Mapping_Order_Order extends TNW_Salesforce_Model_Sync_Mapping_Order_Base
{
    /**
     * @comment Contains Salesforce object name for mapping
     */
    protected $_type = 'Order';

    /**
     * @param Mage_Sales_Model_Order $order
     */
    protected function _processMapping($order = null)
    {
        parent::_processMapping($order);

        if (Mage::helper('tnw_salesforce')->getType() == 'PRO') {
            $disableSyncField = Mage::helper('tnw_salesforce/config')->getDisableSyncField();
            $this->getObj()->$disableSyncField = true;
        }

        $this->getObj()->Description   = self::getOrderDescription($order);
        $this->getObj()->OpportunityId = $order->getOpportunityId();
    }

}