<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce
 */
class TNW_Salesforce_Helper_Salesforce extends TNW_Salesforce_Helper_Abstract
{
    protected $_sfPackagePrefix = NULL;
    /**
     * @return null|string
     */
    public function getSfPrefix()
    {
        if (!$this->_sfPackagePrefix) {
            //if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
            $this->_sfPackagePrefix = "tnw_powersync__";
            //} else {
            //    $this->_sfPackagePrefix = "";
            //}
        }

        return $this->_sfPackagePrefix;
    }

    public function isConnected() {
        return Mage::getSingleton('tnw_salesforce/connection')->initConnection();
    }
    /**
     * @return mixed
     */
    public function getClient()
    {
        return Mage::getSingleton('tnw_salesforce/connection')->getClient();
    }

    public function isLoggedIn()
    {
        return Mage::getSingleton('tnw_salesforce/connection')->isLoggedIn();
    }

    public function tryToLogin() {
        Mage::getSingleton('tnw_salesforce/connection')->tryToLogin();
    }
    public function getLastErrorMessage() {
        Mage::getSingleton('tnw_salesforce/connection')->getLastErrorMessage();
    }
}