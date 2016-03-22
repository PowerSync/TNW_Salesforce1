<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

$installer = $this;

$installer->startSetup();
$installer->getConnection()->addColumn($this->getTable('sales_flat_order_item'), 'salesforce_id', 'varchar(50)');
$installer->getConnection()->addColumn($this->getTable('sales_flat_quote_item'), 'salesforce_id', 'varchar(50)');

$installer->endSetup();