<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 10.03.15
 * Time: 22:18
 */
abstract class TNW_Salesforce_Model_Sync_Mapping_Quote_Base extends TNW_Salesforce_Model_Sync_Mapping_Order_Base
{


    /**
     * @comment list of the allowed mapping types
     * @var array
     */
    protected $_allowedMappingTypes = array(
        'Customer',
        'Billing',
        'Shipping',
        'Custom',
        'Cart',
        'Customer Group',
        'Payment',
    );


    /**
     * @var string
     */
    protected $_cachePrefix = 'quote';

    /**
     * @var string
     */
    protected $_cacheIdField = 'id';

    /**
     * @return TNW_Salesforce_Model_Mysql4_Mapping_Collection|null
     */
    public function getMappingCollection()
    {
        if (empty($this->_mappingCollection)) {
            $this->_mappingCollection = Mage::getModel('tnw_salesforce/mapping')->getCollection()->addObjectToFilter('Abandoned');
        }

        return $this->_mappingCollection;
    }
}