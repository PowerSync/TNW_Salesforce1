<?php

class TNW_Salesforce_Helper_Magento_Invoice extends TNW_Salesforce_Helper_Magento_Abstract
{
    /**
     * @param null $object
     * @return mixed
     */
    public function syncFromSalesforce($object = null)
    {
        $this->_prepare();

        $_sInvoiceId = (property_exists($object, "Id") && $object->Id)
            ? $object->Id : null;

        if (!$_sInvoiceId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting invoice into Magento: tnw_invoice__Invoice__c ID is missing");
            $this->_addError('Could not upsert Invoice into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }

        $_miIncrementIdKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . "Magento_ID__c";
        $_miIncrementId    = (property_exists($object, $_miIncrementIdKey) && $object->$_miIncrementIdKey)
            ? $object->$_miIncrementIdKey : null;

        if ($_miIncrementId) {
            //Test if user exists
            $sql = "SELECT increment_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_invoice') . "` WHERE increment_id = '" . $_miIncrementId . "'";
            $row = $this->_write->query($sql)->fetch();
            $_miIncrementId = ($row) ? $row['increment_id'] : null;

            if ($_miIncrementId) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Invoice loaded using Magento ID: " . $_miIncrementId);
            }
        }

        if (!$_miIncrementId && $_sInvoiceId) {
            // Try to find the user by SF Id
            $sql = "SELECT increment_id FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_invoice') . "` WHERE salesforce_id = '" . $_sInvoiceId . "'";
            $row = $this->_write->query($sql)->fetch();
            $_miIncrementId = ($row) ? $row['increment_id'] : null;

            if ($_miIncrementId) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Invoice #" . $_miIncrementId . " Loaded by using Salesforce ID: " . $_sInvoiceId);
            }
        }

        $_sOrderIdKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Order__c';
        $_sOpportunityIdKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Opportunity__c';
        if (empty($object->$_sOrderIdKey) && empty($object->$_sOpportunityIdKey)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('ERROR Object "tnw_invoice__Invoice__c" lost contact with the object "Order"');

            return false;
        }

        $row = false;
        $tableSalesFlatOrder = Mage::helper('tnw_salesforce')->getTable('sales_flat_order');
        if (empty($row) && !empty($object->$_sOrderIdKey)) {
            $sql = /** @lang text */
                "SELECT entity_id FROM `{$tableSalesFlatOrder}` WHERE salesforce_id = '{$object->$_sOrderIdKey}'";
            $row = $this->_write->query($sql)->fetch();
        }

        if (empty($row) && !empty($object->$_sOpportunityIdKey)) {
            $sql = /** @lang text */
                "SELECT entity_id FROM `{$tableSalesFlatOrder}` WHERE opportunity_id = '{$object->$_sOpportunityIdKey}'";
            $row = $this->_write->query($sql)->fetch();
        }

        if (!$row) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('ERROR Object "Order" (%s) is not synchronized');

            return false;
        }

        return $this->_updateMagento($object, $_miIncrementId, $_sInvoiceId, $row['entity_id']);
    }

