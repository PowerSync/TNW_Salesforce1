<?php

class TNW_Salesforce_Model_Mysql4_Imports extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {
        $this->_init('tnw_salesforce/imports', 'import_id');
    }

    public function setForceInsertMode($forceInsert = true)
    {
        $this->_isPkAutoIncrement = !$forceInsert;
        return $this;
    }
}
