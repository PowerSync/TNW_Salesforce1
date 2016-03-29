<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Config_Sales extends TNW_Salesforce_Helper_Config
{
    const SYNC_TYPE_ORDER = 'order';
    const SYNC_TYPE_OPPORTUNITY = 'opportunity';

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
     * should we use product_campaign_assignment feature?
     */
    const XML_PATH_USE_PRODUCT_CAMPAIGN_ASSIGNMENT = 'salesforce_order/shopping_cart/use_product_campaign_assignment';

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
        return $this->getStoreConfig(self::ORDER_CURRENCY_SYNC) == TNW_Salesforce_Model_Config_Sync_Currency::BASE_CURRENCY ;
    }

    public function getOpportunityToOrderStatus()
    {
        return $this->getStoreConfig(self::OPPORTUNITY_TO_ORDER_STATUS);
    }

    /**
     * @return mixed|null|string
     */
    public function getUseProductCampaignAssignment()
    {
        return $this->getStoreConfig(self::XML_PATH_USE_PRODUCT_CAMPAIGN_ASSIGNMENT);
    }

    /**
     * @return mixed|null|string
     */
    public function useProductCampaignAssignment()
    {
        return (Mage::helper('tnw_salesforce')->getType() == 'PRO') && $this->getUseProductCampaignAssignment();
    }
}