<?php
/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

$installer->getConnection()
    ->update($installer->getTable('core/config_data'), array(
        'path' => 'salesforce_order/currency/multi_currency'
    ), 'path LIKE \'salesforce_order/general/order_multi_currency\'');

$installer->getConnection()
    ->update($installer->getTable('core/config_data'), array(
        'path' => 'salesforce_order/currency/currency_sync'
    ), 'path LIKE \'salesforce_order/general/order_currency_sync\'');

$installer->endSetup();
