<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 * Date: 29.10.15
 * Time: 17:59
 */
class TNW_Salesforce_Block_Adminhtml_Tool_Log extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_tool_log';
        $this->_headerText      = $this->__('Transaction Logs');

        parent::__construct();

        $this->removeButton('add');

    }

}

