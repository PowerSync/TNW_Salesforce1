<?php

class TNW_Salesforce_Helper_Config_Sales_Order extends TNW_Salesforce_Helper_Config_Sales
{
    const ZERO_ORDER_SYNC = 'salesforce_order/general/zero_order_sync_enable';

    public function isEnabledZeroOrderSync()
    {
        return $this->getStoreConfig(self::ZERO_ORDER_SYNC);
    }

    /**
     * @return array
     */
    public function getSyncButtonData()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::registry('sales_order');
        $url   = Mage::getModel('adminhtml/url')->getUrl('*/salesforcesync_ordersync/syncAll', array('order_id' => $order->getId()));

        return array(
            'label'   => Mage::helper('tnw_salesforce')->__('Synchronize w/ Salesforce'),
            'onclick' => "setLocation('$url')",
        );
    }
}