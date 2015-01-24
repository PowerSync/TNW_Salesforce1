<?php
$installer = $this;

$installer->startSetup();
$installer->getConnection()->addColumn($installer->getTable('core/website'), 'salesforce_id', 'varchar(50)');
$installer->endSetup();