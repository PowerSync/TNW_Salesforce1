<?php

/**
 * Model
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

    /**
     * @return Mage_Core_Model_Abstract
     */
    protected function _afterLoad()
    {
        $accountId = Mage::helper('tnw_salesforce/data')
            ->prepareId($this->getData('account_id'));
        $this->setData('account_id', $accountId);

        return parent::_afterLoad();
    }

    /**
     * @return bool
     */
    public function validate()
    {
        $this->getData();
        //todo
        return true;
    }
}