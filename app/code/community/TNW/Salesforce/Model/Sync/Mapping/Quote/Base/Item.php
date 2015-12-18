<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 10.03.15
 * Time: 23:32
 */
class TNW_Salesforce_Model_Sync_Mapping_Quote_Base_Item extends TNW_Salesforce_Model_Sync_Mapping_Order_Base_Item
{

    /**
     * @comment list of the allowed mapping types
     * @var array
     */
    protected $_allowedMappingTypes = array(
		'Cart',
		'Item',
        'Product Inventory',
        'Product',
        'Custom'
    );


    /**
     * @return TNW_Salesforce_Model_Mysql4_Mapping_Collection|null
     */
    public function getMappingCollection()
    {
        if (empty($this->_mappingCollection)) {
            $this->_mappingCollection = Mage::getModel('tnw_salesforce/mapping')->getCollection()->addObjectToFilter('Abandoneditem');
        }

        return $this->_mappingCollection;
    }

}
