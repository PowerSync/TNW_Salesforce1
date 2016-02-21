<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Sync_Mapping_Customer_Lead extends TNW_Salesforce_Model_Sync_Mapping_Customer_Base
{

    protected $_type = 'Lead';

    /**
     * @comment Apply mapping rules
     * @param Mage_Customer_Model_Customer $_customer
     */
    protected function _processMapping($_customer = NULL)
    {

        parent::_processMapping($_customer);

        $company = Mage::helper('tnw_salesforce/salesforce_data_lead')->getCompanyByCustomer($_customer);
        if ($company) {
            $this->getObj()->Company = $company;
        }
        if (Mage::helper('tnw_salesforce')->isCustomerSingleRecordType() == 2 && property_exists($this->getObj(), 'Company')) {
            // B2C only
            unset($this->getObj()->Company);
        }
    }
}