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
        'local_field'       => 'Order Item : unit_price',
        'sf_field'          => 'UnitPrice',
        'sf_object'         => 'OrderItem'
    ),
    array(
        'local_field'       => 'Order Item : qty_ordered',
        'sf_field'          => 'Quantity',
        'sf_object'         => 'OrderItem'
    ),
    array(
        'local_field'       => 'Order Item : sf_product_options_html',
        'sf_field'          => 'tnw_mage_basic__Product_Options__c',
        'sf_object'         => 'OrderItem'
    ),
    array(
        'local_field'       => 'Order Item : sf_product_options_text',
        'sf_field'          => 'Description',
        'sf_object'         => 'OrderItem'
    ),

    // Invoice
    array(
        'local_field'       => 'Invoice : sf_name',
        'sf_field'          => 'Name',
        'sf_object'         => 'OrderInvoice'
    ),
    array(
        'local_field'       => 'Invoice : created_at',
        'sf_field'          => 'tnw_fulfilment__Invoice_Date__c',
        'sf_object'         => 'OrderInvoice',
        'backend_type'      => 'datetime'
    ),
    array(
        'local_field'       => 'Invoice : number',
        'sf_field'          => 'tnw_fulfilment__Magento_ID__c',
        'sf_object'         => 'OrderInvoice',
    ),
    array(
        'local_field'       => 'Invoice : sf_status',
        'sf_field'          => 'tnw_fulfilment__Status__c',
        'sf_object'         => 'OrderInvoice',
    ),
    array(
        'local_field'       => 'Invoice : grand_total',
        'sf_field'          => 'tnw_fulfilment__Total__c',
        'sf_object'         => 'OrderInvoice',
    ),
    array(
        'local_field'       => 'Invoice : cart_all',
        'sf_field'          => 'tnw_fulfilment__Description__c',
        'sf_object'         => 'OrderInvoice',
    ),
    array(
        'local_field'       => 'Customer : salesforce_account_id',
        'sf_field'          => 'tnw_fulfilment__Account__c',
        'sf_object'         => 'OrderInvoice',
        '@attribute'        => 'customer:salesforce_account_id',
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'tnw_fulfilment__Billing_Contact__c',
        'sf_object'         => 'OrderInvoice',
        '@attribute'        => 'customer:salesforce_id'
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'tnw_fulfilment__Shipping_Contact__c',
        'sf_object'         => 'OrderInvoice',
        '@attribute'        => 'customer:salesforce_id'
    ),

    // Invoice Item
    array(
        'local_field'       => 'Product : sku',
        'sf_field'          => 'tnw_fulfilment__Product_Code__c',
        'sf_object'         => 'OrderInvoiceItem',
    ),
    array(
        'local_field'       => 'Product : name',
        'sf_field'          => 'Name',
        'sf_object'         => 'OrderInvoiceItem',
    ),
    array(
        'local_field'       => 'Billing Item : qty',
        'sf_field'          => 'tnw_fulfilment__Quantity__c',
        'sf_object'         => 'OrderInvoiceItem',
    ),
    array(
        'local_field'       => 'Billing Item : unit_price',
        'sf_field'          => 'tnw_fulfilment__Total__c',
        'sf_object'         => 'OrderInvoiceItem',
    ),
    array(
        'local_field'       => 'Billing Item : sf_product_options_text',
        'sf_field'          => 'tnw_fulfilment__Description__c',
        'sf_object'         => 'OrderInvoiceItem',
    ),
    array(
        'local_field'       => 'Billing Item : sf_product_options_html',
        'sf_field'          => 'tnw_fulfilment__Product_Options__c',
        'sf_object'         => 'OrderInvoiceItem',
    ),
    array(
        'local_field'       => 'Billing Item : number',
        'sf_field'          => 'tnw_fulfilment__Magento_ID__c',
        'sf_object'         => 'OrderInvoiceItem',
    ),

    // Shipment
    array(
        'local_field'       => 'Shipment : sf_name',
        'sf_field'          => 'Name',
        'sf_object'         => 'OrderShipment',
    ),
    array(
        'local_field'       => 'Shipment : website',
        'sf_field'          => 'tnw_fulfilment__Magento_Website__c',
        'sf_object'         => 'OrderShipment',
    ),
    array(
        'local_field'       => 'Shipment : created_at',
        'sf_field'          => 'tnw_fulfilment__Date_Shipped__c',
        'sf_object'         => 'OrderShipment',
        'backend_type'      => 'datetime',
    ),
    array(
        'local_field'       => 'Shipment : number',
        'sf_field'          => 'tnw_fulfilment__Magento_ID__c',
        'sf_object'         => 'OrderShipment',
    ),
    array(
        'local_field'       => 'Shipment : total_qty',
        'sf_field'          => 'tnw_fulfilment__Total_Quantity__c',
        'sf_object'         => 'OrderShipment',
    ),
    array(
        'local_field'       => 'Shipment : cart_all',
        'sf_field'          => 'tnw_fulfilment__Description__c',
        'sf_object'         => 'OrderShipment',
    ),
    array(
        'local_field'       => 'Customer : salesforce_account_id',
        'sf_field'          => 'tnw_fulfilment__Account__c',
        'sf_object'         => 'OrderShipment',
        '@attribute'        => 'customer:salesforce_account_id',
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'tnw_fulfilment__Billing_Contact__c',
        'sf_object'         => 'OrderShipment',
        '@attribute'        => 'customer:salesforce_id',
    ),
    array(
        'local_field'       => 'Customer : salesforce_id',
        'sf_field'          => 'tnw_fulfilment__Shipping_Contact__c',
        'sf_object'         => 'OrderShipment',
        '@attribute'        => 'customer:salesforce_id',
    ),

    // Shipment Item
    array(
        'local_field'       => 'Product : sku',
        'sf_field'          => 'tnw_fulfilment__Product_Code__c',
        'sf_object'         => 'OrderShipmentItem',
    ),
    array(
        'local_field'       => 'Product : name',
        'sf_field'          => 'Name',
        'sf_object'         => 'OrderShipmentItem',
    ),
    array(
        'local_field'       => 'Shipment Item : sf_product_options_text',
        'sf_field'          => 'tnw_fulfilment__Description__c',
        'sf_object'         => 'OrderShipmentItem',
    ),
    array(
        'local_field'       => 'Shipment Item : sf_product_options_html',
        'sf_field'          => 'tnw_fulfilment__Product_Options__c',
        'sf_object'         => 'OrderShipmentItem',
    ),
    array(
        'local_field'       => 'Shipment Item : number',
        'sf_field'          => 'tnw_fulfilment__Magento_ID__c',
        'sf_object'         => 'OrderShipmentItem',
    ),
    array(
        'local_field'       => 'Shipment Item : qty',
        'sf_field'          => 'tnw_fulfilment__Quantity__c',
        'sf_object'         => 'OrderShipmentItem',
    ),

    // Opportunity
    array(
        'local_field'       => 'Order : number',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'Opportunity',
    ),
    array(
        'local_field'       => 'Order : created_at',
        'sf_field'          => 'CloseDate',
        'sf_object'         => 'Opportunity',
        'backend_type'      => 'datetime',
    ),
    array(
        'local_field'       => 'Order : cart_all',
        'sf_field'          => 'Description',
        'sf_object'         => 'Opportunity',
    ),
    array(
        'local_field'       => 'Order : sf_status',
        'sf_field'          => 'StageName',
        'sf_object'         => 'Opportunity',
    ),
    array(
        'local_field'       => 'Order : website',
        'sf_field'          => 'tnw_mage_basic__Magento_Website__c',
        'sf_object'         => 'Opportunity',
    ),
    array(
        'local_field'       => 'Order : price_book',
        'sf_field'          => 'Pricebook2Id',
        'sf_object'         => 'Opportunity'
    ),
    array(
        'local_field'       => 'Customer : salesforce_account_id',
        'sf_field'          => 'AccountId',
        'sf_object'         => 'Opportunity',
        '@attribute'        => 'customer:salesforce_account_id',
    ),
    array(
        'local_field'       => 'Order : sf_name',
        'sf_field'          => 'Name',
        'sf_object'         => 'Opportunity',
    ),

    // Opportunity Item
    array(
        'local_field'       => 'Order Item : unit_price',
        'sf_field'          => 'UnitPrice',
        'sf_object'         => 'OpportunityLineItem'
    ),
    array(
        'local_field'       => 'Order Item : qty_ordered',
        'sf_field'          => 'Quantity',
        'sf_object'         => 'OpportunityLineItem'
    ),
    array(
        'local_field'       => 'Order Item : sf_product_options_html',
        'sf_field'          => 'tnw_mage_basic__Product_Options__c',
        'sf_object'         => 'OpportunityLineItem'
    ),
    array(
        'local_field'       => 'Order Item : sf_product_options_text',
        'sf_field'          => 'Description',
        'sf_object'         => 'OpportunityLineItem'
    ),

    // Abandoned
    array(
        'local_field'       => 'Cart : number',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'Abandoned',
    ),
    array(
        'local_field'       => 'Cart : website',
        'sf_field'          => 'tnw_mage_basic__Magento_Website__c',
        'sf_object'         => 'Abandoned',
    ),
    array(
        'local_field'       => 'Cart : sf_name',
        'sf_field'          => 'Name',
        'sf_object'         => 'Abandoned',
    ),
    array(
        'local_field'       => 'Cart : sf_stage',
        'sf_field'          => 'StageName',
        'sf_object'         => 'Abandoned',
    ),
    array(
        'local_field'       => 'Cart : price_book',
        'sf_field'          => 'Pricebook2Id',
        'sf_object'         => 'Abandoned',
    ),
    array(
        'local_field'       => 'Cart : updated_at',
        'sf_field'          => 'CloseDate',
        'sf_object'         => 'Abandoned',
    ),
    array(
        'local_field'       => 'Customer : salesforce_account_id',
        'sf_field'          => 'AccountId',
        'sf_object'         => 'Abandoned',
        '@attribute'        => 'customer:salesforce_account_id',
    ),
    array(
        'local_field'       => 'Cart : cart_all',
        'sf_field'          => 'Description',
        'sf_object'         => 'Abandoned',
    ),

    // Abandoned Item
    array(
        'local_field'       => 'Cart Item : unit_price',
        'sf_field'          => 'UnitPrice',
        'sf_object'         => 'Abandoneditem'
    ),
    array(
        'local_field'       => 'Cart Item : qty',
        'sf_field'          => 'Quantity',
        'sf_object'         => 'OpportunityLineItem'
    ),
    array(
        'local_field'       => 'Cart Item : sf_product_options_html',
        'sf_field'          => 'tnw_mage_basic__Product_Options__c',
        'sf_object'         => 'Abandoneditem'
    ),
    array(
        'local_field'       => 'Cart Item : sf_product_options_text',
        'sf_field'          => 'Description',
        'sf_object'         => 'Abandoneditem'
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

$installer->getConnection()->update($mappingTable, array(
    'local_field' => new Zend_Db_Expr("REPLACE(local_field, 'Cart', 'Order Item')")
), array('sf_object = ?' => array('OrderItem', 'OpportunityLineItem')));
$installer->getConnection()->update($mappingTable, array(
    'local_field' => new Zend_Db_Expr("REPLACE(local_field, 'Item', 'Cart Item')")
), array('sf_object = ?' => 'Quote'));

$installer->getConnection()->insertOnDuplicate($mappingTable, $data);

$installer->endSetup();
