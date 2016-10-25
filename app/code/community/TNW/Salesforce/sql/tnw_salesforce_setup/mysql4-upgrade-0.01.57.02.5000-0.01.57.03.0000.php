<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$connection   = $installer->getConnection();

$mappingTable = $installer->getTable('tnw_salesforce/mapping');
$select = $connection->select()
    ->from($mappingTable)
    ->where($connection->prepareSqlCondition('local_field', array('like'=>'%: unit_price')))
    ->where($connection->prepareSqlCondition('sf_object', array('in'=> array(
        'OpportunityInvoiceItem',
        'OpportunityLineItem',
        'OrderInvoiceItem',
        'OrderItem',
        'Abandoneditem',
    ))));

$selectConfig = $connection->select()
    ->from($installer->getTable('core/config_data'), array('value'))
    ->where('path = :path');

$useTax      = (bool)(int)$connection->fetchOne($selectConfig, array('path' => TNW_Salesforce_Helper_Data::ORDER_USE_TAX_PRODUCT));
$useDiscount = (bool)(int)$connection->fetchOne($selectConfig, array('path' => TNW_Salesforce_Helper_Data::ORDER_USE_DISCOUNT_PRODUCT));

foreach ($connection->fetchAll($select) as $item) {
    list($entity, ) = array_map('trim', explode(':', $item['local_field'], 2));

    switch (true) {
        case !$useTax && !$useDiscount:
            $localName = 'unit_price_including_tax_and_discounts';
            break;

        case !$useTax && $useDiscount:
            $localName = 'unit_price_including_tax_excluding_discounts';
            break;

        case $useTax && !$useDiscount:
            $localName = 'unit_price_including_discounts_excluding_tax';
            break;

        case $useTax && $useDiscount:
            $localName = 'unit_price_excluding_tax_and_discounts';
            break;

        default:
            continue 2;
    }

    $connection->update($mappingTable, array('local_field' => "$entity : $localName"), "mapping_id = {$item['mapping_id']}");
}

$installer->endSetup();