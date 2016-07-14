<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$installer
    ->getConnection()
    ->addIndex(
        $installer->getTable('tnw_salesforce/order_creditmemo_status'),
        $installer->getIdxName(
            'tnw_salesforce/order_creditmemo_status',
            array('magento_stage')
        ),
        array('magento_stage'),
        Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
    );

$installer
    ->getConnection()
    ->addIndex(
        $installer->getTable('tnw_salesforce/order_creditmemo_status'),
        $installer->getIdxName(
            'tnw_salesforce/order_creditmemo_status',
            array('salesforce_status')
        ),
        array('salesforce_status'),
        Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
    );

$installer->endSetup();