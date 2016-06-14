<?php
/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

$mappingTable = $installer->getTable('tnw_salesforce/mapping');

$data = array(
    // Invoice
    array(
        'local_field'       => 'Invoice : sf_name',
        'sf_field'          => 'Name',
        'sf_object'         => 'OpportunityInvoice'
    ),
    array(
        'local_field'       => 'Invoice : created_at',
        'sf_field'          => 'tnw_invoice__Invoice_Date__c',
        'sf_object'         => 'OpportunityInvoice',
        'backend_type'      => 'datetime'
    ),
    array(
        'local_field'       => 'Invoice : number',
        'sf_field'          => 'tnw_invoice__Magento_ID__c',
        'sf_object'         => 'OpportunityInvoice',
    ),
    array(
        'local_field'       => 'Invoice : sf_status',
        'sf_field'          => 'tnw_invoice__Status__c',
        'sf_object'         => 'OpportunityInvoice',
    ),
    array(
        'local_field'       => 'Invoice : grand_total',
        'sf_field'          => 'tnw_invoice__Total__c',
        'sf_object'         => 'OpportunityInvoice',
    ),
    array(
        'local_field'       => 'Invoice : cart_all',
        'sf_field'          => 'tnw_invoice__Description__c',
        'sf_object'         => 'OpportunityInvoice',
    ),
    array(
        'local_field'       => 'Customer : salesforce_account_id',
        'sf_field'          => 'tnw_invoice__Account__c',
        'sf_object'         => 'OpportunityInvoice',
        '@attribute'        => 'customer:salesforce_account_id',
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'tnw_invoice__Billing_Contact__c',
        'sf_object'         => 'OpportunityInvoice',
        '@attribute'        => 'customer:salesforce_id'
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'tnw_invoice__Shipping_Contact__c',
        'sf_object'         => 'OpportunityInvoice',
        '@attribute'        => 'customer:salesforce_id'
    ),

    // Invoice Item
    array(
        'local_field'       => 'Product : sku',
        'sf_field'          => 'tnw_invoice__Product_Code__c',
        'sf_object'         => 'OpportunityInvoiceItem',
    ),
    array(
        'local_field'       => 'Product : name',
        'sf_field'          => 'Name',
        'sf_object'         => 'OpportunityInvoiceItem',
    ),
    array(
        'local_field'       => 'Billing Item : qty',
        'sf_field'          => 'tnw_invoice__Quantity__c',
        'sf_object'         => 'OpportunityInvoiceItem',
    ),
    array(
        'local_field'       => 'Billing Item : unit_price',
        'sf_field'          => 'tnw_invoice__Total__c',
        'sf_object'         => 'OpportunityInvoiceItem',
    ),
    array(
        'local_field'       => 'Billing Item : sf_product_options_text',
        'sf_field'          => 'tnw_invoice__Description__c',
        'sf_object'         => 'OpportunityInvoiceItem',
    ),
    array(
        'local_field'       => 'Billing Item : sf_product_options_html',
        'sf_field'          => 'tnw_invoice__Product_Options__c',
        'sf_object'         => 'OpportunityInvoiceItem',
    ),
    array(
        'local_field'       => 'Billing Item : number',
        'sf_field'          => 'tnw_invoice__Magento_ID__c',
        'sf_object'         => 'OpportunityInvoiceItem',
    ),

    // Shipment
    array(
        'local_field'       => 'Shipment : sf_name',
        'sf_field'          => 'Name',
        'sf_object'         => 'OpportunityShipment',
    ),
    array(
        'local_field'       => 'Shipment : website',
        'sf_field'          => 'tnw_shipment__Magento_Website__c',
        'sf_object'         => 'OpportunityShipment',
    ),
    array(
        'local_field'       => 'Shipment : created_at',
        'sf_field'          => 'tnw_shipment__Date_Shipped__c',
        'sf_object'         => 'OpportunityShipment',
        'backend_type'      => 'datetime',
    ),
    array(
        'local_field'       => 'Shipment : number',
        'sf_field'          => 'tnw_shipment__Magento_ID__c',
        'sf_object'         => 'OpportunityShipment',
    ),
    array(
        'local_field'       => 'Shipment : total_qty',
        'sf_field'          => 'tnw_shipment__Total_Quantity__c',
        'sf_object'         => 'OpportunityShipment',
    ),
    array(
        'local_field'       => 'Shipment : cart_all',
        'sf_field'          => 'tnw_shipment__Description__c',
        'sf_object'         => 'OpportunityShipment',
    ),
    array(
        'local_field'       => 'Customer : salesforce_account_id',
        'sf_field'          => 'tnw_shipment__Account__c',
        'sf_object'         => 'OpportunityShipment',
        '@attribute'        => 'customer:salesforce_account_id',
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'tnw_shipment__Billing_Contact__c',
        'sf_object'         => 'OpportunityShipment',
        '@attribute'        => 'customer:salesforce_id',
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'tnw_shipment__Shipping_Contact__c',
        'sf_object'         => 'OpportunityShipment',
        '@attribute'        => 'customer:salesforce_id',
    ),

    // Shipment Item
    array(
        'local_field'       => 'Product : sku',
        'sf_field'          => 'tnw_shipment__Product_Code__c',
        'sf_object'         => 'OpportunityShipmentItem',
    ),
    array(
        'local_field'       => 'Product : name',
        'sf_field'          => 'Name',
        'sf_object'         => 'OpportunityShipmentItem',
    ),
    array(
        'local_field'       => 'Shipment Item : sf_product_options_text',
        'sf_field'          => 'tnw_shipment__Description__c',
        'sf_object'         => 'OpportunityShipmentItem',
    ),
    array(
        'local_field'       => 'Shipment Item : sf_product_options_html',
        'sf_field'          => 'tnw_shipment__Product_Options__c',
        'sf_object'         => 'OpportunityShipmentItem',
    ),
    array(
        'local_field'       => 'Shipment Item : number',
        'sf_field'          => 'tnw_shipment__Magento_ID__c',
        'sf_object'         => 'OpportunityShipmentItem',
    ),
    array(
        'local_field'       => 'Shipment Item : qty',
        'sf_field'          => 'tnw_shipment__Quantity__c',
        'sf_object'         => 'OpportunityShipmentItem',
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
        'sf_magento_enable' => '1',
        'sf_magento_type'   => 'upsert'
    ), $value);
}

$installer->getConnection()->insertOnDuplicate($mappingTable, $uoiData);
$installer->endSetup();
