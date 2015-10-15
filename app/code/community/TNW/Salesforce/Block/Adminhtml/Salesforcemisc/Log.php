<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class LogTNW_Salesforce_Block_Adminhtml_Salesforcemisc_Log
 */
class TNW_Salesforce_Block_Adminhtml_Salesforcemisc_Log extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_salesforcemisc_log';
        $this->_headerText = $this->__('Synchronization logs');
        parent::__construct();

        $this->removeButton('add');
    }

}
