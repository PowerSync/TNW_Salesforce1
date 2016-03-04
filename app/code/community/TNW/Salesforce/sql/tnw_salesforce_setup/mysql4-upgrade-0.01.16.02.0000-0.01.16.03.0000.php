<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

$installer = $this;

$installer->startSetup();

$installer->run("

  DROP TABLE IF EXISTS {$this->getTable('tnw_salesforce_group')};

");

$installer->endSetup(); 