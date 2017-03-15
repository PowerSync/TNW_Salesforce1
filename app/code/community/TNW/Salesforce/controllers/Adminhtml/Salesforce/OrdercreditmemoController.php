<?php

class TNW_Salesforce_Adminhtml_Salesforce_OrdercreditmemoController extends TNW_Salesforce_Controller_Base_Mapping
{
    /**
     * name of  Salesforce object in case sensitive
     * @var string
     */
    protected $_sfEntity    = 'Order';

    /**
     * name of Local object in case sensitive
     * @var string
     */
    protected $_localEntity = 'OrderCreditMemo';

    /**
     * path to the blocks which will be rendered by
     * @var string
     */
    protected $_blockPath   = 'mapping_creditmemo_ordercreditmemo';
}
