<?php

/**
 * Drop down list
 *
 * Class TNW_Salesforce_Model_Config_Account_Conjoint
 */
class TNW_Salesforce_Model_Config_Account_Conjoint
{
    /**
     * @var array
     */
    protected $_accountIdSelected = array();

    /**
     * Drop down list method
     *
     * @return array
     */
    public function toOptionArray() {
        $result = Mage::helper('tnw_salesforce/config')->getSalesforceAccounts();
        $this->_accountIdSelected['null'] = 'None';
        foreach ($result as $_id => $_account) {
            $this->_accountIdSelected[$_id] = $_account->Name;
        }

        return $this->_accountIdSelected;
    }
}
