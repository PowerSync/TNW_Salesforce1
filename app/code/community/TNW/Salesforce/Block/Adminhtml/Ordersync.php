<?php

class TNW_Salesforce_Block_Adminhtml_Ordersync extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_ordersync';
        $this->_headerText = Mage::helper('tnw_salesforce')->__('Order Synchronization');
        parent::__construct();
        $this->removeButton('add');
    }
}
