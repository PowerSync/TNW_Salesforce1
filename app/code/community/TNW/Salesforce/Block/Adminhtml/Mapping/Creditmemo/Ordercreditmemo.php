<?php

class TNW_Salesforce_Block_Adminhtml_Mapping_Creditmemo_Ordercreditmemo extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_mapping_creditmemo_ordercreditmemo';
        $this->_headerText = $this->__('Order credit memo mapping');
        parent::__construct();
        $this->_updateButton('add', 'label', $this->__('Add New Mapping'));
    }
}
