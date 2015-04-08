<?php

/**
 * Class TNW_Salesforce_Helper_Config
 */
class TNW_Salesforce_Helper_Config extends TNW_Salesforce_Helper_Data
{
    // Global configuration
    const SALESFORCE_PREFIX_PROFESSIONAL = 'tnw_mage_basic__';
    const SALESFORCE_PREFIX_ENTERPRISE = 'tnw_mage_enterp__';

//    const MYSQL_TIMEOUT = 300;

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
}