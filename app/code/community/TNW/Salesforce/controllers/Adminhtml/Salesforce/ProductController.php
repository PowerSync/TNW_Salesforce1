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
    protected $_blockPath = 'mapping_product_product';

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('tnw_salesforce/mappings/product_mapping');
    }
}