<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Config extends TNW_Salesforce_Helper_Data
{
    // Global configuration
    const SALESFORCE_PREFIX_PROFESSIONAL = 'tnw_mage_basic__';
    const SALESFORCE_PREFIX_ENTERPRISE = 'tnw_mage_enterp__';
    const SALESFORCE_PREFIX_FULFILMENT = 'tnw_fulfilment__';

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