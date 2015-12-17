<?php

class TNW_Salesforce_Block_Adminhtml_Ordershipment_Grid extends TNW_Salesforce_Block_Adminhtml_Base_Grid
{
    /**
     * name of  Salesforce object in case-sensitive case
     * @var string
     */
    protected $_sfEntity    = TNW_Salesforce_Model_Config_Objects::ORDER_SHIPMENT_OBJECT;

    /**
     * name of Local object in case sensitive
     * @var string
     */
    protected $_localEntity = 'Order Shipment';
}
