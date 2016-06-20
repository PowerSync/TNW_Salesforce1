<?php

/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$tableName = $installer->getTable('tnw_salesforce/account_matching');
if (!$installer->tableExists($tableName))
{
    $table = $installer->getConnection()
        ->newTable($tableName)
        ->addColumn('matching_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity' => true,
            'nullable' => false,
            'primary' => true,
        ), 'ID Field')
        ->addColumn('account_name', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(
            'nullable' => false,
            'default' => '',
        ), 'Account Name')
        ->addColumn('account_id', Varien_Db_Ddl_Table::TYPE_TEXT, 32, array(
            'nullable' => false,
            'default' => '',
        ), 'Account Id')
        ->addColumn('email_domain', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(
            'nullable' => false,
            'default' => '',
        ), 'Email Domain')
        ->setComment('Account Matching table');

    $table->addIndex(
        $installer->getIdxName('tnw_salesforce/account_matching', array('email_domain'), Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE),
        array('email_domain'),
        array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE)
    );

    $installer->getConnection()->createTable($table);
}

// Create Data
$select = $installer->getConnection()->select()
    ->from($installer->getTable('core/config_data'), 'value')
    ->where('path LIKE \'salesforce_customer/account_catchall/domains\'')
    ->limit(1);

$conf = $installer->getConnection()
    ->fetchOne($select);

if (!empty($conf) && ($data = @unserialize($conf)) && is_array($data) && array_key_exists('account', $data) && is_array($data['account'])) {

    $prepareDate = array();
    foreach($data['account'] as $key => $accountId) {
        if (empty($accountId)) {
            continue;
        }

        $prepareDate[] = array(
            'account_name' => '',
            'account_id'   => $accountId,
            'email_domain' => $data['domain'][$key],
        );
    }

    $installer->getConnection()->insertMultiple($tableName, $prepareDate);
}

$installer->endSetup();