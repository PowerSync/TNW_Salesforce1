<?php

class TNW_Salesforce_Block_Adminhtml_Ordercreditmemoitem_Grid extends TNW_Salesforce_Block_Adminhtml_Base_Grid
{
    /**
     * name of  Salesforce object in case-sensitive case
     * @var string
     */
    protected $_sfEntity    = 'OrderItem';

    /**
     * name of Local object in case sensitive
     * @var string
     */
    protected $_localEntity = 'OrderCreditMemoItem';
}
