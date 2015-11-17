<?php

class TNW_Salesforce_Block_Adminhtml_Tool_Log_File_View extends Mage_Adminhtml_Block_Widget_Form_Container
{
    protected $_blockGroup = 'tnw_salesforce';
    protected $_controller = 'adminhtml_tool_log_file';
    protected $_mode = 'view';


    public function __construct()
    {
        $this->_headerText = $this->__('View/Download Log File');

        parent::__construct();
        $this->removeButton('reset');
        $this->removeButton('save');
    }
}
