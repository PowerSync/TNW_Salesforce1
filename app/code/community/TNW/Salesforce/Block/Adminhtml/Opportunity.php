<?php

class TNW_Salesforce_Block_Adminhtml_Opportunity extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_opportunity';
        $this->_headerText = Mage::helper('tnw_salesforce')->__('Opportunity Mapping');
        parent::__construct();
        $this->_updateButton('add', 'label', Mage::helper('tnw_salesforce')->__('Add New Mapping'));
    }
}
