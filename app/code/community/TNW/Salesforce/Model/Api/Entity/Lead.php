<?php

class TNW_Salesforce_Model_Api_Entity_Lead extends TNW_Salesforce_Model_Api_Entity_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce_api_entity/lead');
    }

    public function convert()
    {
        $prepareData = array(
            'convertedStatus' => Mage::helper("tnw_salesforce")->getLeadConvertedStatus(),
            'leadId' => $this->getId(),
            'doNotCreateOpportunity' => 'true',
            'overwriteLeadSource' => 'false',
            'sendNotificationEmail' => 'false',
        );

        $response = Mage::getSingleton('tnw_salesforce/api_function')->convertLead($prepareData);
    }
}