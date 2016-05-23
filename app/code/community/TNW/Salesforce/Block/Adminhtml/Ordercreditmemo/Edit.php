<?php

class TNW_Salesforce_Block_Adminhtml_Ordercreditmemo_Edit extends TNW_Salesforce_Block_Adminhtml_Base_Edit
{
    /**
     * name of  Salesforce object in case-sensitive case
     * @var string
     */
    protected $_sfEntity = 'Order';

    /**
     * @var string
     */
    protected $_controller = 'adminhtml_ordercreditmemo';
}
