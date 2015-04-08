<?php

class TNW_Salesforce_Model_Api_Client
{
    protected $_client;

    protected function _getClient()
    {
        if (is_null($this->_client)) {
            $this->_client = Mage::getSingleton('tnw_salesforce/connection')->getClient();
        }

        return $this->_client;
    }

    /**
     * @param array $data
     *
     * @return Varien_Object
     */
    public function convertLead($data)
    {
        $response = $this->_getClient()->convertLead(array_values($data));

        return new Varien_Object((array)$response);
    }

    /**
     * @param string $sql
     *
     * @return Varien_Object
     */
    public function query($sql)
    {
        $response = $this->_getClient()->query((string)$sql);

        return new Varien_Object((array)$response);
    }
}