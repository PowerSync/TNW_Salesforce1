<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$setIds = $setup->getAllAttributeSetIds('catalog_product');
foreach ($setIds as $setId) {
    $groupId = $this->getAttributeGroup('catalog_product', $setId, 'Salesforce', 'attribute_group_id');
    if (empty($groupId)) {
        continue;
    }

    $setup->removeAttributeGroup('catalog_product', $setId, $groupId);
}

/**
 * @var $salesSetup Mage_Sales_Model_Resource_Setup
 */
$salesSetup = Mage::getResourceModel('sales/setup', 'core_write');
$salesSetup->addAttribute('order', 'owner_salesforce_id', array(
    'label' => 'Salesforce Owner',
    'type' => 'varchar',
    'input' => 'text',
    'visible' => false,
    'required' => false
));

$data = array(
    // Order
    array(
        'local_field'       => 'Order : owner_salesforce_id',
        'sf_field'          => 'OwnerId',
        'sf_object'         => 'Order',
    ),

    // OrderInvoice
    array(
        'local_field'       => 'Order : owner_salesforce_id',
        'sf_field'          => 'OwnerId',
        'sf_object'         => 'OrderInvoice',
        'sf_magento_enable' => '0',
    ),

    // OrderShipment
    array(
        'local_field'       => 'Order : owner_salesforce_id',
        'sf_field'          => 'OwnerId',
        'sf_object'         => 'OrderShipment',
        'sf_magento_enable' => '0',
    ),

    // OrderCreditMemo
    array(
        'local_field'       => 'Order : owner_salesforce_id',
        'sf_field'          => 'OwnerId',
        'sf_object'         => 'OrderCreditMemo',
        'sf_magento_enable' => '0',
    ),

    // Opportunity
    array(
        'local_field'       => 'Order : owner_salesforce_id',
        'sf_field'          => 'OwnerId',
        'sf_object'         => 'Opportunity',
    ),

    // OpportunityInvoice
    array(
        'local_field'       => 'Order : owner_salesforce_id',
        'sf_field'          => 'OwnerId',
        'sf_object'         => 'OpportunityInvoice',
        'sf_magento_enable' => '0',
    ),

    // OpportunityShipment
    array(
        'local_field'       => 'Order : owner_salesforce_id',
        'sf_field'          => 'OwnerId',
        'sf_object'         => 'OpportunityShipment',
        'sf_magento_enable' => '0',
    ),

    // Contact
    array(
        'local_field'       => 'Customer : salesforce_contact_owner_id',
        'sf_field'          => 'OwnerId',
        'sf_object'         => 'Contact',
        '@attribute'        => 'customer:salesforce_contact_owner_id',
    ),

    // Account
    array(
        'local_field'       => 'Customer : salesforce_account_owner_id',
        'sf_field'          => 'OwnerId',
        'sf_object'         => 'Account',
        '@attribute'        => 'customer:salesforce_account_owner_id',
        'sf_magento_enable' => '0',
    ),

    // Lead
    array(
        'local_field'       => 'Customer : salesforce_lead_owner_id',
        'sf_field'          => 'OwnerId',
        'sf_object'         => 'Lead',
        '@attribute'        => 'customer:salesforce_lead_owner_id',
        'sf_magento_enable' => '0',
    ),
);

$mappingTable    = $installer->getTable('tnw_salesforce/mapping');
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
        'sf_magento_enable' => '1',
        'sf_magento_type'   => 'upsert'
    ), $value);
}

$installer->getConnection()->insertOnDuplicate($mappingTable, $uoiData);

$installer->endSetup();