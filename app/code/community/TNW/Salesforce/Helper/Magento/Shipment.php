<?php

class TNW_Salesforce_Helper_Magento_Shipment extends TNW_Salesforce_Helper_Magento_Abstract
{

    /**
     * @param null $object
     * @return mixed
     */
    public function syncFromSalesforce($object = null)
    {
        $this->_prepare();

        $_sShipmentId = (property_exists($object, "Id") && $object->Id)
            ? $object->Id : null;

        if (!$_sShipmentId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting shipment into Magento: tnw_fulfilment__OrderShipment__c ID is missing");
            $this->_addError('Could not upsert Shipment into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }

        $_msIncrementIdKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . "Magento_ID__c";
        $_msIncrementId    = (property_exists($object, $_msIncrementIdKey) && $object->$_msIncrementIdKey)
            ? $object->$_msIncrementIdKey : null;

        // Lookup product by Magento Id
        if ($_msIncrementId) {
            //Test if user exists
            $sql = "SELECT increment_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_shipment') . "` WHERE increment_id = '" . $_msIncrementId . "'";
            $row = $this->_write->query($sql)->fetch();
            $_msIncrementId = ($row) ? $row['increment_id'] : null;

            if ($_msIncrementId) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Shipment loaded using Magento ID: " . $_msIncrementId);
            }
        }

        if (!$_msIncrementId && $_sShipmentId) {
            // Try to find the user by SF Id
            $sql = "SELECT increment_id FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_shipment') . "` WHERE salesforce_id = '" . $_sShipmentId . "'";
            $row = $this->_write->query($sql)->fetch();
            $_msIncrementId = ($row) ? $row['increment_id'] : null;

            if ($_msIncrementId) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Shipment #" . $_msIncrementId . " Loaded by using Salesforce ID: " . $_sShipmentId);
            }
        }

        $_sOrderIdKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . "Order__c";
        $_sOrderId    = (property_exists($object, $_sOrderIdKey) && $object->$_sOrderIdKey)
            ? $object->$_sOrderIdKey : null;

        if (!$_sOrderId) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError("ERROR Object \"tnw_fulfilment__OrderShipment__c\" lost contact with the object \"Order\"");
            return false;
        }

        $sql = "SELECT entity_id FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` WHERE salesforce_id = '" . $_sOrderId . "'";
        $row = $this->_write->query($sql)->fetch();
        if (!$row) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError(sprintf("ERROR Object \"Order\" (%s) is not synchronized", $_sOrderId));
            return false;
        }

