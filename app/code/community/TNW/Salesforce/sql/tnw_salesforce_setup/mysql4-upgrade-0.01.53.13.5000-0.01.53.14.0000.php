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

$_defaultMappingStatus = array(
    'Lead:StateCode' => false,
    'Lead:CountryCode' => false,

    'Contact:MailingStateCode' => false,
    'Contact:MailingCountryCode' => false,

    'Contact:OtherStateCode' => false,
    'Contact:OtherCountryCode' => false,

    'Order:BillingStateCode' => false,
    'Order:BillingCountryCode' => false,

    'Order:ShippingStateCode' => false,
    'Order:ShippingCountryCode' => false,
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

// Figure out what's already mapped
foreach ($_defaultMappingStatus as $_key => $_value) {
    foreach ($mappingAssoc as $_mapping) {
        list($_objectName, $_fieldName) = explode(':', $_key, 2);
        if ($_mapping['sf_field'] == $_fieldName && $_mapping['sf_object'] == $_objectName) {
            $_defaultMappingStatus[$_key] = true;
        }
    }
}

$picklistMapping = array();
foreach ($_defaultMappingStatus as $_key => $_value) {
    if ($_value) {
        continue;
    }

    list($_objectName, $_fieldName) = explode(':', $_key, 2);
    $_localField = NULL;
    $_attributeId = NULL;
    $_attributeCode = strtolower($_fieldName);
    $_backendType = NULL;
    $_customValue = NULL;

    if ($_fieldName == 'StateCode'
        || $_fieldName == 'CountryCode'
        || $_fieldName == 'OtherStateCode'
        || $_fieldName == 'OtherCountryCode'
        || $_fieldName == 'BillingStateCode'
        || $_fieldName == 'BillingCountryCode'
    ) {
        $_localField = 'Billing';
    }

    if ($_fieldName == 'MailingStateCode'
        || $_fieldName == 'MailingCountryCode'
        || $_fieldName == 'ShippingStateCode'
        || $_fieldName == 'ShippingCountryCode'
    ) {
        $_localField = 'Shipping';
    }

    if (
        $_fieldName == 'StateCode'
        || $_fieldName == 'MailingStateCode'
        || $_fieldName == 'OtherStateCode'
        || $_fieldName == 'BillingStateCode'
        || $_fieldName == 'ShippingStateCode'
    ) {
        $_attributeCode = 'region_id';
    }

    if (
        $_fieldName == 'CountryCode'
        || $_fieldName == 'MailingCountryCode'
        || $_fieldName == 'OtherCountryCode'
        || $_fieldName == 'BillingCountryCode'
        || $_fieldName == 'ShippingCountryCode'
    ) {
        $_attributeCode = 'country_id';
    }

    if ($_localField == 'Billing' || $_localField == 'Shipping') {
        $_type = 'customer_address';
    } else {
        $_type = 'customer';
    }

    $row = $installer->getConnection()->fetchRow($selectAttribute, array(
        ':entity_type_code' => $_type,
        ':attribute_code'   => $_attributeCode
    ));

    if (!empty($row)) {
        $_attributeId = $row['attribute_id'];
        $_backendType = $row['backend_type'];
    }

    $_localField .= ' : ' . $_attributeCode;

    $picklistMapping[] = array(
        'local_field'       => $_localField,
        'sf_field'          => $_fieldName,
        'attribute_id'      => $_attributeId,
        'backend_type'      => $_backendType,
        'sf_object'         => $_objectName,
    );
}

//Execute
$installer->getConnection()->insertMultiple($tableName, $picklistMapping);
$installer->endSetup(); 