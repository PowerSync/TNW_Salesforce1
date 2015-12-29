<?php

$installer = $this;

$installer->startSetup();

$_magentoIdAttribute = 'Order:' . Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';

$_defaultMappingStatus = array(
    'Order:BillingStreet'       => false,
    'Order:BillingCity'         => false,
    'Order:BillingState'        => false,
    'Order:BillingPostalCode'   => false,
    'Order:BillingCountry'      => false,

    'Order:ShippingStreet'      => false,
    'Order:ShippingCity'        => false,
    'Order:ShippingState'       => false,
    'Order:ShippingPostalCode'  => false,
    'Order:ShippingCountry'     => false,

    'Order:PoNumber'            => false,

    'Order:Description'         => false,
    'Order:Type'                => false,
);

$sqlToUpdate = '';
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
        if (
            $_objectName == "Order"
        ) {
            $_localField = 'Order';

            if (
                $_fieldName == 'BillingStreet'
                || $_fieldName == 'BillingCity'
                || $_fieldName == 'BillingState'
                || $_fieldName == 'BillingPostalCode'
                || $_fieldName == 'BillingCountry'
            ) {
                $_localField = 'Billing';
            }

            if (
                $_fieldName == 'ShippingStreet'
                || $_fieldName == 'ShippingCity'
                || $_fieldName == 'ShippingState'
                || $_fieldName == 'ShippingPostalCode'
                || $_fieldName == 'ShippingCountry'
            ) {
                $_localField = 'Shipping';
            }

            if (
                $_fieldName == 'BillingStreet'
                || $_fieldName == 'ShippingStreet'
            ) {
                $_attributeCode = 'street';
            }
            if (
                $_fieldName == 'BillingCity'
                || $_fieldName == 'ShippingCity'
            ) {
                $_attributeCode = 'city';
            }
            if (
                $_fieldName == 'BillingPostalCode'
                || $_fieldName == 'ShippingPostalCode'
            ) {
                $_attributeCode = 'postcode';
            }
            if (
                $_fieldName == 'BillingState'
                || $_fieldName == 'ShippingState'
            ) {
                $_attributeCode = 'region';
            }
            if (
                $_fieldName == 'BillingCountry'
                || $_fieldName == 'ShippingCountry'
            ) {
                $_attributeCode = 'country_id';
            }

            switch ($_fieldName) {
                case 'PoNumber':
                    $_localField = 'Payment';
                    $_attributeCode = 'po_number';
                    $_backendType = "NULL";
                    $_attributeId = "NULL";
                    $_isCustom = true;
                    break;
                case 'Description':
                    $_attributeCode = 'cart_all';
                    $_backendType = "NULL";
                    $_attributeId = "NULL";
                    $_isCustom = true;
                    break;
                case 'Type':
                    $_localField = 'Custom';
                    $_attributeCode = 'order_type';
                    $_backendType = "NULL";
                    $_attributeId = "NULL";
                    $_isCustom = true;
                    $_customValue = "Magento";
                    break;
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
                $_backendType = "'" . $attr->getBackendType() . "'";
            }
        }
        $_localField .= ' : ' . $_attributeCode;
        $_customValue = ($_customValue) ? "'" . $_customValue . "'" : "NULL"; //Wrap into single quotes
        // Add
        $sql .= "INSERT INTO `{$tableName}`(mapping_id, local_field, sf_field, attribute_id, backend_type, sf_object, default_value) ".
            "VALUES (NULL, '{$_localField}', '{$_fieldName}', {$_attributeId}, {$_backendType}, '{$_objectName}', {$_customValue});";
    }
}
//Execute
$db = Mage::getSingleton('core/resource')->getConnection('core_write');
$db->query($sql);

$installer->endSetup(); 