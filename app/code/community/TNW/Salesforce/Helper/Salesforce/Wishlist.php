<?php

class TNW_Salesforce_Helper_Salesforce_Wishlist extends TNW_Salesforce_Helper_Salesforce_Abstract_Base
{
    /**
     * @comment magento entity alias "convert from"
     * @var string
     */
    protected $_magentoEntityName = '';

    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = '';

    /**
     * @var string
     */
    protected $_mappingEntityName = '';

    /**
     * @var string
     */
    protected $_mappingEntityItemName = '';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityModel = '';
}