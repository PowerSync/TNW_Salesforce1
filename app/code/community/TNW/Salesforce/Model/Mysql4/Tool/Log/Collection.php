<?php
/**
 * Author: Tech-N-Web, LLC (dba PowerSync) 
 * Email: support@powersync.biz 
 * Developer: Evgeniy Ermolaev
 * Date: 29.10.15
 * Time: 14:42
 */ 
class TNW_Salesforce_Model_Mysql4_Tool_Log_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{

    protected function _construct()
    {
        $this->_init('tnw_salesforce/tool_log');
    }

}