<?php

$installer = $this;

$installer->startSetup();

$_magentoIdAttribute = 'Opportunity:' . Mage::helper('tnw_salesforce/salesforce')->getSfPrefix() . 'Magento_ID__c';

$_defaultMappingStatus = array(
    'Contact:Email' => false,
    'Contact:FirstName' => false,
    'Contact:LastName' => false,

    'Account:Name' => false,

    'Lead:Email' => false,
    'Lead:FirstName' => false,
    'Lead:LastName' => false,

    'Product2:Name' => false,
    'Product2:ProductCode' => false,

    'Opportunity:CloseDate' => false,
    $_magentoIdAttribute => false
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
foreach ($_defaultMappingStatus as $_key => $_value) {
    if ($_defaultMappingStatus[$_key]) {
        continue;
    } else {
        $_tmp = explode(':', $_key);
        $_objectName = $_tmp[0];
        $_fieldName = $_tmp[1];
        $_localField = NULL;
        $_attributeId = NULL;
        $_attributeCode = NULL;
        $_backendType = NULL;
        if (
            $_objectName == "Lead" ||
            $_objectName == "Account" ||
            $_objectName == "Contact" ||
            $_objectName == "Product2"
        ) {
            switch ($_fieldName) {
                case 'ProductCode':
                    $_attributeCode = 'sku';
                    break;
                default:
                    $_attributeCode = strtolower($_fieldName);
                    break;
            }
            if ($_fieldName == 'Name' && $_objectName == 'Account') {
                $_attributeCode = 'company';
                $_localField = 'Billing';
            }
            if ($_objectName == 'Product2') {
                $_localField = 'Product';
            }
            if ($_objectName == 'Lead' || $_objectName == 'Contact') {
                $_localField = 'Customer';
            }

            if ($_localField == 'Billing' || $_localField == 'Shipping') {
                $_type = 'customer_address';
            } else if ($_localField == "Product") {
                $_type = 'catalog_product';
            } else {
                $_type = 'customer';
            }

            $attrId = Mage::getResourceModel('eav/entity_attribute')
                ->getIdByCode($_type, $_attributeCode);
            $attr = Mage::getModel('catalog/resource_eav_attribute')->load($attrId);
            $_attributeId = $attr->getId();
            $_backendType = "'" . $attr->getBackendType() . "'";
        } else {
            $_localField = 'Order';
            $_backendType = "NULL";
            $_attributeId = "NULL";
            switch ($_fieldName) {
                case 'CloseDate':
                    $_attributeCode = 'created_at';
                    break;
                case Mage::helper('tnw_salesforce/salesforce')->getSfPrefix() . 'Magento_ID__c':
                    $_attributeCode = 'number';
                    break;
            }
        }
        $_localField .= ' : ' . $_attributeCode;
        // Add
        $sql = "INSERT INTO `" . $tableName . "` VALUES (NULL, '" . $_localField . "', '" . $_fieldName . "', " . $_attributeId . ", " . $_backendType . ", '" . $_objectName . "', NULL);";
        //Execute
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $db->query($sql);
    }
}

$installer->endSetup(); 