        return $this->_updateMagento($object, $_msIncrementId, $_sShipmentId, $row['entity_id']);
    }

    protected function _updateMagento($object, $_msIncrementId, $_sShipmentId, $_mOrderId)
    {
        if ($_msIncrementId) {
            /** @var Mage_Sales_Model_Order_Shipment $shipment */
            $shipment = Mage::getModel('sales/order_shipment')
                ->loadByIncrementId($_msIncrementId);
        } elseif ($_mOrderId) {
            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order')->load($_mOrderId);

            // Check order existing
            if (!$order->getId()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('The order no longer exists.');
                return false;
            }

            // Check shipment is available to create separate from invoice
            if ($order->getForcedDoShipmentWithInvoice()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Cannot do shipment for the order separately from invoice.');
                return false;
            }

            // Check shipment create availability
            if (!$order->canShip()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Cannot do shipment for the order.');
                return false;
            }

            $_shipmentItemKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'OrderShipmentItem__r';
            if (!property_exists($object, $_shipmentItemKey)) {
                return false;
            }

            if (!property_exists($object->$_shipmentItemKey, 'records')) {
                return false;
            }

            $hasSalesforceId = $order->getItemsCollection()
                ->walk('getSalesforceId');

            $_iItemQuantityKey  = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Quantity__c';
            $_iItemOrderItemKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Order_Item__c';

            $savedQtys = array();
            foreach ($object->$_shipmentItemKey->records as $record) {
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

            /** @var Mage_Sales_Model_Order_Shipment $shipment */
            $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($savedQtys);
            $shipment->register();

            $shipment->getOrder()->setIsInProcess(true);
            $this->addEntityToSave('Order', $shipment->getOrder());
        }

        $shipment->setData('salesforce_id', $_sShipmentId)
            ->setData('sf_insync', 1);
        $this->addEntityToSave('Shipment', $shipment);

        $_shipmentTrackingKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'OrderShipmentTracking__r';
        if (property_exists($object, $_shipmentTrackingKey) && $object->$_shipmentTrackingKey->totalSize > 0) {
            $hasNumber = $shipment->getTracksCollection()
                ->walk('getNumber');

            $_sTrackingCarrierKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Carrier__c';
            $_sTrackingNumberKey  = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Number__c';
            foreach ($object->$_shipmentTrackingKey->records as $record) {
                if (false !== array_search($record->$_sTrackingNumberKey, $hasNumber)) {
                    continue;
                }

                $codeCarrier = (property_exists($record, $_sTrackingCarrierKey) && !empty($record->$_sTrackingCarrierKey))
                    ? $record->$_sTrackingCarrierKey : 'custom';

                /** @var Mage_Sales_Model_Order_Shipment_Track $track */
                $track = Mage::getModel('sales/order_shipment_track')
                    ->addData(array(
                        'carrier_code'  => $codeCarrier,
                        'title'         => $record->Name,
                        'number'        => $record->$_sTrackingNumberKey,
                    ));

                $shipment->addTrack($track);
            }
        }

        $this->_updateMappedEntityFields($object, $shipment)
            ->_updateMappedEntityItemFields($object, $shipment)
            ->saveEntities();

        return $shipment;
    }

    /**
     * @param $object
     * @param $shipment Mage_Sales_Model_Order_Shipment
     * @return $this
     */
    protected function _updateMappedEntityFields($object, $shipment)
    {
        /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $mappings */
        $mappings = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addObjectToFilter('OrderShipment')
            ->addFieldToFilter('sf_magento_enable', 1)
            ->addFieldToFilter('sf_magento_type', array(
                TNW_Salesforce_Model_Mapping::SET_TYPE_UPSERT,
                ($shipment->isObjectNew())
                    ? TNW_Salesforce_Model_Mapping::SET_TYPE_INSERT : TNW_Salesforce_Model_Mapping::SET_TYPE_UPDATE
            ));

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
                    $entity = $shipment->$method();

                    $keyState = sprintf('tnw_fulfilment__%s_Country__c', $entityName);
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

                case 'Shipment':
                    $entity = $shipment;
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
            $shipment->addComment(
                "Fields are updated by salesforce:\n"
                . implode("\n", $updateFieldsLog)
            );
        }

        return $this;
    }

    /**
     * @param $object
     * @param $shipment Mage_Sales_Model_Order_Shipment
     * @return $this
     */
    protected function _updateMappedEntityItemFields($object, $shipment)
    {
        $hasSalesforceId = $shipment->getOrder()->getItemsCollection()
            ->walk('getSalesforceId');

        /** @var Mage_Sales_Model_Resource_Order_Invoice_Item_Collection $_shipmentItemCollection */
        $_shipmentItemCollection = $shipment->getItemsCollection();
        $hasOrderId = $_shipmentItemCollection
            ->walk('getOrderItemId');

        $_shipmentItemKey   = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'OrderShipmentItem__r';
        $_sItemOrderItemKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Order_Item__c';
        foreach ($object->$_shipmentItemKey->records as $record) {
            $orderItemId = array_search($record->$_sItemOrderItemKey, $hasSalesforceId);
            if (false === $orderItemId) {
                continue;
            }

            $shipmentItemId = array_search($orderItemId, $hasOrderId);
            if (false === $shipmentItemId) {
                continue;
            }

            /** @var Mage_Sales_Model_Order_Invoice_Item $entity */
            $entity = $_shipmentItemCollection->getItemById($shipmentItemId);

            /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $mappings */
            $mappings = Mage::getResourceModel('tnw_salesforce/mapping_collection')
                ->addObjectToFilter('OrderShipmentItem')
                ->addFieldToFilter('sf_magento_enable', 1)
                ->addFieldToFilter('sf_magento_type', array(
                    TNW_Salesforce_Model_Mapping::SET_TYPE_UPSERT,
                    ($entity->isObjectNew())
                        ? TNW_Salesforce_Model_Mapping::SET_TYPE_INSERT : TNW_Salesforce_Model_Mapping::SET_TYPE_UPDATE
                ));

            /** @var $mapping TNW_Salesforce_Model_Mapping */
            foreach ($mappings as $mapping) {
                $entityName = $mapping->getLocalFieldType();
                if ($entityName != 'Shipment Item') {
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
                    $this->addEntityToSave(sprintf('Shipment Item %s', $entity->getId()), $entity);
                }
            }
        }

        return $this;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Shipment
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
        $_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'Magento_ID__c'} = $_data['magento_id'];
        $_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT . 'disableMagentoSync__c'} = true;

        return $_obj;
    }
}