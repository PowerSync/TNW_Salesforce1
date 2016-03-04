<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

$installer = $this;

$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$setup->updateAttribute('catalog_product', 'salesforce_pricebook_id', 'is_global', Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE);

$installer->endSetup();