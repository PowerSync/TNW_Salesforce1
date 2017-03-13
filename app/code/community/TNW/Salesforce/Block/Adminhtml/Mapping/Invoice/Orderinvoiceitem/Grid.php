<?php

class TNW_Salesforce_Block_Adminhtml_Order_Invoiceitem_Grid extends TNW_Salesforce_Block_Adminhtml_Base_Grid
{
    /**
     * name of  Salesforce object in case-sensitive case
     * @var string
     */
    protected $_sfEntity    = TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_ITEM_OBJECT;

    /**
     * name of Local object in case sensitive
     * @var string
     */
    protected $_localEntity = 'OrderInvoiceItem';
}
