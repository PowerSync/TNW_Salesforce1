<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Block_Adminhtml_Tool_Console
 */
class TNW_Salesforce_Block_Adminhtml_Tool_Console extends Mage_Adminhtml_Block_Widget_Form_Container
{

    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_tool';
        $this->_mode = 'console';

        $this->_headerText = $this->__('SOQL Console');
        parent::__construct();

        $this->updateButton('save', 'label', Mage::helper('tnw_salesforce')->__('Execute'));

        $this->removeButton('reset');
    }

}
