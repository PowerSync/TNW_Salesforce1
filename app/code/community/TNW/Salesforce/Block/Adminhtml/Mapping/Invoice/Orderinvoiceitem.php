<?php

class TNW_Salesforce_Block_Adminhtml_Mapping_Invoice_Orderinvoiceitem extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_mapping_invoice_orderinvoiceitem';
        $this->_headerText = $this->__('Order invoice item mapping');
        parent::__construct();
        $this->_updateButton('add', 'label', $this->__('Add New Mapping'));
    }
}
