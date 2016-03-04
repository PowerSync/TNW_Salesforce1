<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

$installer = $this;

$installer->startSetup();
$installer->getConnection()->addColumn($this->getTable('sales_flat_invoice'), 'sf_insync', 'boolean default FALSE');
$installer->getConnection()->addColumn($this->getTable('sales_flat_shipment'), 'sf_insync', 'boolean default FALSE');
$installer->endSetup();