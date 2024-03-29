<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

$installer = $this;

$installer->startSetup();

$installer->run("

DROP TABLE IF EXISTS {$this->getTable('tnw_salesforce_imports')};
CREATE TABLE {$this->getTable('tnw_salesforce_imports')} (
  `import_id` varchar(28) NOT NULL,
  `json` longtext NOT NULL default '',
  `is_processing` BOOLEAN NULL,
  PRIMARY KEY (`import_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    ");

$installer->endSetup(); 