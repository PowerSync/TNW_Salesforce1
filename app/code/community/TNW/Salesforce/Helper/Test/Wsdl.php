<?php

/**
 * Class TNW_Salesforce_Helper_Test_Wsdl
 */
class TNW_Salesforce_Helper_Test_Wsdl extends TNW_Salesforce_Helper_Test_Abstract
{
    /**
     * @var string
     */
    protected $_title = 'Presence of Salesforce WSDL file';

    /**
     * @var string
     */
    protected $_message = 'WSDL file not found, please check your path.';

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
        $_model->tryWsdl();
        return $_model->isWsdlFound();
    }
}
