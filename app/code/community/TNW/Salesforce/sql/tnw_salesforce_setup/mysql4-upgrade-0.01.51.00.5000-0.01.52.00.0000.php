<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */


/**
 * @var Mage_Sales_Model_Resource_Setup $this
 */
$installer = $this;
$installer->startSetup();

/**
 * @comment Enable cache by default
 */
$installer->getConnection()->insertOnDuplicate(
    $installer->getTable('core/cache_option'),
    array('code' => 'tnw_salesforce', 'value' => 1)
);

$installer->getConnection()->update(
    $installer->getTable('tnw_salesforce/order_status'),
    array('sf_order_status' => 'Draft'),
    'status != \'complete\''
);

$installer->getConnection()->update(
    $installer->getTable('tnw_salesforce/mapping'),
    array('default_value' => 'salesforce_customer/lead_config/lead_source'),
    array(
        'local_field = ?' => 'Custom : lead_source',
        'sf_field = ?' => 'LeadSource',
        'sf_object = ?' => 'Lead'
    )
);

$installer->endSetup();