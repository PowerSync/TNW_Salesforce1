<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$data = array(
    // Order Item
    array(
        'local_field' => 'Order Item : item_id',
        'sf_field' => 'tnw_mage_basic__Magento_ID__c',
        'sf_object' => 'OrderItem',
        'sf_magento_enable' => '0',
        'is_system' => 1
    ),

    // Opportunity Item
    array(
        'local_field' => 'Order Item : item_id',
        'sf_field' => 'tnw_mage_basic__Magento_ID__c',
        'sf_object' => 'OpportunityLineItem',
        'sf_magento_enable' => '0',
        'is_system' => 1
    )
);

$mappingTable = $installer->getTable('tnw_salesforce/mapping');
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
            ':attribute_code' => $_attributeCode
        ));

        if (!empty($row)) {
            $_attributeId = $row['attribute_id'];
            $_backendType = $row['backend_type'];
        }

        unset($value['@attribute']);
    }

    $uoiData[] = array_merge(array(
        'attribute_id' => $_attributeId,
        'backend_type' => $_backendType,
        'default_value' => null,
        'is_system' => '1',
        'magento_sf_enable' => '1',
        'magento_sf_type' => 'upsert',
        'sf_magento_enable' => '1',
        'sf_magento_type' => 'upsert'
    ), $value);
}

$installer->getConnection()->insertOnDuplicate($mappingTable, $uoiData);

$installer->endSetup();