<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Api_Entity_Lead extends TNW_Salesforce_Model_Api_Entity_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce_api_entity/lead');
    }

    /**
     * @return bool
     */
    public function isConverted()
    {
        return (bool)$this->getData('IsConverted');
    }

    public function convert()
    {
        $prepareData = array(
            'convertedStatus' => Mage::helper("tnw_salesforce")->getLeadConvertedStatus(),
            'leadId' => $this->getId(),
            'doNotCreateOpportunity' => true,
            'overwriteLeadSource' => false,
            'sendNotificationEmail' => Mage::helper('tnw_salesforce/config_customer')
                ->isLeadEmailNotification(),
        );

        $response = Mage::getSingleton('tnw_salesforce/api_function')->convertLead($prepareData);
    }
}