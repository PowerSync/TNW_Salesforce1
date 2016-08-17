<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Magento_Websites extends TNW_Salesforce_Helper_Magento_Abstract
{
    /**
     * @var null
     */
    protected $_website = null;

    public function __construct()
    {
        parent::__construct();
        $this->_prepare();
    }

    /**
     * Accepts a single customer object and upserts a contact into the DB
     *
     * @param null $object
     * @return bool|false|Mage_Core_Model_Abstract
     */
    public function syncFromSalesforce($object = null)
    {
        $this->_prepare();

        //Lookup and preparation
        $_id = (int) $object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Website_ID__c'};
        try {
            set_time_limit(30);
            $_website = Mage::getModel('core/website')->load($_id);

            // Start fresh if new website
            if(
                !$_website
                || !is_object($_website)
                || !$_website->getData('code')
            ) {
                $_website = Mage::getModel('core/website');
            }

            if ($object->Name != $_website->getData('name')) {
                $_website->setData('name', $object->Name);
            }
            if ($object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Code__c'} != $_website->getData('code')) {
                $_website->setData('code', $object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Code__c'});
            }
            if ($object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Sort_Order__c'} != $_website->getData('sort_order')) {
                $_website->setData('sort_order', $object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Sort_Order__c'});
            }

            $_website->setData('salesforce_id', $object->Id);

            // Increase the timeout
            set_time_limit(120);

            $_flag = false;
            if (!Mage::getSingleton('core/session')->getFromSalesForce()) {
                Mage::getSingleton('core/session')->setFromSalesForce(true);
                $_flag = true;
            }
            // Save Product
            $_website->save();
            if ($_flag) {
                Mage::getSingleton('core/session')->setFromSalesForce(false);
            }
            // Reset timeout
            set_time_limit(30);

            return $_website;
        } catch (Exception $e) {
            $this->_addError('Error upserting website into Magento: ' . $e->getMessage(), 'MAGENTO_WEBSITE_UPSERT_FAILED');
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting website into Magento: " . $e->getMessage());
            unset($e);
            return false;
        }
    }

    /**
     * @param $_data
     * @return stdClass
     */
    protected static function _prepareEntityUpdate($_data)
    {
        $_obj = new stdClass();
        $_obj->Id = $_data['salesforce_id'];
        $_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . 'Website_ID__c'} = $_data['magento_id'];

        if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
            $_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_ENTERPRISE . 'disableMagentoSync__c'} = true;
        }

        return $_obj;
    }

    /**
     * @param $_object stdClass
     * @return string
     */
    protected function _getSfMagentoId($_object)
    {
        $magentoIsField = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . 'Website_ID__c';
        if (!property_exists($_object, $magentoIsField)) {
            return '';
        }

        return $_object->{$magentoIsField};
    }
}