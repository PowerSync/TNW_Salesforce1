<?php

class TNW_Salesforce_Helper_Config_Sales_Invoice extends TNW_Salesforce_Helper_Config_Sales
{
    const INVOICE_SYNC_ENABLE = 'salesforce_order/invoice_configuration/invoice_sync_enable';

    // Allow Magento to synchronize invoices with Salesforce
    public function syncInvoices()
    {
        return $this->getStroreConfig(self::INVOICE_SYNC_ENABLE);
    }
}