<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce extends TNW_Salesforce_Helper_Abstract
{
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