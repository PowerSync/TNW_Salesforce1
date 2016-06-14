<?php

class TNW_Salesforce_Block_Adminhtml_Opportunity_Invoice extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_opportunity_invoice';
        $this->_headerText = $this->__('Opportunity invoice mapping');
        parent::__construct();
        $this->_updateButton('add', 'label', $this->__('Add New Mapping'));
    }
}
