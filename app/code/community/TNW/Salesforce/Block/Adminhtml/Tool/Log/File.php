<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Block_Adminhtml_Tool_Log_File
 */
class TNW_Salesforce_Block_Adminhtml_Tool_Log_File extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_tool_log_file';
        $this->_headerText = $this->__('Synchronization log files');
        parent::__construct();

        $this->removeButton('add');
    }

}
