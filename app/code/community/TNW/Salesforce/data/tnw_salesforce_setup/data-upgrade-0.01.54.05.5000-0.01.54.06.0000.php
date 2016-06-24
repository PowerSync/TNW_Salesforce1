<?php
/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

$configCollection = Mage::getResourceModel('core/config_data_collection')
    ->addPathFilter('salesforce/development_and_debugging');

$modifyBatch = array(
    'product_batch_size',
    'customer_batch_size',
    'website_batch_size',
    'order_batch_size',
    'abandoned_batch_size',
    'invoice_batch_size',
    'shipment_batch_size',
);

/** @var Mage_Core_Model_Config_Data $item */
foreach ($configCollection as $item) {
    list(,, $batchName) = explode('/', $item->getPath());
    if (!in_array($batchName, $modifyBatch)) {
        continue;
    }

    $item->setValue(500);
    $item->save();
}

$installer->endSetup();
