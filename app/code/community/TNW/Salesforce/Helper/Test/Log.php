<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Test_Log extends TNW_Salesforce_Helper_Test_Abstract
{
    /**
     * @var string
     */
    protected $_title = 'Magento error logging enabled';

    /**
     * @var string
     */
    protected $_message = 'Magento logging is disabled, click here to enable it.';

    /**
     * @var
     */
    protected $_redirect;

    /**
     * @return bool|mixed
     */
    protected function _performTest()
    {
        $this->_redirect = Mage::helper("adminhtml")->getUrl("adminhtml/system_config/edit/", array("section" => "dev"));

        $isLogEnabled = Mage::getStoreConfig('dev/log/active');
        $isSfLogEnabled = Mage::getStoreConfig('salesforce/development_and_debugging/log_enable');
        if (!$isLogEnabled && $isSfLogEnabled) {
            return false;
        }

        return true;
    }
}