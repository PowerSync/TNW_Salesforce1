<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Test_Login extends TNW_Salesforce_Helper_Test_Abstract
{
    /**
     * @var string
     */
    protected $_title = 'Test Salesforce login';

    /**
     * @var string
     */
    protected $_message = 'Login failed, please refer to log file to investigate.';

    /**
     * @var
     */
    protected $_redirect;

    /**
     * @return mixed
     */
    protected function _performTest()
    {
        $_model = TNW_Salesforce_Model_Connection::createConnection();

        try {
            $canLogin = $_model->tryToLogin();
        } catch (Exception $e) {
            $canLogin = false;
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: Login attempt failed, connection is inactive!");
        }

        $this->_message = $_model->getLastErrorMessage();

        // set current sf state
        if (!$canLogin) {
            Mage::getSingleton('core/session')->setSfNotWorking(true);
        } else {
            Mage::getSingleton('core/session')->setSfNotWorking(false);
        }

        return $_model->isLoggedIn();
    }
}