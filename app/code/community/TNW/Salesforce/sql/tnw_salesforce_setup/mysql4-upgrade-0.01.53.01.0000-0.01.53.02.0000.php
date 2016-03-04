<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

/**
 * @var $installer Mage_Sales_Model_Resource_Setup
 */
$installer = Mage::getResourceModel('sales/setup', 'core_write');


$installer->addAttribute('order', 'opportunity_id', array(
    'label' => 'Salesforce Opportunity ID',
    'type' => 'varchar',
    'input' => 'text',
    'visible' => false,
    'required' => false
));

$installer->endSetup();
