<?php
class TNW_Salesforce_Block_Adminhtml_Invoicesync extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_invoicesync';
        $this->_headerText = Mage::helper('tnw_accountingseed')->__('Invoice Synchronization');
        parent::__construct();
        $this->removeButton('add');
    }
}