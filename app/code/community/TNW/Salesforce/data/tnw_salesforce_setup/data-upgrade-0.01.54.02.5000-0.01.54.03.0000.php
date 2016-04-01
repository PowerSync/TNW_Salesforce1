<?php
/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

$mappingTable = $installer->getTable('tnw_salesforce/mapping');

$data = array(
    // Campaign
    array(
        'local_field'       => 'Custom : Status',
        'sf_field'          => 'Status',
        'sf_object'         => 'CampaignCatalogRule',
        'default_value'     => 'salesforce_order/salesforce_campaigns/default_status',
    ),
    array(
        'local_field'       => 'Custom : Type',
        'sf_field'          => 'Type',
        'sf_object'         => 'CampaignCatalogRule',
        'default_value'     => 'salesforce_order/salesforce_campaigns/default_type',
    ),
    array(
        'local_field'       => 'Custom : Owner',
        'sf_field'          => 'OwnerId',
        'sf_object'         => 'CampaignCatalogRule',
        'default_value'     => 'salesforce_order/salesforce_campaigns/default_owner',
    ),
    array(
        'local_field'       => 'Catalog Rule : from_date',
        'sf_field'          => 'StartDate',
        'sf_object'         => 'CampaignCatalogRule',
        'backend_type'      => 'datetime',
        'is_system'         => '0',
    ),
    array(
        'local_field'       => 'Catalog Rule : to_date',
        'sf_field'          => 'EndDate',
        'sf_object'         => 'CampaignCatalogRule',
        'backend_type'      => 'datetime',
        'is_system'         => '0',
    ),
    array(
        'local_field'       => 'Catalog Rule : name',
        'sf_field'          => 'Name',
        'sf_object'         => 'CampaignCatalogRule',
    ),
    array(
        'local_field'       => 'Catalog Rule : description',
        'sf_field'          => 'Description',
        'sf_object'         => 'CampaignCatalogRule',
        'is_system'         => '0',
    ),
    array(
        'local_field'       => 'Catalog Rule : is_active',
        'sf_field'          => 'IsActive',
        'sf_object'         => 'CampaignCatalogRule',
    ),
    array(
        'local_field'       => 'Catalog Rule : number',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'CampaignCatalogRule',
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
