<?php

class TNW_Salesforce_Helper_Magento_Creditmemo extends TNW_Salesforce_Helper_Magento_Abstract
{
    /**
     * @param stdClass $object
     * @return mixed
     */
    public function syncFromSalesforce($object = null)
    {
        $this->_prepare();

        $_sOrderId = (property_exists($object, "Id") && $object->Id)
            ? $object->Id : null;

        if (!$_sOrderId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting order into Magento: ID is missing");
            $this->_addError('Could not upsert Order into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }

        $_miIncrementIdKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . "Magento_ID__c";
        $_miIncrementId    = (property_exists($object, $_miIncrementIdKey) && $object->$_miIncrementIdKey)
            ? str_replace('cm_', '', $object->$_miIncrementIdKey) : null;

        if ($_miIncrementId) {
            //Test if user exists
            $sql = "SELECT increment_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_creditmemo') . "` WHERE increment_id = '" . $_miIncrementId . "'";
            $row = $this->_write->query($sql)->fetch();
            $_miIncrementId = ($row) ? $row['increment_id'] : null;

            if ($_miIncrementId) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Credit Memo loaded using Magento ID: " . $_miIncrementId);
            }
        }

        if (!$_miIncrementId && $_sOrderId) {
            // Try to find the user by SF Id
            $sql = "SELECT increment_id FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_creditmemo') . "` WHERE salesforce_id = '" . $_sOrderId . "'";
            $row = $this->_write->query($sql)->fetch();
            $_miIncrementId = ($row) ? $row['increment_id'] : null;

            if ($_miIncrementId) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Credit Memo #" . $_miIncrementId . " Loaded by using Salesforce ID: " . $_sOrderId);
            }
        }

        $_sOriginalOrderIdKey = 'OriginalOrderId';
        $_sOriginalOrderId    = (property_exists($object, $_sOriginalOrderIdKey) && $object->$_sOriginalOrderIdKey)
            ? $object->$_sOriginalOrderIdKey : null;

        if (!$_sOriginalOrderId) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError("ERROR Object \"OriginalOrderId\" lost contact with the object \"Order\"");
            return false;
        }

        $sql = "SELECT entity_id FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` WHERE salesforce_id = '" . $_sOriginalOrderId . "'";
        $row = $this->_write->query($sql)->fetch();
        if (!$row) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError(sprintf("ERROR Object \"Order\" (%s) is not synchronized", $_sOrderId));
            return false;
        }

