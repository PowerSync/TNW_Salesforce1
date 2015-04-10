<?php

class TNW_Salesforce_Model_Api_Function
{
    /**
     * @var TNW_Salesforce_Model_Api_Client
     */
    protected $_client;

    /**
     * @return TNW_Salesforce_Model_Api_Client
     */
    protected function _getClient()
    {
        if (is_null($this->_client)) {
            $this->_client = Mage::getSingleton('tnw_salesforce/api_client');
        }

        return $this->_client;
    }

    protected function _convertToObject($data)
    {
        $response = new stdClass();
        foreach ($data as $key => $value) {
            $response->$key = $value;
        }

        return $response;
    }

    protected function _arrayConvertToObject($array)
    {
        foreach ($array as &$value) {
            $value = $this->_convertToObject($value);
        }

        return $array;
    }

    /**
     * @param array|stdClass $data
     *
     * @return Varien_Object
     */
    public function convertLead($data)
    {
        if (!is_array($data) || !is_object(current($data))) {
            //if array of arrays
            if (is_array(current($data))) {
                $data = array_values($data);
                $data = $this->_arrayConvertToObject($data);
            //if array
            } elseif(is_array($data)) {
                $data = array($this->_convertToObject($data));
            } elseif (is_object($data)) {
                $data = array($data);
            }
        } else {
            $data = array_values($data);
        }

        return $this->_getClient()->convertLead($data);
    }
}