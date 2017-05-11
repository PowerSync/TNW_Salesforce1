<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$data = array(
    // Wishlist
    array(
        'local_field'       => 'Wishlist: number',
        'sf_field'          => 'tnw_mage_basic__Magento_ID__c',
        'sf_object'         => 'WishlistOpportunity',
        'magento_sf_enable' => '1',
    ),
    array(
        'local_field'       => 'Wishlist: website',
        'sf_field'          => 'tnw_mage_basic__Magento_Website__c',
        'sf_object'         => 'WishlistOpportunity',
        'magento_sf_enable' => '1',
    ),
    array(
        'local_field'       => 'Wishlist: sf_name',
        'sf_field'          => 'Name',
        'sf_object'         => 'WishlistOpportunity',
        'magento_sf_enable' => '1',
    ),
    array(
        'local_field'       => 'Wishlist: sf_stage',
        'sf_field'          => 'StageName',
        'sf_object'         => 'WishlistOpportunity',
        'magento_sf_enable' => '1',
    ),
    array(
        'local_field'       => 'Wishlist: sf_close_date',
        'sf_field'          => 'CloseDate',
        'sf_object'         => 'WishlistOpportunity',
        'magento_sf_enable' => '1',
    ),
    array(
        'local_field'       => 'Wishlist: cart_all',
        'sf_field'          => 'Description',
        'sf_object'         => 'WishlistOpportunity',
        'magento_sf_enable' => '1',
    ),
    array(
        'local_field'       => 'Wishlist: owner_salesforce_id',
        'sf_field'          => 'OwnerId',
        'sf_object'         => 'WishlistOpportunity',
        'magento_sf_enable' => '1',
    ),
    array(
        'local_field'       => 'Customer : salesforce_account_id',
        'sf_field'          => 'AccountId',
        'sf_object'         => 'WishlistOpportunity',
        'magento_sf_enable' => '1',
    ),
    array(
        'local_field'       => 'Custom : price_book',
        'sf_field'          => 'Pricebook2Id',
        'sf_object'         => 'WishlistOpportunity',
        'magento_sf_enable' => '1',
        'default_value'     => TNW_Salesforce_Helper_Data::PRODUCT_PRICEBOOK,
    ),

    // Wishlist Item
    array(
        'local_field'       => 'Wishlist Item: qty',
        'sf_field'          => 'Quantity',
        'sf_object'         => 'WishlistOpportunityLine',
        'magento_sf_enable' => '1',
        'sf_magento_enable' => '1',
    ),
    array(
        'local_field'       => 'Wishlist Item: sf_product_options_html',
        'sf_field'          => 'tnw_mage_basic__Product_Options__c',
        'sf_object'         => 'WishlistOpportunityLine',
        'magento_sf_enable' => '1',
    ),
    array(
        'local_field'       => 'Wishlist Item: sf_product_options_text',
        'sf_field'          => 'Description',
        'sf_object'         => 'WishlistOpportunityLine',
        'magento_sf_enable' => '1',
    ),
    array(
        'local_field'       => 'Product : price',
        'sf_field'          => 'UnitPrice',
        'sf_object'         => 'WishlistOpportunityLine',
        'magento_sf_enable' => '1',
    ),
    array(
        'local_field'       => 'Product : salesforce_pricebook_id',
        'sf_field'          => 'PricebookEntryId',
        'sf_object'         => 'WishlistOpportunityLine',
        'magento_sf_enable' => '1',
        'magento_sf_type'   => 'insert',
    ),
);

$uoiData = array();
foreach ($data as $value) {
    $uoiData[] = array_merge(array(
        'attribute_id'      => null,
        'backend_type'      => null,
        'default_value'     => null,
        'is_system'         => '1',
        'magento_sf_enable' => '0',
        'magento_sf_type'   => 'upsert',
        'sf_magento_enable' => '0',
        'sf_magento_type'   => 'upsert'
    ), $value);
}

$connection = $installer->getConnection();
$connection->insertOnDuplicate($installer->getTable('tnw_salesforce/mapping'), $uoiData);

$wishlistTable = $this->getTable('wishlist/wishlist');
if (!$connection->tableColumnExists($wishlistTable, 'sf_insync')) {
    $connection->addColumn($wishlistTable, 'sf_insync', 'boolean default FALSE');
}

if (!$connection->tableColumnExists($wishlistTable, 'salesforce_id')) {
    $connection->addColumn($wishlistTable, 'salesforce_id', 'varchar(50)');
}

$installer->endSetup();