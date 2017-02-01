<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$configTable = $installer->getTable('core/config_data');
$adapter = $installer->getConnection();

$select = $adapter->select()
    ->from($configTable, array('value', 'config_id'))
    ->where($adapter->prepareSqlCondition('path', array('in'=>array(
        'salesforce_order/opportunity_cart/tax_product_pricebook',
        'salesforce_order/opportunity_cart/shipping_product_pricebook',
        'salesforce_order/opportunity_cart/discount_product_pricebook',
    ))));

$rows = $adapter->fetchAll($select);
if (is_array($rows)) {
    foreach ($rows as $row) {
        $value = @unserialize($row['value']);
        if (!is_array($value)) {
            continue;
        }

        $adapter->update($configTable, array(
            'value'=>serialize(array_intersect_key($value, array_flip(array('Id', 'Name', 'ProductCode'))))
        ), $adapter->prepareSqlCondition('config_id', $row['config_id']));
    }
}

$installer->endSetup();