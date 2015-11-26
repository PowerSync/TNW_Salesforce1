<?php
/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Kalashnikov
 */

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();
$tableName = $installer->getTable('tnw_salesforce/account_matching');

do {
    $conf = Mage::getStoreConfig('salesforce_customer/account_catchall/domains');
    if (empty($conf)) {
        break;
    }

    $data = @unserialize($conf);
    if (!is_array($data)) {
        break;
    }

    if (empty($data)) {
        break;
    }

    if (!(array_key_exists('account', $data) && is_array($data['account']))) {
        break;
    }

    /** @var TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection $collection */
    $collection = Mage::getModel('tnw_salesforce_api_entity/account')->getCollection();
    try {
        $toOptionHash = $collection->toOptionHashCustom();
    } catch(Exception $e) {
        $toOptionHash = array();
    }

    foreach($data['account'] as $key => $accountId) {
        if (empty($accountId)) {
            continue;
        }

        $accountName = '';
        if (isset($toOptionHash[$accountId])) {
            $accountName = $toOptionHash[$accountId];
        }

        try {
            $installer->getConnection()->insert($tableName, array(
                'account_name' => $accountName,
                'account_id'   => $accountId,
                'email_domain' => $data['domain'][$key],
            ));
        } catch(Exception $e) {}
    }

} while(false);

$installer->endSetup();