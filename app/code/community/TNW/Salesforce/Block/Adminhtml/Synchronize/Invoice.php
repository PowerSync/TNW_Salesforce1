<?php
class TNW_Salesforce_Block_Adminhtml_Synchronize_Invoice extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_synchronize_invoice';
        $this->_headerText = $this->__('Invoice Synchronization');
        parent::__construct();
        $this->removeButton('add');
    }
}