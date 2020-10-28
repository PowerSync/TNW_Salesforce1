<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */

$entityTypeCode = 'catalog_product';

$groupName = 'General';

$groups = Mage::getModel('eav/entity_attribute_group')->getCollection();
$groups->getSelect()
    ->joinLeft(
        'eav_attribute_set',
        'main_table.attribute_set_id = eav_attribute_set.entity_type_id',
        array('eav_attribute_set.entity_type_id')
    )->joinLeft(
        'eav_entity_type',
        'eav_attribute_set.entity_type_id = eav_entity_type.entity_type_id',
        array('entity_type_code')
    )->where("main_table.attribute_group_name = 'General' AND eav_entity_type.entity_type_code = 'catalog_product'");
$currentGroup = $groups->getFirstItem();

$subquery = new \Zend_Db_Expr('SELECT `attribute_group_id` FROM `eav_attribute_group`');

$attributes = Mage::getModel('eav/entity_attribute')->getCollection();
$attributes->getSelect()->distinct(true)
    ->joinLeft(
    'eav_entity_attribute',
    'main_table.attribute_id = eav_entity_attribute.attribute_id',
        array(
            'eav_entity_attribute.attribute_group_id',
            'eav_entity_attribute.attribute_set_id',
            'eav_entity_attribute.entity_attribute_id',
        )
    )->joinLeft(
        'eav_entity_type',
        'eav_entity_attribute.entity_type_id = eav_entity_type.entity_type_id',
        array('eav_entity_type.entity_type_code')
    )->where("eav_entity_attribute.attribute_group_id NOT IN ($subquery)");

$items = $attributes->addFieldToFilter('entity_type_code', ['eq' => $entityTypeCode])->getItems();

foreach ($items as $item) {
    $item->setData('attribute_group_id', $currentGroup->getAttributeGroupId());
    $item->save();
}
