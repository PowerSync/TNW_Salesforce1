<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

$installer = $this;

$installer->startSetup();

$_magentoIdAttribute = 'Opportunity:' . Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';

$_defaultMappingStatus = array(
    'Lead:Company'      => false,
    'Lead:Phone'        => false,
    'Lead:Street'       => false,
    'Lead:City'         => false,
    'Lead:State'        => false,
    'Lead:PostalCode'   => false,
    'Lead:Country'      => false,
    'Lead:LeadSource'   => false,

    'Contact:MailingStreet'     => false,
    'Contact:MailingCity'       => false,
    'Contact:MailingState'      => false,
    'Contact:MailingPostalCode' => false,
    'Contact:MailingCountry'    => false,
    'Contact:OtherStreet'       => false,
    'Contact:OtherCity'         => false,
    'Contact:OtherState'        => false,
    'Contact:OtherPostalCode'   => false,
    'Contact:OtherCountry'      => false,
    'Contact:OtherPhone'        => false,
    'Contact:Phone'             => false,
    'Contact:Birthdate'         => false,

    'Opportunity:Description'   => false,
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
            $_objectName == "Lead" ||
            $_objectName == "Account" ||
            $_objectName == "Contact"
        ) {
            switch ($_fieldName) {
                case 'ProductCode':
                    $_attributeCode = 'sku';
                    break;
                default:
                    $_attributeCode = strtolower($_fieldName);
                    break;
            }
            if ($_objectName == 'Lead' || $_objectName == 'Contact') {
                $_localField = 'Customer';

                if (
                    $_fieldName == 'Company'
                    || $_fieldName == 'Phone'
                    || $_fieldName == 'Street'
                    || $_fieldName == 'City'
                    || $_fieldName == 'State'
                    || $_fieldName == 'PostalCode'
                    || $_fieldName == 'Country'
                    || $_fieldName == 'OtherStreet'
                    || $_fieldName == 'OtherCity'
                    || $_fieldName == 'OtherState'
                    || $_fieldName == 'OtherPostalCode'
                    || $_fieldName == 'OtherCountry'
                    || $_fieldName == 'OtherPhone'
                ) {
                    $_localField = 'Billing';
                }
                if (
                    $_fieldName == 'MailingStreet'
                    || $_fieldName == 'MailingCity'
                    || $_fieldName == 'MailingState'
                    || $_fieldName == 'MailingPostalCode'
                    || $_fieldName == 'MailingCountry'
                    || ($_objectName == 'Contact' && $_fieldName == 'Phone')
                ) {
                    $_localField = 'Shipping';
                }
                if (
                    $_fieldName == 'Phone'
                    || $_fieldName == 'OtherPhone'
                ) {
                    $_attributeCode = 'telephone';
                }
                if (
                    $_fieldName == 'MailingStreet'
                    || $_fieldName == 'OtherStreet'
                ) {
                    $_attributeCode = 'street';
                }
                if (
                    $_fieldName == 'MailingCity'
                    || $_fieldName == 'OtherCity'
                ) {
                    $_attributeCode = 'city';
                }
                if (
                    $_fieldName == 'PostalCode'
                    || $_fieldName == 'MailingPostalCode'
                    || $_fieldName == 'OtherPostalCode'
                ) {
                    $_attributeCode = 'postcode';
                }
                if (
                    $_fieldName == 'State'
                    || $_fieldName == 'MailingState'
                    || $_fieldName == 'OtherState'
                ) {
                    $_attributeCode = 'region';
                }
                if (
                    $_fieldName == 'Country'
                    || $_fieldName == 'MailingCountry'
                    || $_fieldName == 'OtherCountry'
                ) {
                    $_attributeCode = 'country_id';
                }
                if ($_fieldName == 'Birthdate') {
                    $_attributeCode = 'dob';
                }
                if ($_objectName == 'Lead' && $_fieldName == 'LeadSource') {
                    $_localField = 'Custom';
                    $_attributeCode = 'lead_source';
                    $_backendType = "NULL";
                    $_attributeId = "NULL";
                    $_isCustom = true;
                    $_customValue = "Web";
                }
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
        } else {
            $_localField = 'Order';
            $_backendType = "NULL";
            $_attributeId = "NULL";
            switch ($_fieldName) {
                case 'Description':
                    $_attributeCode = 'cart_all';
                    break;
            }
        }
        $_localField .= ' : ' . $_attributeCode;
        $_customValue = ($_customValue) ? "'" . $_customValue . "'" : "NULL"; //Wrap into single quotes
        // Add
        $sql .= "INSERT INTO `" . $tableName . "` VALUES (NULL, '" . $_localField . "', '" . $_fieldName . "', " . $_attributeId . ", " . $_backendType . ", '" . $_objectName . "', " . $_customValue . ");";
    }
}
//Execute
$db = Mage::getSingleton('core/resource')->getConnection('core_write');
$db->query($sql);

$installer->endSetup(); 