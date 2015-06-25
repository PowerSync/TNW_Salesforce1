<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 09.03.15
 * Time: 22:22
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