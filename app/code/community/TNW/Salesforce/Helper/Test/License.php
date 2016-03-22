<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Test_License extends TNW_Salesforce_Helper_Test_Abstract
{
    /**
     * @var string
     */
    protected $_title = 'Powersync&#153; license validation';

    /**
     * @var string
     */
    protected $_message = 'Your license is unavailable or has expired';

    /**
     * @var
     */
    protected $_redirect;

    protected function _performTest()
    {
        $_model = Mage::getSingleton('tnw_salesforce/connection');
        return $_model->checkPackage();
    }
}