    protected function _updateMagento($object, $_miIncrementId, $_sInvoiceId, $_mOrderId)
    {
        if ($_miIncrementId) {
            /** @var Mage_Sales_Model_Order_Invoice $invoice */
            $invoice = Mage::getModel('sales/order_invoice')
                ->loadByIncrementId($_miIncrementId);
        } elseif ($_mOrderId) {
            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order')->load($_mOrderId);

            // Check order existing
            if (!$order->getId()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('The order no longer exists.');
                return false;
            }

            // Check invoice create availability
            if (!$order->canInvoice()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('The order does not allow creating an invoice.');
                return false;
            }

            $_invoiceItemKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'InvoiceItem__r';
            if (!property_exists($object, $_invoiceItemKey)) {
                return false;
            }

            if (!property_exists($object->$_invoiceItemKey, 'records')) {
                return false;
            }

            $salesforceIds = array_filter($order->getItemsCollection()->walk('getSalesforceId'));
            $opportunityIds = array_filter($order->getItemsCollection()->walk('getOpportunityId'));

            $_iItemQuantityKey  = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Quantity__c';
            $_iItemOrderItemKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Order_Item__c';
            $_iItemOpportunityItemKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Opportunity_Product__c';

            $savedQtys = array();
            foreach ($object->$_invoiceItemKey->records as $record) {
                $orderItemId = null;
                if (empty($orderItemId) && !empty($record->$_iItemOrderItemKey)) {
                    $orderItemId = array_search($record->$_iItemOrderItemKey, $salesforceIds);
                }

                if (empty($orderItemId) && !empty($record->$_iItemOpportunityItemKey)) {
                    $orderItemId = array_search($record->$_iItemOpportunityItemKey, $opportunityIds);
                }

                if (empty($orderItemId)) {
                    continue;
                }

                if (!property_exists($record, $_iItemQuantityKey)) {
                    continue;
                }

                /** @var Mage_Sales_Model_Order_Item $item */
                $item = $order->getItemsCollection()->getItemById($orderItemId);
                if ($item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                    /** @var Mage_Sales_Model_Order_Item $_item */
                    foreach ($item->getChildrenItems() as $_item) {
                        $savedQtys[$_item->getId()] = (int)$record->$_iItemQuantityKey;
                    }

                    continue;
                }

                $savedQtys[$orderItemId] = (int)$record->$_iItemQuantityKey;
            }

            /** @var Mage_Sales_Model_Order_Invoice $invoice */
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice($savedQtys);
            if (!$invoice->getTotalQty()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Cannot create an invoice without products.');
                return false;
            }

            $invoice->setRequestedCaptureCase($invoice::CAPTURE_OFFLINE);
            $invoice->register();

            $invoice->getOrder()->setIsInProcess(true);
            $this->addEntityToSave('Order', $invoice->getOrder());
        }

        $this->addEntityToSave('Invoice', $invoice);
        $invoice->addData(array(
            'salesforce_id' => $_sInvoiceId,
            'sf_insync'     => 1
        ));

        $this->_updateMappedEntityFields($object, $invoice)
            ->_updateMappedEntityItemFields($object, $invoice)
            ->saveEntities();

        return $invoice;
    }

    /**
     * @param $object
     * @param $invoice Mage_Sales_Model_Order_Invoice
     * @return $this
     */
    protected function _updateMappedEntityFields($object, $invoice)
    {
        $sfObject = Mage::helper('tnw_salesforce/config_sales')->integrationOrderAllowed()
            ? 'OrderInvoice'
            : 'OpportunityInvoice';

        /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $mappings */
        $mappings = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addObjectToFilter($sfObject)
            ->addFilterTypeSM(!$invoice->isObjectNew())
            ->firstSystem();

        $updateFieldsLog = array();
        /** @var $mapping TNW_Salesforce_Model_Mapping */
        foreach ($mappings as $mapping) {
            $newValue = property_exists($object, $mapping->getSfField())
                ? $object->{$mapping->getSfField()} : null;

            if (empty($newValue)) {
                $newValue = $mapping->getDefaultValue();
            }

            $entityName = $mapping->getLocalFieldType();
            $field      = $mapping->getLocalFieldAttributeCode();

            $entity = false;
            switch ($entityName) {
                case 'Shipping':
                case 'Billing':
                    $method = sprintf('get%sAddress', $entityName);
                    $entity = $invoice->$method();

                    $keyState = sprintf('tnw_invoice__%s_Country__c', $entityName);
                    if (($field == 'region') && property_exists($object, $keyState)) {
                        foreach(Mage::getModel('directory/region_api')->items($object->$keyState) as $_region) {
                            if (!in_array($newValue, $_region)) {
                                continue;
                            }

                            $field    = 'region_id';
                            $newValue = $_region['region_id'];
                            break;
                        }
                    }
                    break;

                case 'Invoice':
                    $entity = $invoice;
                    break;
            }

            if (!$entity) {
                continue;
            }

            if ($entity->hasData($field) && $entity->getData($field) != $newValue) {
                $entity->setData($field, $newValue);
                $this->addEntityToSave($entityName, $entity);

                //add info about updated field to order comment
                $updateFieldsLog[] = sprintf('%s - from "%s" to "%s"',
                    $mapping->getLocalField(), $entity->getOrigData($field), $newValue);
            }
        }

        //add comment about all updated fields
        if (!empty($updateFieldsLog)) {
            $invoice->addComment(
                "Fields are updated by salesforce:\n"
                . implode("\n", $updateFieldsLog)
            );
        }

        return $this;
    }

