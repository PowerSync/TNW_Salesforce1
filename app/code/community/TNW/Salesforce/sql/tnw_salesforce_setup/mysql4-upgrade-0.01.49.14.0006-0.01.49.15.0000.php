<?php
$installer = $this;

$installer->startSetup();


$tableName = Mage::getSingleton('core/resource')->getTableName('tnw_salesforce_mapping');

$sql = "SELECT mapping_id, sf_field FROM `" . $tableName ."` WHERE sf_field LIKE ('tnw_powersync__%');";
$rows = Mage::helper('tnw_salesforce')->getDbConnection('read')->query($sql)->fetchAll();

$_sql = '';
if ($rows) {
    foreach ($rows as $_row) {
        if (array_key_exists('mapping_id', $_row)) {
            $_sql .= "UPDATE `" . $tableName . "` SET sf_field = '" . str_replace('tnw_powersync__', Mage::helper('tnw_salesforce/config')->getSalesforcePrefix(), $_row['sf_field']) . "' WHERE mapping_id = " . $_row['mapping_id'] . ";";
        }
    }
}

if (!empty($_sql)) {
    Mage::helper('tnw_salesforce')->getDbConnection()->query($_sql);
}

$installer->endSetup();