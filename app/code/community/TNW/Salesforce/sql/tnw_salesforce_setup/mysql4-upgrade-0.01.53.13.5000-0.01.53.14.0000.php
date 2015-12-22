<?php

/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;

$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
$setup->updateAttribute('catalog_product', 'salesforce_campaign_id', 'source_model', 'tnw_salesforce/config_source_product_campaign');

$installer->getConnection()->addColumn(
    $installer->getTable('tnw_salesforce/mapping'),
    'active',
    array(
        'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
        'length' => 2,
        'default' => 1,
        'nullable' => false,
        'comment' => 'Is this record active'

    )
);

$installer->endSetup(); 