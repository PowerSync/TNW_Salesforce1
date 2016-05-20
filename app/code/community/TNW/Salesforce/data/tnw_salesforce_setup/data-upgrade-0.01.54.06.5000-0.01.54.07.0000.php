<?php
/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

$mappingTable = $installer->getTable('tnw_salesforce/mapping');

$data = array(
    // Credit Memo
    array(
        'local_field'       => 'Custom : Status',
        'sf_field'          => 'Status',
        'sf_object'         => 'OrderCreditMemo',
    ),

);

$data = array_map(function($value){
    $_attributeId = $_backendType = null;

    if (array_key_exists('@attribute', $value)) {
        list($_type, $_attributeCode) = explode(':', $value['@attribute'], 2);
        $attrId = Mage::getResourceModel('eav/entity_attribute')
            ->getIdByCode($_type, $_attributeCode);

        /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attr */
        $attr = Mage::getModel('catalog/resource_eav_attribute')->load($attrId);
        $_attributeId = $attr->getId();
        $_backendType = $attr->getBackendType();

        unset($value['@attribute']);
    }

    return array_merge(array(
        'attribute_id'      => $_attributeId,
        'backend_type'      => $_backendType,
        'default_value'     => null,
        'is_system'         => '1',
        'magento_sf_enable' => '1',
        'magento_sf_type'   => 'upsert',
        'sf_magento_enable' => '0',
        'sf_magento_type'   => 'upsert'
    ), $value);
}, $data);

$installer->getConnection()->insertOnDuplicate($mappingTable, $data);

$installer->endSetup();
