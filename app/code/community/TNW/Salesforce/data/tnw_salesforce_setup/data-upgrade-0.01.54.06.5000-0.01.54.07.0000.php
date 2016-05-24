<?php
/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

$mappingTable = $installer->getTable('tnw_salesforce/mapping');
$usePickList  = Mage::helper('tnw_salesforce/config_customer')->useAddressPicklist();

$data = array(
    // Credit Memo
    array(
        'local_field'       => 'Credit Memo : sf_name',
        'sf_field'          => 'Name',
        'sf_object'         => 'OrderCreditMemo',
        'is_system'         => '1',
    ),
    array(
        'local_field'       => 'Credit Memo : sf_status',
        'sf_field'          => 'Status',
        'sf_object'         => 'OrderCreditMemo',
        'is_system'         => '1',
    ),
    array(
        'local_field'       => 'Credit Memo : created_at',
        'sf_field'          => 'EffectiveDate',
        'sf_object'         => 'OrderCreditMemo',
        'is_system'         => '1',
        'backend_type'      => 'datetime'
    ),
    array(
        'local_field'       => 'Credit Memo : number',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'OrderCreditMemo',
        'is_system'         => '1',
    ),
    array(
        'local_field'       => 'Credit Memo : website',
        'sf_field'          => 'tnw_mage_basic__Magento_Website__c',
        'sf_object'         => 'OrderCreditMemo',
        'is_system'         => '1',
    ),
    array(
        'local_field'       => 'Credit Memo : cart_all',
        'sf_field'          => 'Description',
        'sf_object'         => 'OrderCreditMemo',
    ),

    // Customer
    array(
        'local_field'       => 'Customer : salesforce_account_id',
        'sf_field'          => 'AccountId',
        'sf_object'         => 'OrderCreditMemo',
        'is_system'         => '1',
        '@attribute'        => 'customer:salesforce_account_id'
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'BillToContactId',
        'sf_object'         => 'OrderCreditMemo',
        'is_system'         => '1',
        '@attribute'        => 'customer:salesforce_id'
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'ShipToContactId',
        'sf_object'         => 'OrderCreditMemo',
        'is_system'         => '1',
        '@attribute'        => 'customer:salesforce_id'
    ),

    // Billing
    array(
        'local_field'       => 'Billing : street',
        'sf_field'          => 'BillingStreet',
        'sf_object'         => 'OrderCreditMemo',
        '@attribute'        => 'customer_address:street'
    ),
    array(
        'local_field'       => 'Billing : city',
        'sf_field'          => 'BillingCity',
        'sf_object'         => 'OrderCreditMemo',
        '@attribute'        => 'customer_address:city'
    ),
    array(
        'local_field'       => 'Billing : region',
        'sf_field'          => 'BillingState',
        'sf_object'         => 'OrderCreditMemo',
        'magento_sf_enable' => !$usePickList,
        'sf_magento_enable' => !$usePickList,
        '@attribute'        => 'customer_address:region'
    ),
    array(
        'local_field'       => 'Billing : region_id',
        'sf_field'          => 'BillingStateCode',
        'sf_object'         => 'OrderCreditMemo',
        'magento_sf_enable' => $usePickList,
        'sf_magento_enable' => $usePickList,
        '@attribute'        => 'customer_address:region_id'
    ),
    array(
        'local_field'       => 'Billing : postcode',
        'sf_field'          => 'BillingPostalCode',
        'sf_object'         => 'OrderCreditMemo',
        '@attribute'        => 'customer_address:postcode'
    ),
    array(
        'local_field'       => 'Billing : country_id',
        'sf_field'          => 'BillingCountry',
        'sf_object'         => 'OrderCreditMemo',
        'magento_sf_enable' => !$usePickList,
        'sf_magento_enable' => !$usePickList,
        '@attribute'        => 'customer_address:country_id'
    ),
    array(
        'local_field'       => 'Billing : country_id',
        'sf_field'          => 'BillingCountryCode',
        'sf_object'         => 'OrderCreditMemo',
        'magento_sf_enable' => $usePickList,
        'sf_magento_enable' => $usePickList,
        '@attribute'        => 'customer_address:country_id'
    ),

    // Shipping
    array(
        'local_field'       => 'Shipping : street',
        'sf_field'          => 'ShippingStreet',
        'sf_object'         => 'OrderCreditMemo',
        '@attribute'        => 'customer_address:street'
    ),
    array(
        'local_field'       => 'Shipping : city',
        'sf_field'          => 'ShippingCity',
        'sf_object'         => 'OrderCreditMemo',
        '@attribute'        => 'customer_address:city'
    ),
    array(
        'local_field'       => 'Shipping : region',
        'sf_field'          => 'ShippingState',
        'sf_object'         => 'OrderCreditMemo',
        'magento_sf_enable' => !$usePickList,
        'sf_magento_enable' => !$usePickList,
        '@attribute'        => 'customer_address:region'
    ),
    array(
        'local_field'       => 'Shipping : region_id',
        'sf_field'          => 'ShippingStateCode',
        'sf_object'         => 'OrderCreditMemo',
        'magento_sf_enable' => $usePickList,
        'sf_magento_enable' => $usePickList,
        '@attribute'        => 'customer_address:region_id'
    ),
    array(
        'local_field'       => 'Shipping : postcode',
        'sf_field'          => 'ShippingPostalCode',
        'sf_object'         => 'OrderCreditMemo',
        '@attribute'        => 'customer_address:postcode'
    ),
    array(
        'local_field'       => 'Shipping : country_id',
        'sf_field'          => 'ShippingCountry',
        'sf_object'         => 'OrderCreditMemo',
        'magento_sf_enable' => !$usePickList,
        'sf_magento_enable' => !$usePickList,
        '@attribute'        => 'customer_address:country_id'
    ),
    array(
        'local_field'       => 'Shipping : country_id',
        'sf_field'          => 'ShippingCountryCode',
        'sf_object'         => 'OrderCreditMemo',
        'magento_sf_enable' => $usePickList,
        'sf_magento_enable' => $usePickList,
        '@attribute'        => 'customer_address:country_id'
    ),

    // Credit Memo Item
    array(
        'local_field'       => 'Credit Memo Item : qty',
        'sf_field'          => 'Quantity',
        'sf_object'         => 'OrderCreditMemoItem',
        'is_system'         => '1',
    ),
    array(
        'local_field'       => 'Credit Memo Item : sf_product_options_text',
        'sf_field'          => 'Description',
        'sf_object'         => 'OrderCreditMemoItem',
    ),
    array(
        'local_field'       => 'Credit Memo Item : sf_product_options_html',
        'sf_field'          => 'tnw_mage_basic__Product_Options__c',
        'sf_object'         => 'OrderCreditMemoItem',
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
        'is_system'         => '0',
        'magento_sf_enable' => '1',
        'magento_sf_type'   => 'upsert',
        'sf_magento_enable' => '1',
        'sf_magento_type'   => 'upsert'
    ), $value);
}, $data);

$installer->getConnection()->insertOnDuplicate($mappingTable, $data);

$installer->endSetup();
