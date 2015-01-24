<?php

$installer = $this;

$installer->startSetup();

$installer->run("

ALTER TABLE {$this->getTable('tnw_salesforce_mapping')} ADD
  `attribute_id` SMALLINT(5) NULL AFTER `sf_field`

    ");

$installer->run("

ALTER TABLE {$this->getTable('tnw_salesforce_mapping')} ADD
  `backend_type` VARCHAR(255) NULL AFTER `attribute_id`

    ");

$installer->endSetup(); 