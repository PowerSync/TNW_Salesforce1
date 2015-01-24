<?php

/**
 * Class TNW_Salesforce_Helper_Test_Version
 */
class TNW_Salesforce_Helper_Test_Version extends TNW_Salesforce_Helper_Test_Abstract
{
    /**
     * @var string
     */
    protected $_title = 'PHP version above 5.3';

    /**
     * @var string
     */
    protected $_message = 'This extension requires PHP 5.3+';

    /**
     * @var
     */
    protected $_redirect;

    /**
     * @return mixed
     */
    protected function _performTest()
    {
        return Mage::helper('tnw_salesforce')->checkPhpVersion();
    }
}
