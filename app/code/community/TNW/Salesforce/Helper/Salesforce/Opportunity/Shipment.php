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
     * @param $entity Mage_Sales_Model_Order_Shipment
     * @return bool
     */
    protected function checkOrderMassAddEntity($entity)
    {
        $order = $entity->getOrder();
        if (!$this->orderSalesforceId($order) || !$order->getData('sf_insync')) {
            if (!Mage::helper('tnw_salesforce/config_sales')->integrationOrderAllowed()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveNotice("SKIPPING: Sync for shipment #{$entity->getIncrementId()}, order #{$order->getIncrementId()} needs to be synchronized first!");
            }

            return false;
        }

        return true;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    protected function orderSalesforceId($order)
    {
        return $order->getData('opportunity_id');
    }

    /**
     * @param Mage_Sales_Model_Order_Item $orderItem
     * @return string
     */
    protected function orderItemSalesforceId($orderItem)
    {
        return $orderItem->getData('opportunity_id');
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Shipment
     * @param $key
     */
    protected function _prepareEntityObjCustom($_entity, $key)
    {
        // Link to Order
        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'Opportunity__c'}
            = $this->orderSalesforceId($_entity->getOrder());

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
            = $this->orderItemSalesforceId($_entityItem->getOrderItem());

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'Shipment__c'}
            = $this->_getParentEntityId($_entityNumber);

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT . 'disableMagentoSync__c'}
            = true;

        $this->_cache[lcfirst($this->getItemsField()) . 'ToUpsert']['cart_' . $_entityItem->getId()] = $this->_obj;
    }
}