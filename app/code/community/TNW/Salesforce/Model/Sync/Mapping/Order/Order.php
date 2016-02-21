<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Sync_Mapping_Order_Order extends TNW_Salesforce_Model_Sync_Mapping_Order_Base
{

    protected $_type = 'Order';

    protected function _processMapping($order = null)
    {
        parent::_processMapping($order);
        $this->getObj()->Description = Mage::helper('tnw_salesforce/mapping')->getOrderDescription($order);

        $this->getObj()->OpportunityId = $order->getOpportunityId();
    }

}