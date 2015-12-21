<?php

/**
 * Resource model
 */
class TNW_Salesforce_Model_Mysql4_Account_Matching extends Mage_Core_Model_Mysql4_Abstract
{
    /**
     * @internal
     */
    public function _construct()
    {
        $this->_init('tnw_salesforce/account_matching', 'matching_id');
    }
}