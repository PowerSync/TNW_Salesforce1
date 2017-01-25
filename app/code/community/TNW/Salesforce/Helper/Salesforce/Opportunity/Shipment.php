<?php

class TNW_Salesforce_Helper_Salesforce_Opportunity_Shipment extends TNW_Salesforce_Helper_Salesforce_Order_Shipment
{
    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = 'opportunityShipment';

    /**
     * @var string
     */
    protected $_mappingEntityName = 'OpportunityShipment';

    /**
     * @var string
     */
    protected $_mappingEntityItemName = 'OpportunityShipmentItem';

    /**
     * @param $_entity Mage_Sales_Model_Order_Shipment
     * @param $key
     */
    protected function _prepareEntityObjCustom($_entity, $key)
    {
        // Link to Order
        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'Opportunity__c'}
            = $_entity->getOrder()->getData('salesforce_id');

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'disableMagentoSync__c'}
            = true;
    }

    /**
     * @param $_entityItem Mage_Sales_Model_Order_Shipment_Item
     */
    protected function _prepareEntityItemObjCustom($_entityItem)
    {
        $_entity       = $this->getEntityByItem($_entityItem);
        $_entityNumber = $this->_getEntityNumber($_entity);

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'Opportunity_Product__c'}
            = $_entityItem->getOrderItem()->getData('salesforce_id');

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'Shipment__c'}
            = $this->_getParentEntityId($_entityNumber);

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'disableMagentoSync__c'}
            = true;

        $this->_cache[lcfirst($this->getItemsField()) . 'ToUpsert']['cart_' . $_entityItem->getId()] = $this->_obj;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Shipment
     * @param $_entityItem Mage_Sales_Model_Order_Shipment_Item
     * @return bool
     */
    protected function _doesCartItemExist($_entity, $_entityItem)
    {
        $_sOrderItemId = $_entityItem->getOrderItem()->getData('salesforce_id');
        $_entityNumber = $this->_getEntityNumber($_entity);
        $lookupKey     = sprintf('%sLookup', $this->_salesforceEntityName);

        if (! ($this->_cache[$lookupKey]
            && array_key_exists($_entityNumber, $this->_cache[$lookupKey])
            && $this->_cache[$lookupKey][$_entityNumber]->Items)
        ){
            return false;
        }

        foreach ($this->_cache[$lookupKey][$_entityNumber]->Items->records as $_cartItem) {
            if ($_cartItem->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'Opportunity_Product__c'} != $_sOrderItemId) {
                continue;
            }

            return $_cartItem->Id;
        }

        return false;
    }
}