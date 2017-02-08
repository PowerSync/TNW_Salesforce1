<?php

class TNW_Salesforce_Model_Mysql4_Entity_Cache_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    /**
     *
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/sforce_entity_cache');
    }
}