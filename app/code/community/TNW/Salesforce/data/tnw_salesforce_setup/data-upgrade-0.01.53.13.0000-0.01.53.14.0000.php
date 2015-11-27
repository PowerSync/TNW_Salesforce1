<?php
/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;

$installer->startSetup();

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

$picklistMapping = array();

$groupCollection = Mage::getModel('tnw_salesforce/mapping')->getCollection();
$tableName = Mage::getSingleton('core/resource')->getTableName('tnw_salesforce_mapping');
// Figure out what's already mapped
foreach ($_defaultMappingStatus as $_key => $_value) {
    foreach ($groupCollection as $_mapping) {
        $mappingId = $_mapping->getMappingId();
        $_tmp = explode(':', $_key);
        $_objectName = $_tmp[0];
        $_fieldName = $_tmp[1];
        if (
            $_mapping->getSfField() == $_fieldName &&
            $_mapping->getSfObject() == $_objectName
        ) {
            $_defaultMappingStatus[$_key] = true;
        }
    }
}
$sql = "";
foreach ($_defaultMappingStatus as $_key => $_value) {
    if ($_defaultMappingStatus[$_key]) {
        continue;
    } else {
        $_isCustom = false;
        $_tmp = explode(':', $_key);
        $_objectName = $_tmp[0];
        $_fieldName = $_tmp[1];
        $_localField = NULL;
        $_attributeId = NULL;
        $_attributeCode = NULL;
        $_backendType = NULL;
        $_customValue = NULL;

        $_attributeCode = strtolower($_fieldName);

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

        // Skip Custom
        if (!$_isCustom) {
            $attrId = Mage::getResourceModel('eav/entity_attribute')
                ->getIdByCode($_type, $_attributeCode);

            $attr = Mage::getModel('catalog/resource_eav_attribute')->load($attrId);
            $_attributeId = $attr->getId();
            $_backendType = $attr->getBackendType();
        }

        $_localField .= ' : ' . $_attributeCode;

        $picklistMapping[] = array(
            'local_field' => $_localField,
            'sf_field' => $_fieldName,
            'attribute_id' => $_attributeId,
            'backend_type' => $_backendType,
            'sf_object' => $_objectName,
            'active' => 0
        );
    }
}
//Execute

$installer->getConnection()->insertMultiple(
    $installer->getTable('tnw_salesforce/mapping'),
    $picklistMapping
);

$installer->endSetup();
