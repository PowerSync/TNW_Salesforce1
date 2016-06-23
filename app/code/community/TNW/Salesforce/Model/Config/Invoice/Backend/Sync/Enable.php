<?php

class TNW_Salesforce_Model_Config_Invoice_Backend_Sync_Enable extends Mage_Core_Model_Config_Data
{
    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'tnw_salesforce_config_invoice_enable';

    /**
     * Processing object before save data
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _beforeSave()
    {
        if (!$this->getValue()) {
            return parent::_beforeSave();
        }

        $_orderType = $this->getData('groups/customer_opportunity/fields/order_or_opportunity/value');
        if (TNW_Salesforce_Helper_Config_Sales::SYNC_TYPE_ORDER == strtolower($_orderType)
            && !$this->_checkInvoiceObject()
        ) {
            $this->setValue(0);
            return parent::_beforeSave();
        }

        return parent::_beforeSave();
    }

    /**
     * @return bool
     */
    protected function _checkInvoiceObject()
    {
        /** @var tnw_salesforce_model_connection $_connection */
        $_connection = Mage::getSingleton('tnw_salesforce/connection');
        if (!$_connection->initConnection()) {
            return false;
        }

        return $_connection->checkInvoicePackage();
    }
}