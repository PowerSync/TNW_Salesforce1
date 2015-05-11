<?php

class TNW_Salesforce_Helper_Config_Sales extends TNW_Salesforce_Helper_Config
{
    /**
     * @comment config path
     */
    const ORDER_CURRENCY_SYNC = 'salesforce_order/general/order_currency_sync';

    /**
     * @comment Should we use base currency
     * @return bool
     */
    public function useBaseCurrency()
    {
        return $this->getStroreConfig(self::ORDER_CURRENCY_SYNC) == TNW_Salesforce_Model_Config_Sync_Currency::BASE_CURRENCY ;
    }
}