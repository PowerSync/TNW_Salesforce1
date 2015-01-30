<?php

$installer = $this;

$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$setup->addAttribute('customer', 'sf_insync', array(
    'label' => 'In Sync',
    'type' => 'int',
    'input' => 'boolean',
    'visible' => false,
    'system' => true,
    'required' => false,
    'position' => 1,
    'default' => 0,
    'user_defined' => 0,
    'source' => 'eav/entity_attribute_source_boolean'
));

$setup->addAttribute('catalog_product', 'sf_insync', array(
    'label' => 'In Sync',
    'type' => 'int',
    'input' => 'boolean',
    'visible' => false,
    'is_visible' => false,
    'system' => true,
    'required' => false,
    'unique' => 0,
    'position' => 1,
    'group' => 'Salesforce',
    'default' => 0,
    'user_defined' => 0,
    'visible_on_front' => 0,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'source' => 'eav/entity_attribute_source_boolean'
));
// get default set id
$setId = $installer->getDefaultAttributeSetId('catalog_product');

// get group id by name "Additional Attributes"
$attributeSetCollection = Mage::getResourceModel('eav/entity_attribute_group_collection');
foreach ($attributeSetCollection->getData() as $attributeGroupIndex) {
    foreach ($attributeGroupIndex as $key => $value) {
        if ($key === "attribute_group_name" and $value === "Salesforce") {
            $groupId = $attributeGroupIndex["attribute_group_id"];
            break 2;
        }
    }
}

// move attributes to group 'Salesforce'
if (isset($setId) and isset($groupId)) {
    try {
        $installer->addAttributeToGroup('catalog_product', $setId, $groupId, 'salesforce_id', 1000);
    } catch (Exception $e) {
        //Do nothing, skip if it was moved already
    }
    try {
        $installer->addAttributeToGroup('catalog_product', $setId, $groupId, 'salesforce_pricebook_id', 1000);
    } catch (Exception $e) {
        //Do nothing, skip if it was moved already
    }
}

$installer->getConnection()->addColumn($installer->getTable('sales/order'), 'sf_insync', 'boolean default FALSE');

$installer->endSetup(); 