<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;

/**
 * @comment Enable cache by default
 */
$installer->getConnection()->insertOnDuplicate(
    $installer->getTable('core/cache_option'), array(
        'code' => 'tnw_salesforce',
        'value' => 1,
    )
);

$installer->endSetup();
