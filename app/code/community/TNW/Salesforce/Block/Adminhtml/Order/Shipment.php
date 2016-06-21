<?php

class TNW_Salesforce_Block_Adminhtml_Order_Shipment extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_order_shipment';
        $this->_headerText = $this->__('Order shipment mapping');
        parent::__construct();
        $this->_updateButton('add', 'label', $this->__('Add New Mapping'));
    }
}
