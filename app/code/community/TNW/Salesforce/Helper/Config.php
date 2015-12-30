<?php

/**
 * Class TNW_Salesforce_Helper_Config
 */
class TNW_Salesforce_Helper_Config extends TNW_Salesforce_Helper_Data
{
    // Global configuration
    const SALESFORCE_PREFIX_PROFESSIONAL = 'tnw_mage_basic__';
    const SALESFORCE_PREFIX_ENTERPRISE = 'tnw_mage_enterp__';

    /**
     * Get Salesforce managed package prefix
     * @param string $_type
     * @return mixed|null
     */
    public function getSalesforcePrefix($_type = 'professional') {

        $_constantName = 'self::SALESFORCE_PREFIX_' . strtoupper($_type);

        if (defined($_constantName)) {
            return constant($_constantName);
        }

        Mage::throwException('Salesforce prefix is undefined! Contact PowerSync for resolution.');

        return NULL;
    }

    /**
     * @return string
     */
    public function getMagentoIdField()
    {
        return $this->getSalesforcePrefix() . 'Magento_ID__c';
    }

    /**
     * @return string
     */
    public function getMagentoWebsiteField()
    {
        return $this->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();
    }

    /**
     * @return string
     */
    public function getDisableSyncField()
    {
        return $this->getSalesforcePrefix('enterprise') . 'disableMagentoSync__c';
    }

    /**
     * @param bool $reloadCache
     *
     * @return array
     */
    public function getSalesforceAccounts($reloadCache = false)
    {
        $accounts = array();
        $cache = Mage::app()->getCache();
        $cacheKey = 'tnw_salesforce_accounts';

        $cacheLoaded = false;
        if (!$reloadCache) {
            $cacheLoaded = $cache->load($cacheKey);
        }

        if ($cacheLoaded) {
            $accounts = (array)unserialize($cacheLoaded);
        } elseif (Mage::helper('tnw_salesforce')->isWorking()) {
            $client = Mage::getSingleton('tnw_salesforce/connection')->getClient();
            if ($client) {
                $helperName = 'tnw_salesforce/bulk_customer';

                $resetHelper = !Mage::registry('_helper/' . $helperName);

                $manualSync = Mage::helper($helperName);
                /**
                 * Call reset method if it's new instance only. Fix: PCMI-471
                 */
                if ($resetHelper) {
                    $manualSync->reset();
                }

                $manualSync->setSalesforceServerDomain(
                    Mage::getSingleton('core/session')->getSalesforceServerDomain());
                $manualSync->setSalesforceSessionId(
                    Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));
                $accounts = (array)$manualSync->getAllAccounts();

                if (Mage::app()->useCache('tnw_salesforce') && !empty($accounts)) {
                    $cache->save(serialize($accounts), $cacheKey, array('TNW_SALESFORCE', 'TNW_SALESFORCE_ACCOUNTS'));
                }
            }
        }

        return $accounts;
    }

    /**
     * find module configuration in database
     * @return array
     */
    public function getConfigDump($emulateTable = true)
    {

        /**
         * Get the resource model
         */
        $resource = Mage::getSingleton('core/resource');

        /**
         * Retrieve the read connection
         */
        $readConnection = $resource->getConnection('core_read');

        $query = 'SELECT * FROM ' . $resource->getTableName('core/config_data') . ' WHERE path like "%salesforce%" ';

        /**
         * Execute the query and store the results in $results
         */
        $results = $readConnection->fetchAll($query);

        if ($emulateTable) {
            $resultsStr = '';
            foreach ($results as $result) {
                if (empty($resultsStr)) {
                    $resultsStr .= "\t|" . implode('|', array_keys($result)) . "| \n";
                }
                $resultsStr .= "\t|" . implode('|', $result) . "| \n";
            }

            return $resultsStr;
        }

        return $results;
    }
}