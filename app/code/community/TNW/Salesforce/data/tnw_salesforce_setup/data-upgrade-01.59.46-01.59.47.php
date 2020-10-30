<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */

/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;

$setup = new Mage_Catalog_Model_Resource_Setup('core_setup');

$entityTypeCode = 'catalog_product';

$groupName = 'General';

$groups = Mage::getModel('eav/entity_attribute_group')->getCollection();
$groups->getSelect()
    ->joinInner(
        'eav_attribute_set',
        'main_table.attribute_set_id = eav_attribute_set.entity_type_id',
        array('eav_attribute_set.entity_type_id')
    )->joinInner(
        'eav_entity_type',
        'eav_attribute_set.entity_type_id = eav_entity_type.entity_type_id',
        array('entity_type_code')
    )->where("main_table.attribute_group_name = 'General' AND eav_entity_type.entity_type_code = 'catalog_product'");
$currentGroup = $groups->getFirstItem();

$attributes = Mage::getModel('eav/entity_attribute')->getCollection();
$attributes->getSelect()
    ->joinInner(
        'eav_entity_attribute',
        'main_table.attribute_id = eav_entity_attribute.attribute_id',
        array(
            'eav_entity_attribute.attribute_group_id',
            'eav_entity_attribute.attribute_set_id',
            'eav_entity_attribute.entity_attribute_id',
        )
    )->joinInner(
        'eav_entity_type',
        'eav_entity_attribute.entity_type_id = eav_entity_type.entity_type_id',
        array('eav_entity_type.entity_type_code')
    )->joinLeft(
        'eav_attribute_group',
        'eav_entity_attribute.attribute_group_id = eav_attribute_group.attribute_group_id'
    )
    ->where("eav_attribute_group.attribute_group_id IS NULL ");

$itemIds = $attributes->addFieldToFilter('entity_type_code', ['eq' => $entityTypeCode])->getAllIds();

$installer->getConnection()->update('eav_entity_attribute', array(
    'attribute_group_id' => $currentGroup->getId()
), array('attribute_id' => $itemIds));


$installer->endSetup();