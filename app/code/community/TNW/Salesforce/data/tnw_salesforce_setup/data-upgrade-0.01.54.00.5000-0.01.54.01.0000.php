<?php
/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;

$installer->startSetup();

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

foreach ($_defaultMappingStatus as $_objectName => $_field) {
    /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $groupCollection */
    $groupCollection = Mage::getResourceModel('tnw_salesforce/mapping_collection')
        ->addObjectToFilter($_objectName);

    $_hasSfField = array();
    foreach ($groupCollection->walk('getSfField') as $_key => $_sfField) {
        $_hasSfField[$_sfField][] = $_key;
    }

    $_hasLocalField = array();
    foreach ($groupCollection->walk('getLocalField') as $_key => $_localField) {
        $_hasLocalField[$_localField][] = $_key;
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

        $_connection->update($groupCollection->getMainTable(), array(
            'magento_sf_enable' => $magentoSfEnable,
            'magento_sf_type' => $magentoSfType,
            'sf_magento_enable' => $sfMagentoEnable,
            'sf_magento_type' => $sfMagentoType,
        ), array('mapping_id IN(?)' => $iDs));
    }
}

$installer->endSetup();
