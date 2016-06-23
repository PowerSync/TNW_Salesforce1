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

$installer->getConnection()->update(
    $installer->getTable('tnw_salesforce/mapping'),
    array('default_value' => 'Web'),
    array(
        'local_field = ?' => 'Custom : lead_source',
        'sf_field = ?' => 'LeadSource',
        'sf_object = ?' => 'Lead'
    )
);

$installer->endSetup();