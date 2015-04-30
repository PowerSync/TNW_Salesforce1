<?php

class TNW_Salesforce_Model_Mysql4_Import_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/import');
    }

    public function getOnlyPending()
    {
        $this->getSelect()
            ->where('main_table.is_processing IS NULL');
        return $this;
    }
}
