<?php

/**
 * Class TNW_Salesforce_Block_Adminhtml_Check
 */
class TNW_Salesforce_Block_Adminhtml_Check extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_check';
        $this->_headerText = Mage::helper('tnw_salesforce')->__('Integration Check');
        parent::__construct();
        $this->_buttons = array();
    }

    /**
     * @return bool
     */
    public function isSalesforceSection()
    {
        return strtolower(trim($this->getParam('section'))) == 'salesforce';
    }

    /**
     * @param $param
     * @param null $default
     * @return mixed
     */
    public function getParam($param, $default = null)
    {
        return Mage::app()->getRequest()->getParam($param, $default);
    }

    /**
     * @return mixed
     */
    public function isIntegrationEnabled()
    {
        return Mage::helper('tnw_salesforce')->isEnabled();
    }
}
