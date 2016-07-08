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
     *
     */
    protected function _massAddAfterInvoice()
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
            = $_entity->getOrder()->getData('salesforce_id');

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
        /** @var Mage_Catalog_Model_Product $product */
        $product       = $this->_getObjectByEntityItemType($_entityItem, 'Product');

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Invoice__c'}
            = $this->_getParentEntityId($_entityNumber);

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Opportunity_Product__c'}
            = $_entityItem->getOrderItem()->getData('salesforce_id');

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'disableMagentoSync__c'}
            = true;

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $this->getCurrencyCode($_entity);
        }

        /* Dump BillingItem object into the log */
        foreach ($this->_obj as $key => $_item) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Order Invoice Item Object: " . $key . " = '" . $_item . "'");
        }
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('-----------------');

        $key = $_entityItem->getId();
        // if it's fake product for order fee, has the same id's for all products
        if (!$product->getId()) {
            $key .= '_' . $_entityNumber;
        }

        $this->_cache[lcfirst($this->getItemsField()) . 'ToUpsert']['cart_' . $key] = $this->_obj;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @param $_entityItem Mage_Sales_Model_Order_Invoice_Item
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
            if ($_cartItem->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Opportunity_Product__c'} != $_sOrderItemId) {
                continue;
            }

            return $_cartItem->Id;
        }

        return false;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @param $item Varien_Object
     */
    protected function _prepareAdditionalFees($_entity, $item)
    {
        /** @var Mage_Sales_Model_Order_Item $_orderItem */
        $_orderItem           = Mage::getModel('sales/order_item');

        $opportunityLookup = @$this->_cache['opportunityLookup'][$_entity->getOrder()->getRealOrderId()];
        if ($opportunityLookup && property_exists($opportunityLookup, 'OpportunityLineItems') && $opportunityLookup->OpportunityLineItems) {
            foreach ($opportunityLookup->OpportunityLineItems->records as $record) {
                if ($record->PricebookEntry->Product2Id != $item->getData('Id')) {
                    continue;
                }

                $_orderItem->setData('salesforce_id', $record->Id);
                break;
            }
        }

        //FIX: $item->getOrderItem()->getData('salesforce_id')
        $item->setData('order_item', $_orderItem);
    }
}