<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

$installer = $this;

$installer->startSetup();

$installer->getConnection()->addColumn($installer->getTable('sales/order'), 'salesforce_id', 'varchar(50)');
$installer->getConnection()->addColumn($installer->getTable('sales/quote'), 'salesforce_id', 'varchar(50)');

$installer->endSetup();