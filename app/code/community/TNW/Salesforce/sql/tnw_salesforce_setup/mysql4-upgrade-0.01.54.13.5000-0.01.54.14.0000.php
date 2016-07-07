<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$installer->getConnection()
    ->update($installer->getTable('tnw_salesforce/mapping'), array('sf_magento_enable' => '0', 'is_system' => '0'), implode(' AND ', array(
        $installer->getConnection()->prepareSqlCondition('local_field', array(array('like'=>'Shipping :%'), array('like'=>'Billing :%'))),
        $installer->getConnection()->prepareSqlCondition('sf_object', array('in' => array('OrderInvoice', 'OpportunityInvoice', 'OrderShipment', 'OpportunityShipment', 'OrderCreditMemo'))),
    )));

$installer->endSetup();