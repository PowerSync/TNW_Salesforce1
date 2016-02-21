<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

$installer = $this;

$installer->startSetup();

$installer->run("

DROP TABLE IF EXISTS {$this->getTable('tnw_salesforce_queue_storage')};
CREATE TABLE {$this->getTable('tnw_salesforce_queue_storage')} (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `object_id` int(11) NOT NULL,
  `mage_object_type` varchar(255) NOT NULL COMMENT 'Magento Model used',
  `sf_object_type` varchar(255) NOT NULL COMMENT 'Salesforce Object',
  `date_created` datetime NOT NULL,
  `status` enum('new','sync_running') NOT NULL DEFAULT 'new',
  `sync_attempt` int(11) NOT NULL DEFAULT '0' COMMENT 'sync attempt number',
  `date_sync` datetime DEFAULT NULL,
  `message` text COMMENT 'serialized error array ',
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_id_sf_object_type` (`object_id`,`sf_object_type`),
  KEY `mage_object_type` (`mage_object_type`),
  KEY `date_created` (`date_created`),
  KEY `status` (`status`),
  KEY `sync_attempt` (`sync_attempt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    ");

$installer->endSetup();