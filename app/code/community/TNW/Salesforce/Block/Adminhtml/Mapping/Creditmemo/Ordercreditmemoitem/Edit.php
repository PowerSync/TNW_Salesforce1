<?php

class TNW_Salesforce_Block_Adminhtml_Mapping_Creditmemo_Ordercreditmemoitem_Edit
    extends TNW_Salesforce_Block_Adminhtml_Base_Edit
{
    /**
     * name of  Salesforce object in case-sensitive case
     * @var string
     */
    protected $_sfEntity = 'OrderItem';

    /**
     * @var string
     */
    protected $_controller = 'adminhtml_mapping_creditmemo_ordercreditmemoitem';
}
