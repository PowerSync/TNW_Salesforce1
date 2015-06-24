<?php

class TNW_Salesforce_Adminhtml_Salesforce_ConfigController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Reload accounts from salesforce and redirect to referrer
     */
    public function reloadAccountsAction()
    {
        //reload accounts
        Mage::helper('tnw_salesforce/config')->getSalesforceAccounts(true);

        $this->_getSession()->addSuccess($this->__('Accounts refreshed'));
        $this->_redirectReferer();
    }
}