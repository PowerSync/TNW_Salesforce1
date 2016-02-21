<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Config_Sales extends TNW_Salesforce_Helper_Config
{
    /**
     * @comment config path
     */
    const ORDER_CURRENCY_SYNC = 'salesforce_order/general/order_currency_sync';

    /**
     * @comment change opportunity status when order placed to
     */
    const OPPORTUNITY_TO_ORDER_STATUS = 'salesforce_order/customer_opportunity/opportunity_to_order_status';

    /**
     * @comment Should we use base currency
     * @return bool
     */
    public function useBaseCurrency()
    {
        return $this->getStroreConfig(self::ORDER_CURRENCY_SYNC) == TNW_Salesforce_Model_Config_Sync_Currency::BASE_CURRENCY ;
    }

    public function getOpportunityToOrderStatus()
    {
        return $this->getStroreConfig(self::OPPORTUNITY_TO_ORDER_STATUS);
    }
}