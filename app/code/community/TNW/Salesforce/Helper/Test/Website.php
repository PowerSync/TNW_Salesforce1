<?php

/**
 * Class TNW_Salesforce_Helper_Test_Website
 */
class TNW_Salesforce_Helper_Test_Website extends TNW_Salesforce_Helper_Test_Abstract
{
    /**
     * @var string
     */
    protected $_title = 'Presence of Magento store name label';

    /**
     * @var string
     */
    protected $_message = 'We strongly suggest using a store name, click here to fix.';

    /**
     * @var
     */
    protected $_redirect;

    /**
     * @return bool|mixed
     */
    protected function _performTest()
    {
        $this->_redirect = Mage::helper("adminhtml")->getUrl("adminhtml/system_config/edit/", array("section" => "general"));

        $website = Mage::getStoreConfig('general/store_information/name');
        if ($website == "" || !$website || empty($website)) {
            return false;
        }
        return true;
    }
}
