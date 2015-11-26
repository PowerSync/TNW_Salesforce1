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

$conf = Mage::getStoreConfig('salesforce_customer/account_catchall/domains');

Mage::helper('tnw_salesforce/config')->getSalesforceAccounts();

$installer->endSetup();