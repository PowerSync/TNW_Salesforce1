<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

/**
 * @var $this Mage_Core_Model_Resource_Setup
 */
$installer = $this;

$installer->startSetup();

$tableName = Mage::getSingleton('core/resource')->getTableName('tnw_salesforce/queue_storage');

$sql = 'ALTER TABLE `' . $tableName . '` CHANGE status status enum("new","sync_running", "sync_error", "success") NOT NULL DEFAULT "new"';

$db = Mage::getSingleton('core/resource')->getConnection('core_write');
$db->query($sql);

$installer->endSetup();