<?php

class TNW_Salesforce_Helper_Magento_Opportunity extends TNW_Salesforce_Helper_Magento_Abstract
{

    /**
     * @param stdClass $object
     * @return mixed
     */
    public function syncFromSalesforce($object = null)
    {
        $this->_prepare();

        $_sOpportunityId = (property_exists($object, "Id") && $object->Id)
            ? $object->Id : null;

        if (!$_sOpportunityId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting Opportunity into Magento: Opportunity ID is missing");
            $this->_addError('Could not upsert Order into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }

        $_moIncrementIdKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . "Magento_ID__c";
        $_moIncrementId    = (property_exists($object, $_moIncrementIdKey) && $object->$_moIncrementIdKey)
            ? $object->$_moIncrementIdKey : null;

        if ($_moIncrementId) {
            //Test if order exists
            $sql = "SELECT increment_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` WHERE increment_id = '" . $_moIncrementId . "'";
            $row = $this->_write->query($sql)->fetch();
            $_moIncrementId = ($row) ? $row['increment_id'] : null;

            if ($_moIncrementId) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Order loaded using Magento ID: " . $_moIncrementId);
            }
        }

        if (!$_moIncrementId && $_sOpportunityId) {
            // Try to find the user by SF Id
            $sql = "SELECT increment_id FROM `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` WHERE salesforce_id = '" . $_sOpportunityId . "'";
            $row = $this->_write->query($sql)->fetch();
            $_moIncrementId = ($row) ? $row['increment_id'] : null;

            if ($_moIncrementId) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Order #" . $_moIncrementId . " Loaded by using Salesforce ID: " . $_sOpportunityId);
            }
        }

        if (!$_moIncrementId) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('SKIPPING: could not find the order by number: '. $_moIncrementId);
            return false;
        }

        return $this->_updateMagento($object, $_moIncrementId, $_sOpportunityId);
    }

    protected function _updateMagento($object, $_moIncrementId, $_sOpportunityId)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')
            ->loadByIncrementId($_moIncrementId);

        $order->addData(array(
            'salesforce_id' => $_sOpportunityId,
            'sf_insync'     => 1
        ));

        $this->_updateMappedEntityFields($object, $order)
            ->_updateMappedEntityItemFields($object, $order)
            ->saveEntities();

        return $order;
    }

    /**
     * @param $object
     * @param $order Mage_Sales_Model_Order
     * @return $this
     */
    protected function _updateMappedEntityFields($object, $order)
    {
        /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $mappings */
        $mappings = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addObjectToFilter('Opportunity')
            ->addFilterTypeSM(!$order->isObjectNew())
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
            switch ($entityName) {
                case 'Shipping':
                case 'Billing':
                    $method = sprintf('get%sAddress', $entityName);
                    $entity = $order->$method();
                    Mage::getModel('tnw_salesforce/mapping_type_address')
                        ->setMapping($mapping)
                        ->setValue($entity, $newValue);

                    $this->addEntityToSave($entityName, $entity);
                    break;

                case 'Order':
                    $entity = $order;
                    Mage::getModel('tnw_salesforce/mapping_type_order')
                        ->setMapping($mapping)
                        ->setValue($entity, $newValue);

                    $this->addEntityToSave($entityName, $entity);
                    break;

                default:
                    continue 2;
            }

            $field = $mapping->getLocalFieldAttributeCode();
            if (!is_null($entity->getOrigData($field)) && $entity->getOrigData($field) != $entity->getData($field)) {

                //add info about updated field to order comment
                $updateFieldsLog[] = sprintf('%s - from "%s" to "%s"',
                    $mapping->getLocalField(), $entity->getOrigData($field), $entity->getData($field));
            }
        }

        //add comment about all updated fields
        if (!empty($updateFieldsLog)) {
            $order->addStatusHistoryComment(
                "Fields are updated by salesforce:\n"
                . implode("\n", $updateFieldsLog)
            );
        }

        return $this;
    }

    /**
     * @param $object
     * @param $order Mage_Sales_Model_Order
     * @return $this
     */
    protected function _updateMappedEntityItemFields($object, $order)
    {
        /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $mappings */
        $mappings = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addObjectToFilter('OpportunityLineItem')
            ->addFilterTypeSM(true)
            ->firstSystem();

        /** @var Mage_Sales_Model_Resource_Order_Item_Collection $_invoiceItemCollection */
        $_orderItemCollection = $order->getItemsCollection();
        $hasSalesforceId      = $_orderItemCollection->walk('getSalesforceId');

        foreach ($object->OpportunityLineItems->records as $record) {
            $orderItemId = array_search($record->Id, $hasSalesforceId);
            if (false === $orderItemId) {
                continue;
            }

            /** @var Mage_Sales_Model_Order_Item $entity */
            $entity = $_orderItemCollection->getItemById($orderItemId);

            /** @var $mapping TNW_Salesforce_Model_Mapping */
            foreach ($mappings as $mapping) {
                if ($mapping->getLocalFieldType() != 'Order Item') {
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
                    $this->addEntityToSave(sprintf('Order Item %s', $entity->getId()), $entity);
                }
            }
        }

        return $this;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @return mixed
     */
    protected function _getEntityNumber($_entity)
    {
        return $_entity->getIncrementId();
    }
}