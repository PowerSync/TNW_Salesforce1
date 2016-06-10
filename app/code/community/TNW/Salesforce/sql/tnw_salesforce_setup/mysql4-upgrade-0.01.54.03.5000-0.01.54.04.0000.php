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
        'sf_object'         => 'CampaignSalesRule',
        'default_value'     => 'salesforce_order/salesforce_campaigns/default_status',
    ),
    array(
        'local_field'       => 'Custom : Type',
        'sf_field'          => 'Type',
        'sf_object'         => 'CampaignSalesRule',
        'default_value'     => 'salesforce_order/salesforce_campaigns/default_type',
    ),
    array(
        'local_field'       => 'Custom : Owner',
        'sf_field'          => 'OwnerId',
        'sf_object'         => 'CampaignSalesRule',
        'default_value'     => 'salesforce_order/salesforce_campaigns/default_owner',
    ),
    array(
        'local_field'       => 'Shopping Cart Rule : from_date',
        'sf_field'          => 'StartDate',
        'sf_object'         => 'CampaignSalesRule',
        'backend_type'      => 'datetime',
        'is_system'         => '0',
    ),
    array(
        'local_field'       => 'Shopping Cart Rule : to_date',
        'sf_field'          => 'EndDate',
        'sf_object'         => 'CampaignSalesRule',
        'backend_type'      => 'datetime',
        'is_system'         => '0',
    ),
    array(
        'local_field'       => 'Shopping Cart Rule : name',
        'sf_field'          => 'Name',
        'sf_object'         => 'CampaignSalesRule',
    ),
    array(
        'local_field'       => 'Shopping Cart Rule : description',
        'sf_field'          => 'Description',
        'sf_object'         => 'CampaignSalesRule',
        'is_system'         => '0',
    ),
    array(
        'local_field'       => 'Shopping Cart Rule : is_active',
        'sf_field'          => 'IsActive',
        'sf_object'         => 'CampaignSalesRule',
    ),
    array(
        'local_field'       => 'Shopping Cart Rule : number',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'CampaignSalesRule',
    ),

    //
    array(
        'local_field'       => 'Custom : Status',
        'sf_field'          => 'Status',
        'sf_object'         => 'CampaignMember',
        'default_value'     => 'Responded',
    ),
);

$selectAttribute = $installer->getConnection()->select()
    ->from(array('a' => $this->getTable('eav/attribute')), array('a.attribute_id', 'a.backend_type'))
    ->join(
        array('t' => $this->getTable('eav/entity_type')),
        'a.entity_type_id = t.entity_type_id',
        array())
    ->where('t.entity_type_code = :entity_type_code')
    ->where('a.attribute_code = :attribute_code');

$uoiData = array();
foreach ($data as $value) {
    $_attributeId = $_backendType = null;
    if (array_key_exists('@attribute', $value)) {
        list($_type, $_attributeCode) = explode(':', $value['@attribute'], 2);

        $row = $installer->getConnection()->fetchRow($selectAttribute, array(
            ':entity_type_code' => $_type,
            ':attribute_code'   => $_attributeCode
        ));

        if (!empty($row)) {
            $_attributeId = $row['attribute_id'];
            $_backendType = $row['backend_type'];
        }

        unset($value['@attribute']);
    }

    $uoiData[] = array_merge(array(
        'attribute_id'      => $_attributeId,
        'backend_type'      => $_backendType,
        'default_value'     => null,
        'is_system'         => '1',
        'magento_sf_enable' => '1',
        'magento_sf_type'   => 'upsert',
        'sf_magento_enable' => '0',
        'sf_magento_type'   => 'upsert'
    ), $value);
}

$installer->getConnection()->insertOnDuplicate($mappingTable, $uoiData);
$installer->endSetup();