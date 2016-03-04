<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Import_Order
{
    /**
     * @var stdClass
     */
    protected $_object;

    /**
     * @var Mage_Sales_Model_Order
     */
    protected $_order;

    /**
     * @var Mage_Customer_Model_Customer
     */
    protected $_orderCustomer;

    /**
     * @var string
     */
    protected $_objectType = 'Order';

    /**
     * @var array
     */
    protected $_entitiesToSave = array();

    /**
     * @param $string
     */
    protected function log($string)
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($string);
    }

    /**
     * @param stdClass $object
     *
     * @return TNW_Salesforce_Model_Import_Order
     */
    public function setObject($object)
    {
        $this->_object = $object;

        return $this;
    }

    /**
     * @return stdClass
     */
    protected function getObject()
    {
        return $this->_object;
    }

    /**
     * @return bool|Mage_Sales_Model_Order
     */
    protected function getOrder()
    {
        if (is_null($this->_order)) {
            if (isset($this->getObject()->Id) && $this->getObject()->Id) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Updating salesforce order ' . $this->getObject()->Id);
            }

            $magentoId = Mage::helper('tnw_salesforce/config')->getMagentoIdField();
            if (!isset($this->getObject()->$magentoId) || !$this->getObject()->$magentoId) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('There is no magento id');
                $this->_order = false;
                return false;
            }

            $this->_order = Mage::getModel('sales/order')->loadByIncrementId($this->getObject()->$magentoId);
            if (!$this->_order->getId()) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(
                    sprintf('Order with  Id not found, skipping update', $this->getObject()->$magentoId));
                $this->_order = false;
                return false;
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Order Loaded: ' . $this->_order->getIncrementId());
        }
        return $this->_order;
    }

    /**
     * @throws Exception
     */
    public function process()
    {
        if ($this->getOrder()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Attempt to update order status');
            $this->updateStatus()
                ->updateMappedFields()
                ->saveEntities();
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Order object is not set');
        }
    }

    /**
     * @return $this
     */
    protected function updateMappedFields()
    {
        //clear log fields before update
        $updateFieldsLog = array();

        $additional = array();

        $mappings = Mage::getModel('tnw_salesforce/mapping')
            ->getCollection()
            ->addObjectToFilter($this->_objectType)
            ->addFieldToFilter('active', 1);

        foreach ($mappings as $mapping) {
            //skip if cannot find field in object
            if (!isset($this->getObject()->{$mapping->getSfField()})) {
                continue;
            }
            $newValue = $this->getObject()->{$mapping->getSfField()};
            /** @var $mapping TNW_Salesforce_Model_Mapping */
            $entityName = $mapping->getLocalFieldType();
            $field = $mapping->getLocalFieldAttributeCode();
            $entity = $this->getEntity($entityName);
            if (!$entity) {
                continue;
            }

            if ($entity instanceof Mage_Sales_Model_Order_Address) {
                $additional[$entityName][$field] = $newValue;
            }

            if ($entity->hasData($field) && $entity->getData($field) != $newValue) {
                $entity->setData($field, $newValue);
                $this->addEntityToSave($entityName, $entity);

                //add info about updated field to order comment
                $updateFieldsLog[] = sprintf('%s - from "%s" to "%s"',
                    $mapping->getLocalField(), $entity->getOrigData($field), $newValue);
            }
        }

        foreach ($additional as $entityName => $address) {
            $entity = $this->getEntity($entityName);
            if (!$entity) {
                continue;
            }

            if (!$entity instanceof Mage_Sales_Model_Order_Address) {
                continue;
            }

            $_countryCode = $this->_getCountryId($address['country_id']);
            $_regionCode  = null;
            if ($_countryCode) {
                foreach (array('region_id', 'region') as $_regionField) {
                    if (!isset($address[$_regionField])) {
                        continue;
                    }

                    $_regionCode = $this->_getRegionId($address[$_regionField], $_countryCode);
                    if (!empty($_regionCode)) {
                        break;
                    }
                }
            }

            $entity->addData(array(
                'country_id' => $_countryCode,
                'region_id'  => $_regionCode,
            ));

            $this->addEntityToSave($entityName, $entity);
        }

        //add comment about all updated fields
        if (!empty($updateFieldsLog)) {
            $this->getOrder()->addStatusHistoryComment(
                "Fields are updated by salesforce:\n"
                . implode("\n", $updateFieldsLog)
            );
        }

        return $this;
    }

    protected function _getCountryId($_name  = NULL)
    {
        foreach(Mage::getModel('directory/country_api')->items() as $_country) {
            if (in_array($_name, $_country)) {
                return $_country['country_id'];
            }
        }

        return NULL;
    }

    protected function _getRegionId($_name  = NULL, $_countryCode = NULL)
    {
        foreach(Mage::getModel('directory/region_api')->items($_countryCode) as $_region) {
            if (in_array($_name, $_region)) {
                return $_region['region_id'];
            }
        }

        return NULL;
    }

    /**
     * @param string $entityName
     *
     * @return bool|Mage_Core_Model_Abstract
     */
    protected function getEntity($entityName)
    {
        $entity = false;
        switch ($entityName) {
            case 'Order':
                $entity = $this->getOrder();
                break;
            case 'Shipping':
            case 'Billing':
                $method = sprintf('get%sAddress', $entityName);
                $entity = $this->getOrder()->$method();
                break;
            case 'Payment':
                $entity = $this->getOrder()->getPayment();
                break;
            case 'Customer':
                $entity = $this->getOrderCustomer();
        }

        return $entity;
    }

    /**
     * @return bool|Mage_Customer_Model_Customer
     */
    protected function getOrderCustomer()
    {
        if (is_null($this->_orderCustomer)) {
            $this->_orderCustomer = false;
            if ($this->getOrder()->getCustomerId()) {
                $customer = Mage::getModel('customer/customer')->load($this->getOrder()->getCustomerId());
                if ($customer->getId()) {
                    $this->_orderCustomer = $customer;
                }
            }
        }

        return $this->_orderCustomer;
    }

    /**
     * @return $this
     */
    protected function updateStatus()
    {
        $order = $this->getOrder();
        if (!isset($this->getObject()->Status) || !$this->getObject()->Status) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING: Order status is not avaialble');
            return $this;
        }

        $matchedStatuses = Mage::getModel('tnw_salesforce/order_status')
            ->getCollection()
            ->addFieldToFilter('sf_order_status', $this->getObject()->Status);
        if (count($matchedStatuses) === 1) {
            foreach ($matchedStatuses as $_status) {
                if ($order->getStatus() != $_status->getStatus()) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SUCCESS: Order status updated to ' . $_status->getStatus());
                    $oldStatusLabel = $order->getStatusLabel();
                    $order->setStatus($_status->getStatus());
                    $order->addStatusHistoryComment(
                        sprintf("Update from salesforce: status is updated from %s to %s",
                            $oldStatusLabel, $order->getStatusLabel()),
                        $order->getStatus()
                    );
                    $this->addEntityToSave('Order', $order);
                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SUCCESS: Order status is in sync');
                }
                break;
            }
        } elseif (count($matchedStatuses) > 1) {
            $log = sprintf('SKIPPING: Order #%s status update.', $order->getIncrementId());
            $log .= ' Mapped Salesforce status matches multiple Magento Order statuses';
            $log .= ' - not sure which one should be selected';
            $order->addStatusHistoryComment($log);
            $this->addEntityToSave('Order', $order);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING: ' . $log);
        } else {
            $message = sprintf('SKIPPING: Order #%s status update.', $order->getIncrementId())
                . ' Mapped Salesforce status does not match any Magento Order status';
            $order->addStatusHistoryComment(
                $message
            );
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING: ' . $message);
            $this->addEntityToSave('Order', $order);
        }

        return $this;
    }

    /**
     * @param string $key
     * @param Mage_Core_Model_Abstract $entity
     */
    protected function addEntityToSave($key, $entity)
    {
        $this->_entitiesToSave[$key] = $entity;
    }

    /**
     * @return $this
     * @throws Exception
     */
    protected function saveEntities()
    {
        if (!empty($this->_entitiesToSave)) {
            $transaction = Mage::getSingleton('core/resource_transaction');
            foreach ($this->_entitiesToSave as $key => $entityToSave) {
                $transaction->addObject($entityToSave);
            }
            $transaction->save();
        }

        return $this;
    }
}