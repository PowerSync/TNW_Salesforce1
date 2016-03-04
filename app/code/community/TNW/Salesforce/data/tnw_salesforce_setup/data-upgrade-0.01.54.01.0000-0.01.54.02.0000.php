<?php
/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

$mappingTable = $installer->getTable('tnw_salesforce/mapping');

$data = array(
    // Order
    array(
        'local_field'       => 'Order : number',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'Order',
    ),
    array(
        'local_field'       => 'Order : website',
        'sf_field'          => 'tnw_mage_basic__Magento_Website__c',
        'sf_object'         => 'Order',
    ),
    array(
        'local_field'       => 'Order : cart_all',
        'sf_field'          => 'Description',
        'sf_object'         => 'Order',
    ),
    array(
        'local_field'       => 'Order : created_at',
        'sf_field'          => 'EffectiveDate',
        'sf_object'         => 'Order',
        'backend_type'      => 'datetime'
    ),
    array(
        'local_field'       => 'Customer : salesforce_account_id',
        'sf_field'          => 'AccountId',
        'sf_object'         => 'Order',
        '@attribute'        => 'customer:salesforce_account_id'
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'tnw_mage_basic__BillingCustomer__c',
        'sf_object'         => 'Order',
        '@attribute'        => 'customer:salesforce_id'
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'BillToContactId',
        'sf_object'         => 'Order',
        '@attribute'        => 'customer:salesforce_id'
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'ShipToContactId',
        'sf_object'         => 'Order',
        '@attribute'        => 'customer:salesforce_id'
    ),
    array(
        'local_field'       => 'Order : opportunity_id',
        'sf_field'          => 'OpportunityId',
        'sf_object'         => 'Order'
    ),
    array(
        'local_field'       => 'Order : sf_status',
        'sf_field'          => 'Status',
        'sf_object'         => 'Order'
    ),
    array(
        'local_field'       => 'Order : sf_name',
        'sf_field'          => 'Name',
        'sf_object'         => 'Order'
    ),
    array(
        'local_field'       => 'Order : price_book',
        'sf_field'          => 'Pricebook2Id',
        'sf_object'         => 'Order'
    ),

    // OrderItem
    array(
        'local_field'       => 'Cart : unit_price',
        'sf_field'          => 'UnitPrice',
        'sf_object'         => 'OrderItem'
    ),
    array(
        'local_field'       => 'Cart : qty_ordered',
        'sf_field'          => 'Quantity',
        'sf_object'         => 'OrderItem'
    ),
    array(
        'local_field'       => 'Cart : sf_product_options_html',
        'sf_field'          => 'tnw_mage_basic__Product_Options__c',
        'sf_object'         => 'OrderItem'
    ),
    array(
        'local_field'       => 'Cart : sf_product_options_text',
        'sf_field'          => 'Description',
        'sf_object'         => 'OrderItem'
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
        'sf_magento_enable' => '1',
        'sf_magento_type'   => 'upsert'
    ), $value);
}, $data);

$installer->getConnection()->insertOnDuplicate($mappingTable, $data);

$installer->endSetup();
