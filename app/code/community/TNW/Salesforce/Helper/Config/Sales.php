<?php

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
     * @comment config path
     */
    const XML_PATH_ORDERS_BUNDLE_ITEM_SYNC = 'salesforce_order/shopping_cart/orders_bundle_item_sync';

    /**
     * @comment config path
     */
    const XML_PATH_ORDERS_STATUS_UPDATE_CUSTOMER = 'salesforce_order/general/order_status_update_customer';

    /**
     * @comment Bundle Item marker
     */
    const BUNDLE_ITEM_MARKER = 'Bundle Item from: sku - ';

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