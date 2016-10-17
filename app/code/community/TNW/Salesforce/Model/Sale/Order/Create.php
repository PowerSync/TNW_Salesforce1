<?php

class TNW_Salesforce_Model_Sale_Order_Create extends Mage_Adminhtml_Model_Sales_Order_Create
{
    public function __construct()
    {
        $this->_session = Mage::getSingleton('tnw_salesforce/sale_order_create_session_quote');
    }
}