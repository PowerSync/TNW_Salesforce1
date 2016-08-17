<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$configTable = $installer->getTable('core/config_data');
$connection  = $installer->getConnection();

$connection->update($configTable, array('path' => 'salesforce_customer/account/single_account_sync_customer'),
    'path LIKE \'salesforce_customer/sync/single_account_sync_customer\'');
$connection->update($configTable, array('path' => 'salesforce_customer/account/single_account_select'),
    'path LIKE \'salesforce_customer/sync/single_account_select\'');
$connection->update($configTable, array('path' => 'salesforce_customer/contact/contact_asignee'),
    'path LIKE \'salesforce_customer/sync/contact_asignee\'');

$connection->update($configTable, array('path' => 'salesforce_contactus/general/customer_form_enable'),
    'path LIKE \'salesforce_customer/contactus/customer_form_enable\'');
$connection->update($configTable, array('path' => 'salesforce_contactus/general/customer_form_assigned'),
    'path LIKE \'salesforce_customer/contactus/customer_form_assigned\'');

$select = $connection->select()
    ->from($configTable, array('value'))
    ->where('path LIKE \'salesforce_customer/sync/merge_duplicates\'');

$value = $connection->fetchOne($select);

$connection->insertMultiple($configTable, array(
    array('scope'=>'default', 'scope_id' => 0, 'path'=>'salesforce_customer/account/merge_duplicates', 'value'=>$value),
    array('scope'=>'default', 'scope_id' => 0, 'path'=>'salesforce_customer/contact/merge_duplicates', 'value'=>$value),
    array('scope'=>'default', 'scope_id' => 0, 'path'=>'salesforce_customer/lead_config/merge_duplicates', 'value'=>$value),
));

$deleteSelect = $connection->deleteFromSelect($select, $configTable);
$connection->query($deleteSelect);

$installer->endSetup();