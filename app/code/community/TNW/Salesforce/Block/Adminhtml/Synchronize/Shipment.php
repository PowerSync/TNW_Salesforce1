<?php
class TNW_Salesforce_Block_Adminhtml_Synchronize_Shipment extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_synchronize_shipment';
        $this->_headerText = $this->__('Shipment Synchronization');
        parent::__construct();
        $this->removeButton('add');
    }
}