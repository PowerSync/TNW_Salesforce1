<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
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


}
