<?php

$installer = $this;

$installer->startSetup();

$installer->run("

ALTER TABLE {$this->getTable('tnw_salesforce_order_status')} CHANGE
  `sf_lead_status_code` `sf_lead_status_code` VARCHAR( 100 ) NOT NULL

");

$installer->run("

ALTER TABLE {$this->getTable('tnw_salesforce_order_status')} CHANGE
  `sf_opportunity_status_code` `sf_opportunity_status_code` VARCHAR( 100 ) NOT NULL

");

$installer->endSetup();