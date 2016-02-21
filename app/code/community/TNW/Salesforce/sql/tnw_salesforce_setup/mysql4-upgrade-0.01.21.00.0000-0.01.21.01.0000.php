<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */
$installer = $this;

$installer->startSetup();
$installer->getConnection()->addColumn($installer->getTable('sales/creditmemo_comment'), 'salesforce_id', 'varchar(50)');
$installer->getConnection()->addColumn($installer->getTable('sales/invoice_comment'), 'salesforce_id', 'varchar(50)');
$installer->getConnection()->addColumn($installer->getTable('sales/shipment_comment'), 'salesforce_id', 'varchar(50)');
$installer->endSetup();