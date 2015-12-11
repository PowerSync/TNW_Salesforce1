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

    $chunkData = array_chunk($data['account'], TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);
    foreach ($chunkData as $chunkItem) {
        /** @var TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection $collection */
        $collection = Mage::getModel('tnw_salesforce_api_entity/account')->getCollection();
        $collection->addFieldToFilter('Id', array('in' => $chunkItem));

        try {
            $toOptionHash = $collection->toOptionHashCustom();
        } catch(Exception $e) {
            $toOptionHash = array();
        }

        // Prepare Data
        $prepareDate = array();
        foreach($chunkItem as $key => $accountId) {
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

        try {
            $installer->getConnection()->insertMultiple($tableName, $prepareDate);
        } catch(Exception $e) {
            Mage::logException($e);
        }
    }
}

$installer->endSetup();