<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

$installer = $this;

$installer->startSetup();

$installer->getConnection()->addColumn($installer->getTable('tnw_salesforce_order_status'), 'sf_order_status', 'varchar(100)');

$installer->endSetup();

