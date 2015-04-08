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
        $leadModel =  Mage::getSingleton('tnw_salesforce/cached')->load('tnw_salesforce_api_entity/lead', $lead['leadId']);
        $accountLookup = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup(array($leadModel->getData('Email')));
        if (isset($accountLookup[0][$leadModel->getData('Email')])) {
            $account = $accountLookup[0][$leadModel->getData('Email')];
            $lead['accountId'] = $account['ID'];
        }
        return $this->_getClient()->convertLead($lead);
    }
}