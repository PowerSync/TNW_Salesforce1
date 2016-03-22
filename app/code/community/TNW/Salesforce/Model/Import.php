<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Import extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/import');
    }

    /**
     * Get Object Type
     *
     * @return string
     */
    protected function getObjectType()
    {
        $object = $this->getObject();

        return isset($object->attributes) && isset($object->attributes->type)
            ? (string)$object->attributes->type : '';
    }

    /**
     * Get Object property
     *
     * @param string $key
     * @return mixed
     */
    protected function getObjectProperty($key)
    {
        return isset($this->getObject()->$key) ? $this->getObject()->$key : null;
    }

    /**
     * Get import processor
     *
     * @return bool|TNW_Salesforce_Helper_Magento_Abstract|TNW_Salesforce_Model_Import_Order
     */
    protected function getProcessor()
    {
        switch ($this->getObjectType()) {
            case 'Account':
                if ($this->getObjectProperty('IsPersonAccount') && $this->getObjectProperty('PersonEmail')) {
                    return Mage::helper('tnw_salesforce/magento_customers');
                }
                break;
            case 'Contact':
                if (($this->getObjectProperty('IsPersonAccount') && $this->getObjectProperty('PersonEmail'))
                    || $this->getObjectProperty('Email')
                ) {
                    return Mage::helper('tnw_salesforce/magento_customers');
                }
                break;
            case Mage::helper('tnw_salesforce/config')->getMagentoWebsiteField():
                return Mage::helper('tnw_salesforce/magento_websites');
            case 'Product2':
                return Mage::helper('tnw_salesforce/magento_products');
            case 'Order':
                return Mage::getModel('tnw_salesforce/import_order');
            case TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT:
                return Mage::helper('tnw_salesforce/magento_invoice');
            case TNW_Salesforce_Model_Config_Objects::ORDER_SHIPMENT_OBJECT:
                return Mage::helper('tnw_salesforce/magento_shipment');
        }

        return false;
    }

    /**
     * Process import
     */
    public function process()
    {
        $_association = array();
        $importProcessor = $this->getProcessor();
        if ($importProcessor) {
            Mage::getSingleton('core/session')->setFromSalesForce(true);
            if ($importProcessor instanceof TNW_Salesforce_Helper_Magento_Abstract) {
                $importProcessor->process($this->getObject());
                $_association = $importProcessor->getSalesforceAssociationAndClean();
            } else {
                $importProcessor->setObject($this->getObject())->process();
            }
            Mage::getSingleton('core/session')->setFromSalesForce(false);
        }

        return $_association;
    }

    public function sendMagentoIdToSalesforce($_association)
    {
        $importProcessor = $this->getProcessor();
        if ($importProcessor instanceof TNW_Salesforce_Helper_Magento_Abstract) {
            $importProcessor::sendMagentoIdToSalesforce($_association);
        }
    }
}
