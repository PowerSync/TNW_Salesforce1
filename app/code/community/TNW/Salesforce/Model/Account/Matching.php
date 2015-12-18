<?php

/**
 * Model
 * @method string getEmailDomain()
 * @method string getAccountId()
 * @method string getAccountName()
 */
class TNW_Salesforce_Model_Account_Matching extends Mage_Core_Model_Abstract
{
    /**
     * @internal
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/account_matching');
    }
}