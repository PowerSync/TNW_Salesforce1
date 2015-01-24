<?php

/**
 * Class TNW_Salesforce_Block_Adminhtml_Customersync
 */
class TNW_Salesforce_Block_Adminhtml_Customersync extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_customersync';
        $this->_headerText = Mage::helper('tnw_salesforce')->__('Customer Synchronization');
        parent::__construct();
        $this->removeButton('add');
    }
}
