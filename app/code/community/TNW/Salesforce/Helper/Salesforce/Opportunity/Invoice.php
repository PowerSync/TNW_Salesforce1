<?php

class TNW_Salesforce_Helper_Salesforce_Opportunity_Invoice extends TNW_Salesforce_Helper_Salesforce_Order_Invoice
{
    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = 'opportunityInvoice';

    /**
     * @var string
     */
    protected $_mappingEntityName = 'OpportunityInvoice';

    /**
     * @var string
     */
    protected $_mappingEntityItemName = 'OpportunityInvoiceItem';

    /**
     * @param $entity Mage_Sales_Model_Order_Invoice
     * @return bool
     */
    protected function checkOrderMassAddEntity($entity)
    {
        $order = $entity->getOrder();
        if (!$this->orderSalesforceId($order) || !$order->getData('sf_insync')) {
            if (!Mage::helper('tnw_salesforce/config_sales')->integrationOrderAllowed()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveNotice("SKIPPING: Sync for invoice #{$entity->getIncrementId()}, order #{$order->getIncrementId()} needs to be synchronized first!");
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
     *
     */
    protected function _massAddAfterLookup()
    {
        // Salesforce lookup, find all orders by Magento order number
        $this->_cache[sprintf('%sLookup', $this->_salesforceEntityName)] = Mage::helper('tnw_salesforce/salesforce_data_invoice')
            ->lookup($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);

        $orders = array();
        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $key=>$number) {
            $invoice = $this->_loadEntityByCache($key, $number);
            $orders[] = $invoice->getOrder()->getRealOrderId();
        }

        $this->_cache['opportunityLookup'] = Mage::helper('tnw_salesforce/salesforce_data')
            ->opportunityLookup($orders);

        if (Mage::helper('tnw_salesforce/config_sales')->integrationOrderAllowed()) {

            $this->_cache['orderLookup'] = Mage::helper('tnw_salesforce/salesforce_data_order')
                ->lookup($orders);
        }
    }

    /**
     * @param $_entity
     * @param $key
     */
    protected function _prepareEntityObjCustom($_entity, $key)
    {
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $this->getCurrencyCode($_entity);
        }

        // Link to Order
        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Opportunity__c'}
            = $this->orderSalesforceId($_entity->getOrder());

        if (Mage::helper('tnw_salesforce/config_sales')->integrationOrderAllowed()) {

            // Link to Order
            $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Order__c'}
                = parent::orderSalesforceId($_entity->getOrder());
        }

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'disableMagentoSync__c'}
            = true;
    }

    /**
     * @param $_entityItem Mage_Sales_Model_Order_Invoice_Item
     */
    protected function _prepareEntityItemObjCustom($_entityItem)
    {
        $_entity       = $this->getEntityByItem($_entityItem);
        $_entityNumber = $this->_getEntityNumber($_entity);

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Invoice__c'}
            = $this->_getParentEntityId($_entityNumber);

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Opportunity_Product__c'}
            = $this->orderItemSalesforceId($_entityItem->getOrderItem());

        if (Mage::helper('tnw_salesforce/config_sales')->integrationOrderAllowed()) {
            $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Order_Item__c'}
                = parent::orderItemSalesforceId($_entityItem->getOrderItem());
        }

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'disableMagentoSync__c'}
            = true;

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $this->getCurrencyCode($_entity);
        }

        $entityId = $_entityItem->getId();

        $key = empty($entityId)
            ? sprintf('%s_%s', $_entityNumber, count($this->_cache[lcfirst($this->getItemsField()) . 'ToUpsert']))
            : $entityId;

        $this->_cache[lcfirst($this->getItemsField()) . 'ToUpsert']['cart_' . $key] = $this->_obj;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @param $item Mage_Sales_Model_Order_Invoice_Item
     */
    protected function _prepareAdditionalFees($_entity, $item)
    {
        /** @var Mage_Sales_Model_Order_Item $_orderItem */
        $_orderItem           = Mage::getModel('sales/order_item');
        $productSalesforceId  = $this->_getObjectByEntityItemType($item, 'Product')->getData('salesforce_id');

        $records              = !empty($this->_cache['opportunityLookup'][$_entity->getOrder()->getRealOrderId()]->OpportunityLineItems)
            ? $this->_cache['opportunityLookup'][$_entity->getOrder()->getRealOrderId()]->OpportunityLineItems->records : array();

        foreach ($records as $record) {
            if ($record->PricebookEntry->Product2Id != $productSalesforceId) {
                continue;
            }

            $_orderItem->setData('opportunity_id', $record->Id);
            break;
        }

        if (Mage::helper('tnw_salesforce/config_sales')->integrationOrderAllowed()) {

            $records = !empty($this->_cache['orderLookup'][$_entity->getOrder()->getRealOrderId()]->OrderItems)
                ? $this->_cache['orderLookup'][$_entity->getOrder()->getRealOrderId()]->OrderItems->records : array();

            foreach ($records as $record) {
                if ($record->PricebookEntry->Product2Id != $productSalesforceId) {
                    continue;
                }

                $_orderItem->setData('salesforce_id', $record->Id);
                break;
            }
        }


        //FIX: $item->getOrderItem()->getData('salesforce_id')
        $item->setOrderItem($_orderItem);
    }
}