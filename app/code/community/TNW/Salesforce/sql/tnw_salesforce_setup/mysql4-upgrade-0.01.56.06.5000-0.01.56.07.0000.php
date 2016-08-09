<?php
/**
 * @var $this TNW_Salesforce_Model_Mysql4_Setup
 */
$installer = $this;
$installer->startSetup();

$select = $installer->getConnection()->select()
    ->from($installer->getTable('core/config_data'), array('value'))
    ->where('path LIKE \'salesforce_customer/newsletter_config/customer_newsletter_enable_sync\'');

$isEnable = $installer->getConnection()->fetchOne($select);
$installer->getConnection()->update($installer->getTable('tnw_salesforce/mapping'), array('magento_sf_enable' => $isEnable, 'sf_magento_enable' => $isEnable), implode(' AND ', array(
    $installer->getConnection()->prepareSqlCondition('sf_field', array('eq'=>'HasOptedOutOfEmail')),
    $installer->getConnection()->prepareSqlCondition('sf_object', array('in' => array('Lead', 'Contact'))),
)));

$installer->endSetup();