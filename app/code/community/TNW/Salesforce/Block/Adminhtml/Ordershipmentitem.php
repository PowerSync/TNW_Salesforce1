<?php

class TNW_Salesforce_Block_Adminhtml_Ordershipmentitem extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_ordershipmentitem';
        $this->_headerText = $this->__('Order shipment item mapping');
        parent::__construct();
        $this->_updateButton('add', 'label', $this->__('Add New Mapping'));
    }
}