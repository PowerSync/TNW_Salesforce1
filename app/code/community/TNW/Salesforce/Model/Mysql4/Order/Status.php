<?php

class TNW_Salesforce_Model_Mysql4_Order_Status extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {
        $this->_init('tnw_salesforce/order_status', 'status_id');
    }
}