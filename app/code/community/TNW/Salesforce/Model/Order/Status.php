<?php

class TNW_Salesforce_Model_Order_Status extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/order_status');
    }
}