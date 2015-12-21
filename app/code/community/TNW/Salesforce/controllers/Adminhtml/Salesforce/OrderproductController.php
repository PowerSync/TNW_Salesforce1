<?php

class TNW_Salesforce_Adminhtml_Salesforce_OrderproductController extends TNW_Salesforce_Controller_Base_Mapping
{
    /**
     * name of  Salesforce object in case sensitive
     * @var string
     */
    protected $_sfEntity = 'OrderItem';

    /**
     * path to the blocks which will be rendered by
     * @var string
     */
    protected $_blockPath = 'orderproduct';
}
