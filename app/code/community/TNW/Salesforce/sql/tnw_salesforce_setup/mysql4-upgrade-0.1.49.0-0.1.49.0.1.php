<?php
$installer = $this;

$installer->startSetup();
$installer->getConnection()->addColumn($installer->getTable('core/website'), 'pricebook_id', 'varchar(50)');
$installer->endSetup();