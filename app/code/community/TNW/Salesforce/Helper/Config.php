<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Config extends TNW_Salesforce_Helper_Data
{
    // Global configuration
    const SALESFORCE_PREFIX_PROFESSIONAL = 'tnw_mage_basic__';
    const SALESFORCE_PREFIX_ENTERPRISE   = 'tnw_mage_enterp__';
    const SALESFORCE_PREFIX_SHIPMENT     = 'tnw_shipment__';
    const SALESFORCE_PREFIX_INVOICE      = 'tnw_invoice__';

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

    /**
     * @param bool $withDefault
     * @return Mage_Core_Model_Website[]
     */
    public function getWebsiteDifferentConfig($withDefault = true)
    {
        static $tmpWebsites = array();
        if (!count($tmpWebsites)) {
            $paths = array(
                'salesforce/api_config/api_enable',
                'salesforce/api_config/api_username',
                'salesforce/api_config/api_password',
                'salesforce/api_config/api_token',
            );

            /** @var Mage_Core_Model_Resource_Config_Data $resource */
            $resource = Mage::getResourceModel('core/config_data');
            $adapter = $resource->getReadConnection();

            $selectWhere = $adapter->select()
                ->from($resource->getMainTable(), array('path'))
                ->where($adapter->prepareSqlCondition('path', array('in'=>$paths)))
                ->group(array('path'))
                ->having('count(*) > 1')
            ;

            $select = $adapter->select()
                ->from($resource->getMainTable(), array('scope_id', 'scope'))
                ->where($adapter->prepareSqlCondition('path', array('in'=>$selectWhere)))
            ;

            foreach ($adapter->fetchAll($select) as $row) {
                switch ($row['scope']) {
                    case 'websites':
                        $website = Mage::app()->getWebsite($row['scope_id']);
                        break;

                    case 'stores':
                        $website = Mage::app()->getStore($row['scope_id'])->getWebsite();
                        break;

                    default:
                        continue 2;
                }

                $tmpWebsites[$website->getId()] = $website;
            }
        }

        $addWebsite = array();
        if ($withDefault) {
            $website = Mage::app()->getWebsite('admin');
            $addWebsite[$website->getId()] = $website;
        }

        return array_merge($tmpWebsites, $addWebsite);
    }
}