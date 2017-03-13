<?php
class TNW_Salesforce_Block_Adminhtml_Synchronize_Creditmemo extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_synchronize_creditmemo';
        $this->_headerText = $this->__('Credit Memo Synchronization');
        parent::__construct();
        $this->removeButton('add');
    }
}