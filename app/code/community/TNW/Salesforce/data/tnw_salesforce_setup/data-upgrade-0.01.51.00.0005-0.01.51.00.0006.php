<?php
$installer = $this;

/**
 * @comment Enable cache by default
 */
$installer->getConnection()->insert(
    $installer->getTable('core/cache_option'), array(
        'code' => 'tnw_salesforce',
        'value' => 1,
    )
);

$installer->endSetup();
