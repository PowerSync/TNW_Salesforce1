<?php

/**
 * Class TNW_Salesforce_Block_Adminhtml_Queuesync
 */
class TNW_Salesforce_Block_Adminhtml_Queuesync extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_queuesync';
        $this->_headerText = Mage::helper('tnw_salesforce')->__('Synchronization Queue');
        parent::__construct();
        $this->removeButton('add');
        $this->_addButton('flush_magento', array(
            'label'     => Mage::helper('tnw_salesforce')->__('Process Queue'),
            'onclick'   => 'setLocation(\'' . $this->getUrl("*/*/processall") .'\')',
        ));
    }
}