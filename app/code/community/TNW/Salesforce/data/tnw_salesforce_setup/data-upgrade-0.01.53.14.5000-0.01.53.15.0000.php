<?php
/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;

$installer->startSetup();

$_defaultMappingStatus = array(
    'OrderInvoice' => array(
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Billing_Street__c'      => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:street'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Billing_City__c'        => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:city'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Billing_State__c'       => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:region'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Billing_PostalCode__c'  => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:postcode'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Billing_Country__c'     => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:country_id'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Billing_Phone__c'     => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:telephone'),

        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Shipping_Street__c'     => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:street'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Shipping_City__c'       => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:city'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Shipping_State__c'      => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:region'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Shipping_PostalCode__c' => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:postcode'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Shipping_Country__c'    => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:country_id'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Shipping_Phone__c'    => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:telephone'),
    ),
    'OrderShipment' => array(
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Billing_Street__c'      => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:street'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Billing_City__c'        => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:city'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Billing_State__c'       => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:region'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Billing_PostalCode__c'  => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:postcode'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Billing_Country__c'     => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:country_id'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Billing_Phone__c'     => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:telephone'),

        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Shipping_Street__c'     => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:street'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Shipping_City__c'       => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:city'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Shipping_State__c'      => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:region'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Shipping_PostalCode__c' => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:postcode'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Shipping_Country__c'    => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:country_id'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .'Shipping_Phone__c'    => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:telephone'),
    ),
);

$picklistMapping = array();
/** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $groupCollection */
$groupCollection = Mage::getResourceModel('tnw_salesforce/mapping_collection');
$tableName       = $groupCollection->getMainTable();

foreach ($_defaultMappingStatus as $_objectName => $_field) {
    $groupCollection->resetData();
    $groupCollection->addObjectToFilter($_objectName);

    $allValues = $groupCollection->getAllValues();
    foreach ($_field as $_fieldName => $_param) {
        if (isset($allValues[$_fieldName])) {
            continue;
        }

        list($_type, $_attributeCode) = explode(':', $_param['attribute']);
        $attrId = Mage::getResourceModel('eav/entity_attribute')
            ->getIdByCode($_type, $_attributeCode);

        /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attr */
        $attr = Mage::getModel('catalog/resource_eav_attribute')->load($attrId);
        $_attributeId = $attr->getId();
        $_backendType = $attr->getBackendType();

        $picklistMapping[] = array(
            'local_field' => sprintf('%s : %s', $_param['localField'], $_attributeCode),
            'sf_field' => $_fieldName,
            'attribute_id' => $_attributeId,
            'backend_type' => $_backendType,
            'sf_object' => $_objectName,
            'active' => 0
        );
    }
}

//Execute
$installer->getConnection()->insertMultiple($tableName, $picklistMapping);
$installer->endSetup();
