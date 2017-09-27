<?php

class TNW_Salesforce_Adminhtml_Salesforce_Order_InvoiceitemController extends TNW_Salesforce_Controller_Base_Mapping
{
    /**
     * name of  Salesforce object in case sensitive
     * @var string
     */
    protected $_sfEntity = TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_ITEM_OBJECT;

    /**
     * name of Local object in case sensitive
     * @var string
     */
    protected $_localEntity = 'OrderInvoiceItem';

    /**
     * path to the blocks which will be rendered by
     * @var string
     */
    protected $_blockPath = 'mapping_invoice_orderinvoiceitem';

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('tnw_salesforce/mappings/invoice_mapping/order_invoice_item');
    }
}
