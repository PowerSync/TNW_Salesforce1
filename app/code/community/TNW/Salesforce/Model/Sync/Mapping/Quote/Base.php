<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
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
}