<?php

/**
 * Collection
 */
class TNW_Salesforce_Model_Mysql4_Account_Matching_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    /**
     * @internal
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/account_matching');
    }

    /**
     * @param string $valueField
     * @param string $labelField
     * @return array
     */
    public function toOptionHashCustom($valueField='email_domain', $labelField='account_id')
    {
        return $this->_toOptionHash($valueField, $labelField);
    }
}
