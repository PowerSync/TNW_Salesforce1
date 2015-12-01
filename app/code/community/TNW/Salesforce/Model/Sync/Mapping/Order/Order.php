<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 09.03.15
 * Time: 22:22
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

        $this->getObj()->Description   = self::getOrderDescription($order);
        $this->getObj()->OpportunityId = $order->getOpportunityId();
    }

}