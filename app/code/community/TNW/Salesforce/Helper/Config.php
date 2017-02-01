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
     * @return Varien_Db_Select
     */
    public static function generateSelectWebsiteDifferent()
    {
        /** @var Mage_Core_Model_Resource_Config_Data $resource */
        $resource = Mage::getResourceModel('core/config_data');
        $adapter = $resource->getReadConnection();

        return $adapter->select()
            ->distinct()
            ->from($resource->getMainTable(), array('scope_id'))
            ->where($adapter->prepareSqlCondition('path', array('in'=>array(
                TNW_Salesforce_Helper_Data::API_ENABLED,
                TNW_Salesforce_Helper_Data::API_USERNAME,
                TNW_Salesforce_Helper_Data::API_PASSWORD,
                TNW_Salesforce_Helper_Data::API_TOKEN,
            ))))
            ->where($adapter->prepareSqlCondition('scope', 'websites'))
        ;
    }

    /**
     * @param string|int|Mage_Core_Model_Website $website
     * @return Mage_Core_Model_Website
     */
    public function getWebsite($website = null)
    {
        if ('' === $website) {
            $website = null;
        }

        return Mage::app()->getWebsite($website);
    }

    /**
     * @param null $website
     * @return Mage_Core_Model_Website|null
     */
    public function getWebsiteDifferentConfig($website = null)
    {
        $website = $this->getWebsite($website);
        $diffWebsites = $this->getWebsitesDifferentConfig(false);
        if (!isset($diffWebsites[$website->getId()])) {
            $website = $this->getWebsite('admin');
        }

        return $website;
    }

    /**
     * @param bool $withDefault
     * @return Mage_Core_Model_Website[]
     */
    public function getWebsitesDifferentConfig($withDefault = true)
    {
        static $tmpWebsites = null;
        if (is_null($tmpWebsites)) {
            $tmpWebsites = array();
            $select = self::generateSelectWebsiteDifferent();
            foreach ($select->getAdapter()->fetchCol($select) as $websiteId) {
                $tmpWebsites[$websiteId] = Mage::app()->getWebsite($websiteId);
            }
        }

        $addWebsite = array();
        if ($withDefault) {
            $website = $this->getWebsite('admin');
            $addWebsite[$website->getId()] = $website;
        }

        return $tmpWebsites + $addWebsite;
    }

    /**
     * @param $website
     * @return Varien_Object
     */
    public function startEmulationWebsiteDifferentConfig($website)
    {
        $website = $this->getWebsiteDifferentConfig($website);
        return $this->startEmulationWebsite($website);
    }

    /**
     * @param $website
     * @return Varien_Object
     */
    public function startEmulationWebsite($website)
    {
        $website = $this->getWebsite($website);
        if ($this->getWebsite()->getId() == $website->getId()) {
            return new Varien_Object();
        }

        /** @var Mage_Core_Model_App_Emulation $appEmulation */
        $appEmulation = Mage::getSingleton('core/app_emulation');
        return $appEmulation->startEnvironmentEmulation($website->getDefaultStore()->getId());
    }

    public function stopEmulationWebsite(Varien_Object $initialEnvironmentInfo)
    {
        if ($initialEnvironmentInfo->isEmpty()) {
            return;
        }

        /** @var Mage_Core_Model_App_Emulation $appEmulation */
        $appEmulation = Mage::getSingleton('core/app_emulation');
        $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
    }

    /**
     * @param $website
     * @param $callback
     * @return mixed
     * @throws Exception
     */
    public function wrapEmulationWebsiteDifferentConfig($website, $callback)
    {
        $website = $this->getWebsiteDifferentConfig($website);
        return $this->wrapEmulationWebsite($website, $callback);
    }

    /**
     * @param $website
     * @param $callback
     * @return mixed
     * @throws Exception
     */
    public function wrapEmulationWebsite($website, $callback)
    {
        $initialEnvironmentInfo = $this->startEmulationWebsite($website);

        try {
            $return = call_user_func($callback);
        } catch (Exception $e) {
            $this->stopEmulationWebsite($initialEnvironmentInfo);
            throw $e;
        }

        $this->stopEmulationWebsite($initialEnvironmentInfo);
        return $return;
    }
}