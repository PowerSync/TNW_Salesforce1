<?php
/**
 * Created by PhpStorm.
 * User: evgeniy
 * Date: 10.11.16
 * Time: 13:19
 */

abstract class TNW_Salesforce_Helper_Magento_Order_Base extends TNW_Salesforce_Helper_Magento_Abstract
{
    /**
     * @var string
     */
    protected $_mappingEntityName = '';

    /**
     * @var string
     */
    protected $_mappingEntityItemName = '';

    /**
     * @comment salesforce entity alias
     * @var string
     */
    protected $_salesforceEntityName = '';


    abstract     protected function _updateMagento($object, $_mMagentoId, $_sSalesforceId);


    /**
     * @return string
     * @throws Exception
     */
    public function getItemsField() {

        if (empty($this->_salesforceEntityName)) {
            throw new Exception(Mage::helper('tnw_salesforce')->__('Unknown salesforce entity!'));
        }

        /**
         * @var $helper TNW_Salesforce_Helper_Salesforce_Order|TNW_Salesforce_Helper_Salesforce_Opportunity
         */
        $helper = Mage::helper('tnw_salesforce/salesforce_' . $this->_salesforceEntityName);
        return $helper->getItemsField();
    }

    /**
     * @param null $object
     * @return bool|null
     */
    public function getMagentoId($object = null, $_sSalesforceId)
    {
        $_mMagentoId = null;

        $_sMagentoIdKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . "Magento_ID__c";
        $_sMagentoId    = (property_exists($object, $_sMagentoIdKey) && $object->$_sMagentoIdKey)
            ? $object->$_sMagentoIdKey : null;

        $orderTable = Mage::helper('tnw_salesforce')->getTable('sales_flat_order');
        if (!empty($_sMagentoId)) {
            //Test if user exists
            $sql = "SELECT increment_id  FROM `$orderTable` WHERE increment_id = '$_sMagentoId'";
            $row = $this->_write->query($sql)->fetch();
            if ($row) {
                $_mMagentoId = $row['increment_id'];

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Order loaded using Magento ID: " . $_mMagentoId);
            }
        }

        if (is_null($_mMagentoId) && !empty($_sSalesforceId)) {
            // Try to find the user by SF Id
            $sql = "SELECT increment_id FROM `$orderTable` WHERE salesforce_id = '$_sSalesforceId'";
            $row = $this->_write->query($sql)->fetch();
            if ($row) {
                $_mMagentoId = $row['increment_id'];

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Order #" . $_mMagentoId . " Loaded by using Salesforce ID: " . $_sSalesforceId);
            }
        }

        return $_mMagentoId;
    }

    /**
     * @param null $object
     * @return bool
     */
    public function syncFromSalesforce($object = null)
    {
        $this->_prepare();

        $_sSalesforceId = (property_exists($object, "Id") && $object->Id)
            ? $object->Id : null;

        if (!$_sSalesforceId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting order into Magento: ID is missing");
            $this->_addError('Could not upsert Order into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }

        $_mMagentoId = $this->getMagentoId($object, $_sSalesforceId);

        return $this->_updateMagento($object, $_mMagentoId, $_sSalesforceId);
    }

    /**
     * @param $object
     * @param $order Mage_Sales_Model_Order
     * @return $this
     */
    protected function _updateNotes($object, $order)
    {
        if (!property_exists($object, 'Notes')) {
            return $this;
        }

        if (empty($object->Notes->records)) {
            return $this;
        }

        $salesforceIds = $this->salesforceIdsByNotes($order->getStatusHistoryCollection());
        foreach ($object->Notes->records as $record) {
            if (empty($record->Body)) {
                continue;
            }

            $noteId = array_search($record->Id, $salesforceIds);
            if ($noteId === false) {
                $history = Mage::getModel('sales/order_status_history')
                    ->setStatus($order->getStatus())
                    ->setComment($record->Body)
                    ->setSalesforceId($record->Id)
                    ->setEntityName(Mage_Sales_Model_Order::HISTORY_ENTITY_NAME);

                $order->addStatusHistory($history);
            }
            else {
                $order->getStatusHistoryCollection()
                    ->getItemById($noteId)
                    ->setComment($record->Body);
            }
        }

        $this->addEntityToSave('Order', $order);
        return $this;
    }

    /**
     * @param Mage_Sales_Model_Entity_Order_Status_History_Collection $notesCollection
     * @return array
     */
    protected function salesforceIdsByNotes($notesCollection)
    {
        return $notesCollection->walk('getSalesforceId');
    }

    /**
     * @param $type
     * @return Varien_Data_Collection_Db
     */
    public function getMappingByType($entity, $type)
    {
        return
            $mappings = Mage::getResourceModel('tnw_salesforce/mapping_collection')
                ->addObjectToFilter($type)
                ->addFilterTypeSM((bool)$entity->getId())
                ->firstSystem();
    }

    /**
     * @param $object
     * @param $order Mage_Sales_Model_Order
     * @return $this
     */
    protected function _updateMappedEntityFields($object, $order)
    {
        /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $mappings */
        $mappings =$this->getMappingByType($order, $this->_mappingEntityName);

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
     * @param $_entity Mage_Sales_Model_Order
     * @return mixed
     */
    protected function _getEntityNumber($_entity)
    {
        return $_entity->getIncrementId();
    }

    /**
     * @param $object stdClass
     * @param $order Mage_Sales_Model_Order
     * @param bool $isUpdate
     * @return $this
     */
    protected function _updateMappedEntityItemFields($object, $order, $isUpdate = true)
    {
        /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $mappings */
        $mappings = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addObjectToFilter($this->_mappingEntityItemName)
            ->addFilterTypeSM($isUpdate)
            ->firstSystem();

        /** @var Mage_Sales_Model_Resource_Order_Item_Collection $_orderItemCollection */
        $_orderItemCollection = $order->getItemsCollection();
        $hasSalesforceId = $this->salesforceIdsByOrderItems($_orderItemCollection);

        foreach ($object->{$this->getItemsField()}->records as $record) {
            $orderItemId = array_search($record->Id, $hasSalesforceId);
            if (false === $orderItemId) {
                continue;
            }

            /** @var Mage_Sales_Model_Order_Item $entity */
            $entity = $_orderItemCollection->getItemById($orderItemId);

            /** @var $mapping TNW_Salesforce_Model_Mapping */
            foreach ($mappings as $mapping) {

                //-------------------

                $newValue = property_exists($object, $mapping->getSfField())
                    ? $object->{$mapping->getSfField()} : null;

                if (empty($newValue)) {
                    $newValue = $mapping->getDefaultValue();
                }

                Mage::getModel('tnw_salesforce/mapping_type_order_item')
                    ->setMapping($mapping)
                    ->setValue($entity, $newValue);

                $field = $mapping->getLocalFieldAttributeCode();
                if (!is_null($entity->getOrigData($field)) && $entity->getOrigData($field) != $entity->getData($field)) {

                    //add info about updated field to order comment
                    $updateFieldsLog[] = sprintf('%s - from "%s" to "%s"',
                        $mapping->getLocalField(), $entity->getOrigData($field), $entity->getData($field));
                }
            }

            $this->addEntityToSave(sprintf('Order Item %s', $entity->getId()), $entity);
        }

           return $this;
    }

    /**
     * @param Mage_Sales_Model_Resource_Order_Item_Collection $orderItemCollection
     * @return array
     */
    protected function salesforceIdsByOrderItems($orderItemCollection)
    {
        return $orderItemCollection->walk('getSalesforceId');
    }
}