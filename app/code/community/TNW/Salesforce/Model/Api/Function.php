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

    /**
     * @param $lead
     *
     * @return Varien_Object
     */
    public function convertLead($lead)
    {
        return $this->_getClient()->convertLead($lead);
    }
}