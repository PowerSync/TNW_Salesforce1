<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
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
        $isConnect = $_model->tryToConnect();
        $this->_message = $_model->getLastErrorMessage();

        return $isConnect;
    }
}