<?php

class TNW_Salesforce_Model_Import_Order
{
    /**
     * @var stdClass
     */
    protected $_object;

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

    public function process()
    {
        if (isset($this->getObject()->Id) && $this->getObject()->Id) {
            $this->log('Updating salesforce order ' . $this->getObject()->Id);
        }

        $magentoId = Mage::helper('tnw_salesforce/config')->getMagentoIdField();
        if (!isset($this->getObject()->$magentoId) || !$this->getObject()->$magentoId) {
            $this->log('There is no magento id');
            return;
        }

        $order = Mage::getModel('sales/order')->loadByIncrementId($this->getObject()->$magentoId);
        if (!$order->getId()) {
            Mage::helper('tnw_salesforce')->log(
                sprintf('Order with  Id not found, skipping update', $this->getObject()->$magentoId));
            return;
        }

        $this->updateAddress($order, 'billing');
        $this->updateAddress($order, 'shipping');
    }

    protected function updateAddress(Mage_Sales_Model_Order $order, $type)
    {
        $attribute = ucfirst($type) . 'Address';
        if (!isset($this->getObject()->$attribute) || !is_object($this->getObject()->$attribute)) {
            $this->log('Order doesn\'t have ' . $type . ' address');
            return;
        }

        $addressObject = new Varien_Object((array)$this->getObject()->$attribute);
        $method = sprintf('get%sAddress', ucfirst($type));

        $fields = array(
            // salesforce => magento
            'city' => 'city',
            'street' => 'street',
            'state' => 'region',
            'postalCode' => 'postcode',
        );

        /** @var Mage_Sales_Model_Order_Address $address */
        $address = $order->$method();
        foreach ($fields as $salesforceField => $magentoField) {
            if ($addressObject->getData($salesforceField)) {
                $address->setData($magentoField, $addressObject->getData($salesforceField));
            }
        }
        $address->save();
    }
}