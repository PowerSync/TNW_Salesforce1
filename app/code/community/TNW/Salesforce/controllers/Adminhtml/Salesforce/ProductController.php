<?php

/**
 * Class TNW_Salesforce_Adminhtml_Salesforce_ProductController
 */
class TNW_Salesforce_Adminhtml_Salesforce_ProductController extends TNW_Salesforce_Controller_Base_Mapping
{
    /**
     * name of  Salesforce object in case sensitive
     * @var string
     */
    protected $_sfEntity = 'Product2';

    /**
     * path to the blocks which will be rendered by
     * @var string
     */
    protected $_blockPath = 'product';
}