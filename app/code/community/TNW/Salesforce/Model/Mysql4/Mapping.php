<?php

class TNW_Salesforce_Model_Mysql4_Mapping extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {
        $this->_init('tnw_salesforce/mapping', 'mapping_id');
    }
}
