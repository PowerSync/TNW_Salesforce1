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

        $_isNew = false;

        $_sInvoiceId = (property_exists($object, "Id") && $object->Id)
            ? $object->Id : null;

        if (!$_sInvoiceId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting invoice into Magento: tnw_fulfilment__OrderInvoice__c ID is missing");
            $this->_addError('Could not upsert Invoice into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }

        $_mInvoiceId = (property_exists($object, TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . "Magento_ID__c") && $object->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Magento_ID__c'})
            ? $object->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Magento_ID__c'} : null;

        // Lookup product by Magento Id
        if ($_mInvoiceId) {
            //Test if user exists
            $sql = "SELECT entity_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_invoice') . "` WHERE entity_id = '" . $_mInvoiceId . "'";
            $row = $this->_write->query($sql)->fetch();
            if (!$row) {
                // Magento ID exists in Salesforce, user must have been deleted. Will re-create with the same ID
                $_isNew = true;
            }
        }

        if ($_mInvoiceId && !$_isNew) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Product loaded using Magento ID: " . $_mInvoiceId);
        } else {
            // No Magento ID
            if ($_sInvoiceId) {
                // Try to find the user by SF Id
                $sql = "SELECT entity_id FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_invoice') . "` WHERE salesforce_id = '" . $_sInvoiceId . "'";
                $row = $this->_write->query($sql)->fetch();
                $_mInvoiceId = ($row) ? $row['entity_id'] : null;
            }

            if ($_mInvoiceId) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Invoice #" . $_mInvoiceId . " Loaded by using Salesforce ID: " . $_mInvoiceId);
            } else {
                //Brand new invoice
                $_isNew = true;
            }
        }

        $_sOrderId = (property_exists($object, TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . "Order__c") && $object->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Order__c'})
            ? $object->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Order__c'} : null;

        if (!$_sOrderId) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError("ERROR Object \"tnw_fulfilment__OrderInvoice__c\" lost contact with the object \"Order\"");
            return false;
        }

        $sql = "SELECT entity_id FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` WHERE salesforce_id = '" . $_sOrderId . "'";
        $row = $this->_write->query($sql)->fetch();
        if (!$row) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError(sprintf("ERROR Object \"Order\" (%s) is not synchronized", $_sOrderId));
            return false;
        }

        return $this->_updateMagento($object, $_mInvoiceId, $_sInvoiceId, $row['entity_id'], $_isNew);
    }

    protected function _updateMagento($object, $_mInvoiceId, $_sInvoiceId, $_mOrderId, $_isNew)
    {
        if ($_mInvoiceId) {
            /** @var Mage_Sales_Model_Order_Invoice $invoice */
            $invoice = Mage::getModel('sales/order_invoice')->load($_mInvoiceId);
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

            $_invoiceItemKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'OrderInvoiceItem__r';
            if (!property_exists($object, $_invoiceItemKey)) {
                return false;
            }

            if (!property_exists($object->$_invoiceItemKey, 'records')) {
                return false;
            }

            $hasSalesforceId = $order->getItemsCollection()
                ->walk('getSalesforceId');

            $_iItemQuantityKey  = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Quantity__c';
            $_iItemOrderItemKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Order_Item__c';

            $savedQtys = array();
            foreach ($object->$_invoiceItemKey->records as $record) {
                if (!property_exists($record, $_iItemOrderItemKey)) {
                    continue;
                }

                $orderItemId = array_search($record->$_iItemOrderItemKey, $hasSalesforceId);
                if (false === $orderItemId) {
                    continue;
                }

                if (!property_exists($record, $_iItemQuantityKey)) {
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
     * @param $invoice
     * @return $this
     */
    protected function _updateMappedEntityFields($object, $invoice)
    {
        $mappings = Mage::getModel('tnw_salesforce/mapping')
            ->getCollection()
            ->addObjectToFilter('OrderInvoice');

        $updateFieldsLog = array();
        /** @var $mapping TNW_Salesforce_Model_Mapping */
        foreach ($mappings as $mapping) {
            //skip if cannot find field in object
            if (!isset($object->{$mapping->getSfField()})) {
                continue;
            }

            $newValue   = $object->{$mapping->getSfField()};
            $entityName = $mapping->getLocalFieldType();
            $field      = $mapping->getLocalFieldAttributeCode();

            $entity = false;
            switch ($entityName) {
                case 'Shipping':
                case 'Billing':
                    $method = sprintf('get%sAddress', $entityName);
                    $entity = $invoice->$method();
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
        $hasSalesforceId = $invoice->getOrder()->getItemsCollection()
            ->walk('getSalesforceId');

        /** @var Mage_Sales_Model_Resource_Order_Invoice_Item_Collection $_invoiceItemCollection */
        $_invoiceItemCollection = $invoice->getItemsCollection();
        $hasOrderId = $_invoiceItemCollection
            ->walk('getOrderItemId');

        $_invoiceItemKey    = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'OrderInvoiceItem__r';
        $_iItemOrderItemKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Order_Item__c';
        foreach ($object->$_invoiceItemKey->records as $record) {
            $orderItemId = array_search($record->$_iItemOrderItemKey, $hasSalesforceId);
            if (false === $orderItemId) {
                continue;
            }

            $invoiceItemId = array_search($orderItemId, $hasOrderId);
            if (false === $invoiceItemId) {
                continue;
            }

            /** @var Mage_Sales_Model_Order_Invoice_Item $entity */
            $entity = $_invoiceItemCollection->getItemById($invoiceItemId);

            $mappings = Mage::getModel('tnw_salesforce/mapping')
                ->getCollection()
                ->addObjectToFilter('OrderInvoiceItem');

            /** @var $mapping TNW_Salesforce_Model_Mapping */
            foreach ($mappings as $mapping) {
                $entityName = $mapping->getLocalFieldType();
                if ($entityName != 'Billing Item') {
                    continue;
                }

                //skip if cannot find field in object
                if (!isset($record->{$mapping->getSfField()})) {
                    continue;
                }

                $newValue   = $record->{$mapping->getSfField()};
                $field      = $mapping->getLocalFieldAttributeCode();

                if ($entity->hasData($field) && $entity->getData($field) != $newValue) {
                    $entity->setData($field, $newValue);
                    $this->addEntityToSave(sprintf('Billing Item %s', $entity->getId()), $entity);
                }
            }

        }

        return $this;
    }
}