    /**
     * @param $object
     * @param $invoice Mage_Sales_Model_Order_Invoice
     * @return $this
     */
    protected function _updateMappedEntityItemFields($object, $invoice)
    {
        $salesforceIds = array_filter($invoice->getOrder()->getItemsCollection()->walk('getSalesforceId'));
        $opportunityIds = array_filter($invoice->getOrder()->getItemsCollection()->walk('getOpportunityId'));

        /** @var Mage_Sales_Model_Resource_Order_Invoice_Item_Collection $_invoiceItemCollection */
        $_invoiceItemCollection = $invoice->getItemsCollection();
        $hasOrderId = $_invoiceItemCollection
            ->walk('getOrderItemId');

        $_invoiceItemKey    = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'InvoiceItem__r';
        $_iItemOrderItemKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Order_Item__c';
        $_iItemOpportunityItemKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Opportunity_Product__c';
        foreach ($object->$_invoiceItemKey->records as $record) {
            $orderItemId = null;
            if (empty($orderItemId) && !empty($record->$_iItemOrderItemKey)) {
                $orderItemId = array_search($record->$_iItemOrderItemKey, $salesforceIds);
            }

            if (empty($orderItemId) && !empty($record->$_iItemOpportunityItemKey)) {
                $orderItemId = array_search($record->$_iItemOpportunityItemKey, $opportunityIds);
            }

            if (empty($orderItemId)) {
                continue;
            }

            $invoiceItemId = array_search($orderItemId, $hasOrderId);
            if (false === $invoiceItemId) {
                continue;
            }

            /** @var Mage_Sales_Model_Order_Invoice_Item $entity */
            $entity = $_invoiceItemCollection->getItemById($invoiceItemId);

            $sfObject = Mage::helper('tnw_salesforce/config_sales')->integrationOrderAllowed()
                ? 'OrderInvoiceItem'
                : 'OpportunityInvoiceItem';

            /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $mappings */
            $mappings = Mage::getResourceModel('tnw_salesforce/mapping_collection')
                ->addObjectToFilter($sfObject)
                ->addFilterTypeSM(!$entity->isObjectNew())
                ->firstSystem();

            /** @var $mapping TNW_Salesforce_Model_Mapping */
            foreach ($mappings as $mapping) {
                $entityName = $mapping->getLocalFieldType();
                if ($entityName != 'Billing Item') {
                    continue;
                }

                $newValue = property_exists($record, $mapping->getSfField())
                    ? $record->{$mapping->getSfField()} : null;

                if (empty($newValue)) {
                    $newValue = $mapping->getDefaultValue();
                }

                $field = $mapping->getLocalFieldAttributeCode();
                if ($entity->hasData($field) && $entity->getData($field) != $newValue) {
                    $entity->setData($field, $newValue);
                    $this->addEntityToSave(sprintf('Billing Item %s', $entity->getId()), $entity);
                }
            }

        }

        return $this;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @return mixed
     */
    protected function _getEntityNumber($_entity)
    {
        return $_entity->getIncrementId();
    }

    /**
     * @param $_data
     * @return stdClass
     */
    protected static function _prepareEntityUpdate($_data)
    {
        $_obj = new stdClass();
        $_obj->Id = $_data['salesforce_id'];
        $_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Magento_ID__c'} = $_data['magento_id'];
        $_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'disableMagentoSync__c'} = true;

        return $_obj;
    }

    /**
     * @param $_object stdClass
     * @return string
     */
    protected function _getSfMagentoId($_object)
    {
        $magentoIsField = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Magento_ID__c';
        if (!property_exists($_object, $magentoIsField)) {
            return '';
        }

        return $_object->{$magentoIsField};
    }
}