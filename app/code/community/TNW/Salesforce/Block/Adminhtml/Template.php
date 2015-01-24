<?php

class TNW_Salesforce_Block_Adminhtml_Template extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_template';
        $this->_headerText = Mage::helper('tnw_salesforce')->__('Integration Check');
        parent::__construct();
        $this->_buttons = array();
    }
}
