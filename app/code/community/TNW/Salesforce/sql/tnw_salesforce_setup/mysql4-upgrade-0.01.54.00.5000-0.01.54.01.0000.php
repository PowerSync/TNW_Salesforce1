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

$_defaultMappingStatus = array(
    'Product2' => array(
        'ProductCode' => array(
            'localField'=>'Product', 'attribute'=>'catalog_product:sku',
            'magento_sf'=>array('1', 'upsert'), 'sf_magento'=>array('1', 'upsert')),
        'Name' => array(
            'localField'=>'Product', 'attribute'=>'catalog_product:name',
            'magento_sf'=>array('1', 'insert'), 'sf_magento'=>array('1', 'insert')),
        'Description' => array(
            'localField'=>'Product', 'attribute'=>'catalog_product:description',
            'magento_sf'=>array('1', 'upsert'), 'sf_magento'=>array('1', 'upsert')),
        'tnw_mage_basic__Attribute_Set__c' => array(
            'localField'=>'Product', 'attribute'=>'catalog_product:attribute_set_id',
            'magento_sf'=>array('1', 'upsert'), 'sf_magento'=>array('1', 'upsert')),
        'tnw_mage_basic__Product_Type__c' => array(
            'localField'=>'Product', 'attribute'=>'catalog_product:type_id',
            'magento_sf'=>array('1', 'upsert'), 'sf_magento'=>array('1', 'update')),
    ),
    'Lead' => array(
        'LeadSource' => array(
            'localField'=>'Custom', 'attribute'=>':lead_source',
            'magento_sf'=>array('1', 'upsert'), 'sf_magento'=>array('0', NULL),
            'default'=>'salesforce_customer/lead_config/lead_source'),
    ),
    'Account' => array(
        'Name' => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:company',
            'magento_sf'=>array('1', 'upsert'), 'sf_magento'=>array('0', NULL))
    ),
    'Order' => array(
        'Type' => array(
            'localField'=>'Custom', 'attribute'=>':order_type',
            'magento_sf'=>array('1', 'upsert'), 'sf_magento'=>array('0', NULL),
            'default'=>'Magento'),
    )
);

$tableName       = $installer->getTable('tnw_salesforce/mapping');
$selectMapping   = $installer->getConnection()->select()
    ->from($tableName, array('mapping_id', 'sf_field', 'local_field'))
    ->where('sf_object = :sf_object');

foreach ($_defaultMappingStatus as $_objectName => $_field) {
    $mappingAssoc    = $installer->getConnection()
        ->fetchAssoc($selectMapping, array(':sf_object' => $_objectName));

    if (empty($mappingAssoc)) {
        continue;
    }

    $_hasSfField = $_hasLocalField = array();
    foreach ($mappingAssoc as $mapping) {
        $_hasSfField[$mapping['sf_field']][]        = $mapping['mapping_id'];
        $_hasLocalField[$mapping['local_field']][]  = $mapping['mapping_id'];
    }

    foreach ($_field as $_fieldName => $_param) {
        if (!isset($_hasSfField[$_fieldName])) {
            continue;
        }

        list($_type, $_attributeCode) = explode(':', $_param['attribute']);
        $_localField = sprintf('%s : %s', $_param['localField'], $_attributeCode);
        if (!isset($_hasLocalField[$_localField])) {
            continue;
        }

        $iDs = array_intersect($_hasSfField[$_fieldName], $_hasLocalField[$_localField]);
        if (!count($iDs)) {
            continue;
        }

        list($magentoSfEnable, $magentoSfType) = $_param['magento_sf'];
        list($sfMagentoEnable, $sfMagentoType) = $_param['sf_magento'];

        $installer->getConnection()->update($tableName, array(
            'magento_sf_enable' => $magentoSfEnable,
            'magento_sf_type' => $magentoSfType,
            'sf_magento_enable' => $sfMagentoEnable,
            'sf_magento_type' => $sfMagentoType,
        ), array('mapping_id IN(?)' => $iDs));
    }
}

$installer->endSetup();