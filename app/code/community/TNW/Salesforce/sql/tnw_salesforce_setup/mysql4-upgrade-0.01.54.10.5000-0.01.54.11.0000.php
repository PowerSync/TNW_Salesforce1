<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$renameSelect = $installer->getConnection()->select()
    ->from($installer->getTable('core/config_data'), array('value'))
    ->where('path = ?', 'salesforce_customer/sync/account_rename')
    ->limit(1);
$isRenameReadonly = $installer->getConnection()->fetchOne($renameSelect);
$isRenameReadonly = ($isRenameReadonly === false) ? true : $isRenameReadonly;

$data = array(
    // Product
    array(
        'local_field'       => 'Product : attribute_set_id',
        'sf_field'          => 'tnw_mage_basic__Attribute_Set__c',
        'sf_object'         => 'Product2',
        'default_value'     => 'Default',
        'sf_magento_type'   => 'insert',
    ),
    array(
        'local_field'       => 'Product : type_id',
        'sf_field'          => 'tnw_mage_basic__Product_Type__c',
        'sf_object'         => 'Product2',
        'default_value'     => 'Simple Product',
        'sf_magento_type'   => 'insert',
    ),
    array(
        'local_field'       => 'Product : id',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'Product2',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Product : name',
        'sf_field'          => 'Name',
        'sf_object'         => 'Product2',
        'magento_sf_type'   => 'upsert',
        'sf_magento_type'   => 'upsert',
    ),

    // Account
    array(
        'local_field'       => 'Customer : sf_company',
        'sf_field'          => 'Name',
        'sf_object'         => 'Account',
        'magento_sf_type'   => $isRenameReadonly ? 'insert' : 'upsert',
        'sf_magento_enable' => '0',
    ),

    // Contact
    array(
        'local_field'       => 'Customer : email',
        'sf_field'          => 'Email',
        'sf_object'         => 'Contact',
        '@attribute'        => 'customer:email',
        'sf_magento_type'   => 'insert',
    ),
    array(
        'local_field'       => 'Customer : id',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'Contact',
        'sf_magento_enable' => '0',
    ),

    // Lead
    array(
        'local_field'       => 'Customer : id',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'Lead',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Customer : email',
        'sf_field'          => 'Email',
        'sf_object'         => 'Lead',
        '@attribute'        => 'customer:email',
        'sf_magento_type'   => 'insert',
    ),
    array(
        'local_field'       => 'Customer : sf_company',
        'sf_field'          => 'Company',
        'sf_object'         => 'Lead',
        'sf_magento_enable' => '0',
    ),

    // Order
    array(
        'local_field'       => 'Order : number',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'Order',
        'sf_magento_enable' => '0',
    ),

    // Order Item
    array(
        'local_field'       => 'Order Item : unit_price',
        'sf_field'          => 'UnitPrice',
        'sf_object'         => 'OrderItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Order Item : qty_ordered',
        'sf_field'          => 'Quantity',
        'sf_object'         => 'OrderItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Order Item : sf_product_options_html',
        'sf_field'          => 'tnw_mage_basic__Product_Options__c',
        'sf_object'         => 'OrderItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Order Item : sf_product_options_text',
        'sf_field'          => 'Description',
        'sf_object'         => 'OrderItem',
        'sf_magento_enable' => '0',
    ),

    // Order Invoice
    array(
        'local_field'       => 'Invoice : number',
        'sf_field'          => 'tnw_invoice__Magento_ID__c',
        'sf_object'         => 'OrderInvoice',
        'sf_magento_enable' => '0',
    ),

    // Order Invoice Item
    array(
        'local_field'       => 'Product : sku',
        'sf_field'          => 'tnw_invoice__Product_Code__c',
        'sf_object'         => 'OrderInvoiceItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Product : name',
        'sf_field'          => 'Name',
        'sf_object'         => 'OrderInvoiceItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Billing Item : qty',
        'sf_field'          => 'tnw_invoice__Quantity__c',
        'sf_object'         => 'OrderInvoiceItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Billing Item : unit_price',
        'sf_field'          => 'tnw_invoice__Total__c',
        'sf_object'         => 'OrderInvoiceItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Billing Item : sf_product_options_text',
        'sf_field'          => 'tnw_invoice__Description__c',
        'sf_object'         => 'OrderInvoiceItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Billing Item : sf_product_options_html',
        'sf_field'          => 'tnw_invoice__Product_Options__c',
        'sf_object'         => 'OrderInvoiceItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Billing Item : number',
        'sf_field'          => 'tnw_invoice__Magento_ID__c',
        'sf_object'         => 'OrderInvoiceItem',
        'sf_magento_enable' => '0',
    ),

    // Order Shipment
    array(
        'local_field'       => 'Shipment : number',
        'sf_field'          => 'tnw_shipment__Magento_ID__c',
        'sf_object'         => 'OrderShipment',
        'sf_magento_enable' => '0',
    ),

    // Order Shipment Item
    array(
        'local_field'       => 'Shipment Item : number',
        'sf_field'          => 'tnw_shipment__Magento_ID__c',
        'sf_object'         => 'OrderShipmentItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Product : sku',
        'sf_field'          => 'tnw_shipment__Product_Code__c',
        'sf_object'         => 'OrderShipmentItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Product : name',
        'sf_field'          => 'Name',
        'sf_object'         => 'OrderShipmentItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Shipment Item : sf_product_options_text',
        'sf_field'          => 'tnw_shipment__Description__c',
        'sf_object'         => 'OrderShipmentItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Shipment Item : sf_product_options_html',
        'sf_field'          => 'tnw_shipment__Product_Options__c',
        'sf_object'         => 'OrderShipmentItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Shipment Item : qty',
        'sf_field'          => 'tnw_shipment__Quantity__c',
        'sf_object'         => 'OrderShipmentItem',
        'sf_magento_enable' => '0',
    ),

    // Opportunity
    array(
        'local_field'       => 'Order : number',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'Opportunity',
        'sf_magento_enable' => '0',
    ),

    // Opportunity Item
    array(
        'local_field'       => 'Order Item : unit_price',
        'sf_field'          => 'UnitPrice',
        'sf_object'         => 'OpportunityLineItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Order Item : qty_ordered',
        'sf_field'          => 'Quantity',
        'sf_object'         => 'OpportunityLineItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Order Item : sf_product_options_html',
        'sf_field'          => 'tnw_mage_basic__Product_Options__c',
        'sf_object'         => 'OpportunityLineItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Order Item : sf_product_options_text',
        'sf_field'          => 'Description',
        'sf_object'         => 'OpportunityLineItem',
        'sf_magento_enable' => '0',
    ),

    // Opportunity Invoice
    array(
        'local_field'       => 'Invoice : number',
        'sf_field'          => 'tnw_invoice__Magento_ID__c',
        'sf_object'         => 'OpportunityInvoice',
        'sf_magento_enable' => '0',
    ),

    // Opportunity Invoice Item
    array(
        'local_field'       => 'Billing Item : number',
        'sf_field'          => 'tnw_invoice__Magento_ID__c',
        'sf_object'         => 'OpportunityInvoiceItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Product : sku',
        'sf_field'          => 'tnw_invoice__Product_Code__c',
        'sf_object'         => 'OpportunityInvoiceItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Product : name',
        'sf_field'          => 'Name',
        'sf_object'         => 'OpportunityInvoiceItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Billing Item : qty',
        'sf_field'          => 'tnw_invoice__Quantity__c',
        'sf_object'         => 'OpportunityInvoiceItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Billing Item : unit_price',
        'sf_field'          => 'tnw_invoice__Total__c',
        'sf_object'         => 'OpportunityInvoiceItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Billing Item : sf_product_options_text',
        'sf_field'          => 'tnw_invoice__Description__c',
        'sf_object'         => 'OpportunityInvoiceItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Billing Item : sf_product_options_html',
        'sf_field'          => 'tnw_invoice__Product_Options__c',
        'sf_object'         => 'OpportunityInvoiceItem',
        'sf_magento_enable' => '0',
    ),

    // Opportunity Shipment
    array(
        'local_field'       => 'Shipment : number',
        'sf_field'          => 'tnw_shipment__Magento_ID__c',
        'sf_object'         => 'OpportunityShipment',
        'sf_magento_enable' => '0',
    ),

    // Opportunity Shipment Item
    array(
        'local_field'       => 'Shipment Item : number',
        'sf_field'          => 'tnw_shipment__Magento_ID__c',
        'sf_object'         => 'OpportunityShipmentItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Product : sku',
        'sf_field'          => 'tnw_shipment__Product_Code__c',
        'sf_object'         => 'OpportunityShipmentItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Product : name',
        'sf_field'          => 'Name',
        'sf_object'         => 'OpportunityShipmentItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Shipment Item : sf_product_options_text',
        'sf_field'          => 'tnw_shipment__Description__c',
        'sf_object'         => 'OpportunityShipmentItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Shipment Item : sf_product_options_html',
        'sf_field'          => 'tnw_shipment__Product_Options__c',
        'sf_object'         => 'OpportunityShipmentItem',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Shipment Item : qty',
        'sf_field'          => 'tnw_shipment__Quantity__c',
        'sf_object'         => 'OpportunityShipmentItem',
        'sf_magento_enable' => '0',
    ),

    // Abandoned
    array(
        'local_field'       => 'Cart : number',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'Abandoned',
        'sf_magento_enable' => '0',
    ),

    // CampaignSalesRule
    array(
        'local_field'       => 'Shopping Cart Rule : number',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'CampaignSalesRule',
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

$query = $installer->getConnection()->select()->from($mappingTable)
    ->where('local_field = ?', 'Billing : company')
    ->where('sf_field = ?', 'Name')
    ->where('sf_object = ?', 'Account')
    ->deleteFromSelect($mappingTable);
$installer->getConnection()->query($query);

$query = $installer->getConnection()->select()->from($mappingTable)
    ->where('local_field = ?', 'Billing : company')
    ->where('sf_field = ?', 'Company')
    ->where('sf_object = ?', 'Lead')
    ->deleteFromSelect($mappingTable);
$installer->getConnection()->query($query);

$installer->endSetup();