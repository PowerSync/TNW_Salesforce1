<?php

class TNW_Salesforce_Helper_Config_Sales_Shipment extends TNW_Salesforce_Helper_Config_Sales
{
    const SHIPMENT_SYNC_ENABLE = 'salesforce_shipment/shipment_configuration/sync_enabled';
    const SHIPMENT_NOTES_SYNC  = 'salesforce_shipment/shipment_configuration/notes_synchronize';

    // Allow Magento to synchronize shipments with Salesforce
    public function syncShipments()
    {
        return (int)$this->getStoreConfig(self::SHIPMENT_SYNC_ENABLE);
    }

    /**
     * @return bool
     */
    public function syncShipmentNotes()
    {
        return (int)$this->getStoreConfig(self::SHIPMENT_NOTES_SYNC);
    }

    /**
     * @return bool
     */
    public function syncShipmentsForOrder()
    {
        return $this->syncShipments()
            && self::SYNC_TYPE_ORDER == strtolower($this->_helper()->getOrderObject());
    }

    /**
     * @return bool
     */
    public function syncShipmentsForOpportunity()
    {
        return $this->syncShipments()
            && self::SYNC_TYPE_OPPORTUNITY == strtolower($this->_helper()->getOrderObject());
    }
}