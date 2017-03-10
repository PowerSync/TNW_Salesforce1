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
        if (
            $this->getValue()
            && TNW_Salesforce_Model_System_Config_Source_Order_Integration_Type::ORDER == $this->getData('groups/customer_opportunity/fields/integration_type/value')
            && !$this->_checkInvoiceObject()
        ) {
            $this->setValue(0);
        }

        return parent::_beforeSave();
    }

    /**
     * @return bool
     */
    protected function _checkInvoiceObject()
    {
        /** @var tnw_salesforce_model_connection $_connection */
        $_connection = TNW_Salesforce_Model_Connection::createConnection();
        if (!$_connection->initConnection()) {
            return false;
        }

        return $_connection->checkInvoicePackage();
    }
}