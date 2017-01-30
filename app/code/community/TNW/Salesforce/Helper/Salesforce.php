<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce extends TNW_Salesforce_Helper_Abstract
{
    public function isConnected()
    {
        return TNW_Salesforce_Model_Connection::createConnection()->initConnection();
    }

    public function isLoggedIn()
    {
        return TNW_Salesforce_Model_Connection::createConnection()->isLoggedIn();
    }

    public function tryToLogin()
    {
        TNW_Salesforce_Model_Connection::createConnection()->tryToLogin();
    }

    public function getLastErrorMessage()
    {
        TNW_Salesforce_Model_Connection::createConnection()->getLastErrorMessage();
    }
}