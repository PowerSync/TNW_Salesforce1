<?php

class TNW_Salesforce_Model_Mysql4_Mapping_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/mapping');
    }

    public function addObjectToFilter($so)
    {
        $this->getSelect()
            ->where('main_table.sf_object = ?', $so);
        return $this;
    }

    public function addLocalFieldToFilter($f)
    {
        $this->getSelect()
            ->where('main_table.local_field = ?', $f);
        return $this;
    }
}
