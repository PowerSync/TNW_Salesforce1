<?php

/**
 * Class TNW_Salesforce_Helper_Test_Login
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
        $_model = Mage::getSingleton('tnw_salesforce/connection');

        if ($_model->isConnected()) {
            $canLogin = $_model->tryToLogin();
        } else {
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