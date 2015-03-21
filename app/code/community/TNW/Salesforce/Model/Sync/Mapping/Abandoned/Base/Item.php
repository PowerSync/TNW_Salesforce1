<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 10.03.15
 * Time: 23:32
 */
class TNW_Salesforce_Model_Sync_Mapping_Abandoned_Base_Item extends TNW_Salesforce_Model_Sync_Mapping_Order_Base_Item
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


}
