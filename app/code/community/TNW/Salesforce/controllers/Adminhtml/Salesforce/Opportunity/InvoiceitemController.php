<?php

class TNW_Salesforce_Adminhtml_Salesforce_Opportunity_InvoiceitemController extends TNW_Salesforce_Controller_Base_Mapping
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
    protected $_localEntity = 'OpportunityInvoiceItem';

    /**
     * path to the blocks which will be rendered by
     * @var string
     */
    protected $_blockPath = 'mapping_invoice_opportunityinvoiceitem';
}
