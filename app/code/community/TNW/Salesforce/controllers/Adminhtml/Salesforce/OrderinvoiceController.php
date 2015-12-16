<?php

class TNW_Salesforce_Adminhtml_Salesforce_OrderinvoiceController extends TNW_Salesforce_Controller_Base_Mapping
{
    /**
     * name of  Salesforce object in case sensitive
     * @var string
     */
    protected $_sfEntity = TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT;

    /**
     * path to the blocks which will be rendered by
     * @var string
     */
    protected $_blockPath = 'orderinvoice';
}
