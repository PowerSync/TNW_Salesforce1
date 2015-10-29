<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 * Date: 29.10.15
 * Time: 17:59
 */
class TNW_Salesforce_Block_Adminhtml_Salesforcemisc_Log extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_salesforcemisc_log';
        $this->_headerText      = $this->__('Sync Logs from DB');

        parent::__construct();

        $this->removeButton('add');
    }

}

