<?php

class TNW_Salesforce_Helper_Config_Sales_Order extends TNW_Salesforce_Helper_Config_Sales
{
    const ZERO_ORDER_SYNC = 'salesforce_order/general/zero_order_sync_enable';

    public function isEnabledZeroOrderSync()
    {
        return $this->getStoreConfig(self::ZERO_ORDER_SYNC);
    }

    public function getOrderLabels()
    {
        $labels = array(
            'salesforce_id' =>  'Order',
            'contact_salesforce_id' =>  'Contact',
            'account_salesforce_id' =>  'Account',
        );

        if (Mage::helper('tnw_salesforce')->getOrderObject() != TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_OBJECT) {
            $labels['opportunity_id'] = 'Opportunity';
        }

        return $labels;
    }
}