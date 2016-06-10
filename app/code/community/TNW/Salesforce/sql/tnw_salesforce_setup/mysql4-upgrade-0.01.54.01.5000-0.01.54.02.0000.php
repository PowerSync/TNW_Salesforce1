<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$mappingTable = $installer->getTable('tnw_salesforce/mapping');
$installer->getConnection()->addColumn($mappingTable, 'is_system', array(
    'type'      => Varien_Db_Ddl_Table::TYPE_SMALLINT,
    'length'    => 2,
    'default'   => 0,
    'nullable'  => false,
    'comment'   => 'Is system'
));

$installer->getConnection()->addIndex(
    $mappingTable,
    $installer->getIdxName(
        'tnw_salesforce/mapping',
        array('local_field', 'sf_object', 'sf_field'),
        Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
    ),
    array('local_field', 'sf_object', 'sf_field'),
    Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
);

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
        'sf_object'         => 'Abandoneditem'
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

    // Product
    array(
        'local_field'       => 'Product : attribute_set_id',
        'sf_field'          => 'tnw_mage_basic__Attribute_Set__c',
        'sf_object'         => 'Product2',
    ),
    array(
        'local_field'       => 'Product : type_id',
        'sf_field'          => 'tnw_mage_basic__Product_Type__c',
        'sf_object'         => 'Product2',
        'sf_magento_type'   => 'update'
    ),
    array(
        'local_field'       => 'Product : sku',
        'sf_field'          => 'ProductCode',
        'sf_object'         => 'Product2',
    ),
    array(
        'local_field'       => 'Product : name',
        'sf_field'          => 'Name',
        'sf_object'         => 'Product2',
        'magento_sf_type'   => 'insert',
        'sf_magento_type'   => 'insert'
    ),
    array(
        'local_field'       => 'Product : id',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'Product2',
    ),
    array(
        'local_field'       => 'Custom : active',
        'sf_field'          => 'IsActive',
        'sf_object'         => 'Product2',
        'default_value'     => 'Enabled',
    ),

    // Contact
    array(
        'local_field'       => 'Customer : email',
        'sf_field'          => 'Email',
        'sf_object'         => 'Contact',
        '@attribute'        => 'customer:email',
    ),
    array(
        'local_field'       => 'Customer : firstname',
        'sf_field'          => 'FirstName',
        'sf_object'         => 'Contact',
        '@attribute'        => 'customer:firstname',
    ),
    array(
        'local_field'       => 'Customer : lastname',
        'sf_field'          => 'LastName',
        'sf_object'         => 'Contact',
        '@attribute'        => 'customer:lastname',
    ),
    array(
        'local_field'       => 'Customer : id',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'Contact',
    ),
    array(
        'local_field'       => 'Customer : website_id',
        'sf_field'          => 'tnw_mage_basic__Magento_Website__c',
        'sf_object'         => 'Contact',
        '@attribute'        => 'customer:website_id',
    ),
    array(
        'local_field'       => 'Customer : sf_email_opt_out',
        'sf_field'          => 'HasOptedOutOfEmail',
        'sf_object'         => 'Contact',
    ),

    // Account
    array(
        'local_field'       => 'Customer : sf_record_type',
        'sf_field'          => 'RecordTypeId',
        'sf_object'         => 'Account',
        'sf_magento_enable' => '0',
    ),
    array(
        'local_field'       => 'Billing : company',
        'sf_field'          => 'Name',
        'sf_object'         => 'Account',
        '@attribute'        => 'customer_address:company',
    ),

    // Lead
    array(
        'local_field'       => 'Customer : email',
        'sf_field'          => 'Email',
        'sf_object'         => 'Lead',
        '@attribute'        => 'customer:email',
    ),
    array(
        'local_field'       => 'Customer : firstname',
        'sf_field'          => 'FirstName',
        'sf_object'         => 'Lead',
        '@attribute'        => 'customer:firstname',
    ),
    array(
        'local_field'       => 'Customer : lastname',
        'sf_field'          => 'LastName',
        'sf_object'         => 'Lead',
        '@attribute'        => 'customer:lastname',
    ),
    array(
        'local_field'       => 'Billing : company',
        'sf_field'          => 'Company',
        'sf_object'         => 'Lead',
        '@attribute'        => 'customer_address:company',
    ),
    array(
        'local_field'       => 'Customer : id',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'Lead',
    ),
    array(
        'local_field'       => 'Customer : website_id',
        'sf_field'          => 'tnw_mage_basic__Magento_Website__c',
        'sf_object'         => 'Lead',
        '@attribute'        => 'customer:website_id',
    ),
    array(
        'local_field'       => 'Customer : sf_email_opt_out',
        'sf_field'          => 'HasOptedOutOfEmail',
        'sf_object'         => 'Lead',
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

$installer->getConnection()->update($mappingTable, array(
    'local_field' => new Zend_Db_Expr("REPLACE(local_field, 'Cart', 'Order Item')")
), array('sf_object' => array('in' => array('OrderItem', 'OpportunityLineItem'))));
$installer->getConnection()->update($mappingTable, array(
    'local_field' => new Zend_Db_Expr("REPLACE(local_field, 'Item', 'Cart Item')")
), array('sf_object = ?' => 'Quote'));

$installer->getConnection()->insertOnDuplicate($mappingTable, $uoiData);
$installer->endSetup();