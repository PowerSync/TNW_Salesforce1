<?php

$installer = $this;

$installer->startSetup();

$installer->run("

  DROP TABLE IF EXISTS {$this->getTable('tnw_salesforce_group')};

");

$installer->endSetup(); 