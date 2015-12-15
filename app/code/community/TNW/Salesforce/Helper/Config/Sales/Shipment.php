<?php

class TNW_Salesforce_Helper_Config_Sales_Shipment extends TNW_Salesforce_Helper_Config_Sales
{
    const SHIPMENT_SYNC_ENABLE = 'salesforce_order/shipment_configuration/sync_enabled';

    // Allow Magento to synchronize shipments with Salesforce
    public function syncShipments()
    {
        return $this->getStoreConfig(self::SHIPMENT_SYNC_ENABLE);
    }

    /**
     * @return bool
     */
    public function syncShipmentsForOrder()
    {
        return $this->syncShipments()
            && self::SYNC_TYPE_ORDER == strtolower($this->_helper()->getOrderObject());
    }
}