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

$conf = Mage::getStoreConfig('salesforce_customer/account_catchall/domains');
if (!empty($conf) && ($data = @unserialize($conf)) && is_array($data) && array_key_exists('account', $data) && is_array($data['account'])) {
    /** @var TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection $collection */
    $collection = Mage::getModel('tnw_salesforce_api_entity/account')->getCollection();
    try {
        $toOptionHash = $collection->toOptionHashCustom();
    } catch(Exception $e) {
        $toOptionHash = array();
    }

    // Prepare Data
    $prepareDate = array();
    foreach($data['account'] as $key => $accountId) {
        if (empty($accountId)) {
            continue;
        }

        $accountName = '';
        if (isset($toOptionHash[$accountId])) {
            $accountName = $toOptionHash[$accountId];
        }

        $prepareDate[] = array(
            'account_name' => $accountName,
            'account_id'   => $accountId,
            'email_domain' => $data['domain'][$key],
        );
    }

    // Save Data
    $chunkData = array_chunk($prepareDate, 200, true);
    foreach ($chunkData as $chunkItem) {
        try {
            $installer->getConnection()->insertMultiple($tableName, $chunkItem);
        } catch(Exception $e) {
            Mage::logException($e);
        }
    }
}

$installer->endSetup();