        return $this->_updateMagento($object, $_miIncrementId, $_sOrderId, $row['entity_id']);
    }

    protected function _updateMagento($object, $_miIncrementId, $_sOrderId, $_mOriginalOrderId)
    {
        if ($_miIncrementId) {
            /** @var Mage_Sales_Model_Order_Creditmemo $creditMemo */
            $creditMemo = Mage::getModel('sales/order_creditmemo')
                ->load($_miIncrementId, 'increment_id');
        } elseif ($_mOriginalOrderId) {
            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order')->load($_mOriginalOrderId);

            // Check order existing
            if (!$order->getId()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('The order no longer exists.');
                return false;
            }

            // Check credit memo create availability
            if (!$order->canCreditmemo()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Cannot create credit memo for the order.');
                return false;
            }

            $_orderItemKey = 'OrderItems';
            if (!property_exists($object, $_orderItemKey)) {
                return false;
            }

            if (!property_exists($object->$_orderItemKey, 'records')) {
                return false;
            }

            $hasSalesforceId = $order->getItemsCollection()
                ->walk('getSalesforceId');

            $_iItemQuantityKey  = 'Quantity';
            $_iItemOrderItemKey = 'OriginalOrderItemId';

            $savedQtys = array(
                'qtys' => array()
            );

            foreach ($object->$_orderItemKey->records as $record) {
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

                /** @var Mage_Sales_Model_Order_Item $item */
                $item = $order->getItemsCollection()->getItemById($orderItemId);
                if ($item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                    /** @var Mage_Sales_Model_Order_Item $_item */
                    foreach ($item->getChildrenItems() as $_item) {
                        $savedQtys['qtys'][$_item->getId()] = abs((int)$record->$_iItemQuantityKey);
                    }

                    continue;
                }

                $savedQtys['qtys'][$orderItemId] = abs((int)$record->$_iItemQuantityKey);
            }

            /** @var Mage_Sales_Model_Order_Creditmemo $creditMemo */
            $creditMemo = Mage::getModel('sales/service_order', $order)->prepareCreditmemo($savedQtys);
            if (($creditMemo->getGrandTotal() <=0) && (!$creditMemo->getAllowZeroGrandTotal())) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Credit memo\'s total must be positive.');

                return false;
            }

            /**
             * Process back to stock flags
             *
             * @var Mage_Sales_Model_Order_Creditmemo_Item $creditMemoItem
             */
            foreach ($creditMemo->getAllItems() as $creditMemoItem) {
                $creditMemoItem->setBackToStock(Mage::helper('cataloginventory')->isAutoReturnEnabled());
            }

            $creditMemo->register();
            $this->addEntityToSave('Order', $creditMemo->getOrder());
        }

        $this->addEntityToSave('Credit Memo', $creditMemo);
        $creditMemo->addData(array(
            'salesforce_id' => $_sOrderId,
            'sf_insync'     => 1
        ));

        $this->_updateMappedEntityFields($object, $creditMemo)
            ->_updateMappedEntityItemFields($object, $creditMemo)
            ->saveEntities();

        return $creditMemo;
    }

    /**
     * @param $object
     * @param $creditmemo Mage_Sales_Model_Order_Creditmemo
     * @return $this
     */
    protected function _updateMappedEntityFields($object, $creditmemo)
    {
        /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $mappings */
        $mappings = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addObjectToFilter('OrderCreditMemo')
            ->addFilterTypeSM(!$creditmemo->isObjectNew());

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
                    $entity = $creditmemo->$method();

                    switch ($field) {
                        case 'country_id';
                            $countryId = $this->findCountryId($newValue);
                            if ($countryId) {
                                $newValue = $countryId;
                            }
                            break;

                        case 'region':
                        case 'region_id':
                            foreach (array(sprintf('%sCountryCode', $entityName), sprintf('%sBillingCountry', $entityName)) as $item) {
                                if (!property_exists($object, $item)) {
                                    continue;
                                }

                                $countryId = $this->findCountryId($object->$item);
                                if ($countryId) {
                                    $regionId = $this->findRegionId($countryId, $newValue);
                                    if ($regionId) {
                                        $field    = 'region_id';
                                        $newValue = $regionId;
                                        break;
                                    }
                                }
                            }
                            break;
                    }
                    break;

                case 'Credit Memo':
                    $entity = $creditmemo;

                    switch ($field) {
                        case 'sf_status':
                            $statusId = $this->_findMagentoStatus($newValue);
                            if ($statusId) {
                                $newValue = $statusId;
                                $field = 'state';
                            }
                            break;
                    }
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
            $creditmemo->addComment(
                "Fields are updated by salesforce:\n"
                . implode("\n", $updateFieldsLog)
            );
        }

        return $this;
    }

    protected function _findMagentoStatus($sfStatus)
    {
        static $mappingState = null;
        if (is_null($mappingState)) {
            /** @var TNW_Salesforce_Model_Mysql4_Order_Creditmemo_Status_Collection $collection */
            $collection     = Mage::getResourceModel('tnw_salesforce/order_creditmemo_status_collection');
            $mappingState   = $collection->toReverseStatusHash();
        }

        if (isset($mappingState[$sfStatus])) {
            return $mappingState[$sfStatus];
        }

        return null;
    }

    /**
     * @param $object stdClass
     * @param $creditmemo Mage_Sales_Model_Order_Creditmemo
     * @return $this
     */
    protected function _updateMappedEntityItemFields($object, $creditmemo)
    {
        $hasSalesforceId = $creditmemo->getOrder()->getItemsCollection()
            ->walk('getSalesforceId');

        /** @var Mage_Sales_Model_Resource_Order_Creditmemo_Item_Collection $_creditmemoItemCollection */
        $_creditmemoItemCollection = $creditmemo->getItemsCollection();
        $hasOrderId = $_creditmemoItemCollection
            ->walk('getOrderItemId');

        $_orderItemKey      = 'OrderItems';
        $_iItemOrderItemKey = 'OriginalOrderItemId';
        foreach ($object->$_orderItemKey->records as $record) {
            $orderItemId = array_search($record->$_iItemOrderItemKey, $hasSalesforceId);
            if (false === $orderItemId) {
                continue;
            }

            $creditMemoItemId = array_search($orderItemId, $hasOrderId);
            if (false === $creditMemoItemId) {
                continue;
            }

            /** @var Mage_Sales_Model_Order_Creditmemo_Item $entity */
            $entity = $_creditmemoItemCollection->getItemById($creditMemoItemId);

            /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $mappings */
            $mappings = Mage::getResourceModel('tnw_salesforce/mapping_collection')
                ->addObjectToFilter('OrderCreditMemoItem')
                ->addFilterTypeSM(!$entity->isObjectNew());

            /** @var $mapping TNW_Salesforce_Model_Mapping */
            foreach ($mappings as $mapping) {
                $entityName = $mapping->getLocalFieldType();
                if ($entityName != 'Credit Memo Item') {
                    continue;
                }

                //skip if cannot find field in object
                if (!isset($record->{$mapping->getSfField()})) {
                    continue;
                }

                $newValue   = $record->{$mapping->getSfField()};
                $field      = $mapping->getLocalFieldAttributeCode();

                switch ($field) {
                    case 'qty':
                        $newValue = abs($newValue);
                        break;
                }

                if ($entity->hasData($field) && $entity->getData($field) != $newValue) {
                    $entity->setData($field, $newValue);
                    $this->addEntityToSave(sprintf('Billing Item %s', $entity->getId()), $entity);
                }
            }

        }

        return $this;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Creditmemo
     * @return mixed
     */
    protected function _getEntityNumber($_entity)
    {
        return 'cm_'.$_entity->getIncrementId();
    }
}