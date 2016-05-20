<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;

$installer->startSetup();
$installer->getConnection()->addColumn($installer->getTable('sales/creditmemo'), 'salesforce_id', 'varchar(50)');
$installer->getConnection()->addColumn($installer->getTable('sales/creditmemo'), 'sf_insync', 'boolean default FALSE');
$installer->endSetup();