<?php

class TNW_Salesforce_Model_Sync_Mapping_Invoice_Base_Item extends TNW_Salesforce_Model_Sync_Mapping_Order_Base_Item
{
    /**
     * @comment list of the allowed mapping types
     * @var array
     */
    protected $_allowedMappingTypes = array(
        'Billing Item',
        'Product Inventory',
        'Product',
        'Custom'
    );
}