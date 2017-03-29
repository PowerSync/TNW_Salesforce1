<?php

class TNW_Salesforce_Block_Adminhtml_Mapping_Creditmemo_Ordercreditmemo_Edit
    extends TNW_Salesforce_Block_Adminhtml_Base_Edit
{
    /**
     * name of  Salesforce object in case-sensitive case
     * @var string
     */
    protected $_sfEntity = 'Order';

    /**
     * @var string
     */
    protected $_controller = 'adminhtml_mapping_creditmemo_ordercreditmemo';
}
