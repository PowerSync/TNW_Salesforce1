<?php

/**
 * Class TNW_Salesforce_Helper_Test_Connection
 */
class TNW_Salesforce_Helper_Test_Connection extends TNW_Salesforce_Helper_Test_Abstract
{
    /**
     * @var string
     */
    protected $_title = 'Test Salesforce connection';

    /**
     * @var string
     */
    protected $_message = 'Connection failed, please refer to log file to investigate.';

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
        $_model->getClient();
        $this->_message = Mage::getSingleton('tnw_salesforce/connection')->getLastErrorMessage();

        return $_model->isConnected();
    }
}