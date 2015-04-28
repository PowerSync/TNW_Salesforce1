<?php

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
     * @var string
     */
    protected $_objectType = 'Order';

    /**
     * @param $string
     */
    protected function log($string)
    {
        Mage::helper('tnw_salesforce')->log($string);
    }

    /**
     * @param stdClass $object
     *
     * @return $this
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
                $this->log('Updating salesforce order ' . $this->getObject()->Id);
            }

            $magentoId = Mage::helper('tnw_salesforce/config')->getMagentoIdField();
            if (!isset($this->getObject()->$magentoId) || !$this->getObject()->$magentoId) {
                $this->log('There is no magento id');
                $this->_order = false;
                return false;
            }

            $this->_order = Mage::getModel('sales/order')->loadByIncrementId($this->getObject()->$magentoId);
            if (!$this->_order->getId()) {
                Mage::helper('tnw_salesforce')->log(
                    sprintf('Order with  Id not found, skipping update', $this->getObject()->$magentoId));
                $this->_order = false;
                return false;
            }
        }

        return $this->_order;
    }

    public function process()
    {
        if ($this->getOrder()) {
            $this->updateStatus();
            $this->updateMappedFields();
        }
    }

    protected function updateMappedFields()
    {
        $mappings = Mage::getModel('tnw_salesforce/mapping')->getCollection()->addObjectToFilter($this->_objectType);
        $entitiesToSave = array();
        foreach ($mappings as $mapping) {
            //skip if cannot find field in object
            if (!isset($this->getObject()->{$mapping->getSfField()})) {
                continue;
            }
            $newValue = $this->getObject()->{$mapping->getSfField()};
            /** @var $mapping TNW_Salesforce_Model_Mapping */
            list($entityName, $field) = explode(' : ', $mapping->getLocalField());
            $entity = $this->getEntity($entityName);
            if ($entity->getData($field) != $newValue) {
                $entity->setData($field, $newValue);

                if (!isset($entitiesToSave[$entityName])) {
                    $entitiesToSave[$entityName] = $entity;
                }
            }
        }
        if (!empty($entitiesToSave)) {
            $transaction = Mage::getSingleton('core/resource_transaction');
            foreach ($entitiesToSave as $entityToSave) {
                $transaction->addObject($entityToSave);
            }
            $transaction->save();
        }
    }

    /**
     * @param string $entityName
     *
     * @return bool|Varien_Object
     */
    protected function getEntity($entityName)
    {
        $entity = false;
        switch ($entityName) {
            case 'Shipping':
            case 'Billing':
                $method = sprintf('get%sAddress', $entityName);
                $entity = $this->getOrder()->$method();
                break;
        }

        return $entity;
    }

    protected function updateStatus()
    {
        $order = $this->getOrder();
        if (!isset($this->getObject()->Status) || !$this->getObject()->Status) {
            return;
        }

        $matchedStatuses = Mage::getModel('tnw_salesforce/order_status')
            ->getCollection()
            ->addFieldToFilter('sf_order_status', $this->getObject()->Status);
        if (count($matchedStatuses) === 1) {
            foreach ($matchedStatuses as $_status) {
                $order->setStatus($_status->getStatus());
                break;
            }
            $order->save();
        } elseif (count($matchedStatuses) > 1) {
            $log = sprintf('SKIPPING: Order #%s status update.', $order->getIncrementId());
            $log .= ' Mapped Salesforce status matches multiple Magento Order statuses';
            $log .= ' - not sure which one should be selected';
            $this->log($log);
        } else {
            $this->log(sprintf('SKIPPING: Order #%s status update.', $order->getIncrementId())
                . ' Mapped Salesforce status does not match any Magento Order status');
        }
    }
}