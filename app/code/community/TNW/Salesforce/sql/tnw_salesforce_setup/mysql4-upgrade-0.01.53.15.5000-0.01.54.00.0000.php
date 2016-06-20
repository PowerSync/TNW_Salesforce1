<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$_defaultMappingStatus = array(
    'OrderInvoice' => array(
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE .'Billing_Street__c'      => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:street'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE .'Billing_City__c'        => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:city'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE .'Billing_State__c'       => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:region'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE .'Billing_PostalCode__c'  => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:postcode'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE .'Billing_Country__c'     => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:country_id'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE .'Billing_Phone__c'     => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:telephone'),

        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE .'Shipping_Street__c'     => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:street'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE .'Shipping_City__c'       => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:city'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE .'Shipping_State__c'      => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:region'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE .'Shipping_PostalCode__c' => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:postcode'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE .'Shipping_Country__c'    => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:country_id'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE .'Shipping_Phone__c'    => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:telephone'),
    ),
    'OrderShipment' => array(
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT .'Billing_Street__c'      => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:street'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT .'Billing_City__c'        => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:city'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT .'Billing_State__c'       => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:region'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT .'Billing_PostalCode__c'  => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:postcode'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT .'Billing_Country__c'     => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:country_id'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT .'Billing_Phone__c'     => array(
            'localField'=>'Billing', 'attribute'=>'customer_address:telephone'),

        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT .'Shipping_Street__c'     => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:street'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT .'Shipping_City__c'       => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:city'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT .'Shipping_State__c'      => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:region'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT .'Shipping_PostalCode__c' => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:postcode'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT .'Shipping_Country__c'    => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:country_id'),
        TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_SHIPMENT .'Shipping_Phone__c'    => array(
            'localField'=>'Shipping', 'attribute'=>'customer_address:telephone'),
    ),
);

$tableName       = $installer->getTable('tnw_salesforce/mapping');
$select          = $installer->getConnection()->select()->from($tableName, array('sf_field', 'sf_object'));
$mappingAssoc    = $installer->getConnection()->fetchAssoc($select);
$mappingAssoc    = $mappingAssoc ? $mappingAssoc : array();

$selectAttribute = $installer->getConnection()->select()
    ->from(array('a' => $this->getTable('eav/attribute')), array('a.attribute_id', 'a.backend_type'))
    ->join(
        array('t' => $this->getTable('eav/entity_type')),
        'a.entity_type_id = t.entity_type_id',
        array())
    ->where('t.entity_type_code = :entity_type_code')
    ->where('a.attribute_code = :attribute_code');

$picklistMapping = array();
foreach ($_defaultMappingStatus as $_objectName => $_field) {
    foreach ($_field as $_fieldName => $_param) {
        foreach ($mappingAssoc as $_mapping) {
            if ($_mapping['sf_field'] == $_fieldName && $_mapping['sf_object'] == $_objectName) {
                continue 2;
            }
        }

        list($_type, $_attributeCode) = explode(':', $_param['attribute']);
        $row = $installer->getConnection()->fetchRow($selectAttribute, array(
            ':entity_type_code' => $_type,
            ':attribute_code'   => $_attributeCode
        ));

        $_attributeId = $_backendType = null;
        if (!empty($row)) {
            $_attributeId = $row['attribute_id'];
            $_backendType = $row['backend_type'];
        }

        $picklistMapping[] = array(
            'local_field' => sprintf('%s : %s', $_param['localField'], $_attributeCode),
            'sf_field' => $_fieldName,
            'sf_object' => $_objectName,
            'attribute_id' => $_attributeId,
            'backend_type' => $_backendType,
        );
    }
}

//Execute
$installer->getConnection()->insertMultiple($tableName, $picklistMapping);

$installer->endSetup();