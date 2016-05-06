<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$mappingTable = $installer->getTable('tnw_salesforce/mapping');
$installer->getConnection()->addColumn($mappingTable, 'magento_sf_enable', array(
    'type'      => Varien_Db_Ddl_Table::TYPE_SMALLINT,
    'length'    => 2,
    'default'   => 1,
    'nullable'  => false,
    'comment'   => 'Magento > SF: Sync Enable'
));

$installer->getConnection()->addColumn($mappingTable, 'magento_sf_type', array(
    'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length'    => 255,
    'default'   => 'upsert',
    'comment'   => 'Magento > SF: Set Type'
));

$installer->getConnection()->addColumn($mappingTable, 'sf_magento_enable', array(
    'type'      => Varien_Db_Ddl_Table::TYPE_SMALLINT,
    'length'    => 2,
    'default'   => 1,
    'nullable'  => false,
    'comment'   => 'SF > Magento: Sync Enable'
));

$installer->getConnection()->addColumn($mappingTable, 'sf_magento_type', array(
    'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length'    => 255,
    'default'   => 'upsert',
    'comment'   => 'SF > Magento: Set Type'
));

$updateQuery = $installer->getConnection()->updateFromSelect(
    $installer->getConnection()->select()
        ->from(false, array('magento_sf_enable' => new Zend_Db_Expr('m.active'), 'sf_magento_enable' => new Zend_Db_Expr('m.active'))),
    array('m' => $mappingTable)
);

$installer->getConnection()->query($updateQuery);
$installer->getConnection()->dropColumn($mappingTable, 'active');

$installer->endSetup();