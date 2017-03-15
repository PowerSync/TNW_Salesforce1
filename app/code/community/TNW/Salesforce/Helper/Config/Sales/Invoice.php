<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Config_Sales_Invoice extends TNW_Salesforce_Helper_Config_Sales
{
    const INVOICE_SYNC_ENABLE = 'salesforce_invoice/invoice_configuration/sync_enable';
    const INVOICE_NOTES_SYNC  = 'salesforce_invoice/invoice_configuration/notes_synchronize';

    // Allow Magento to synchronize invoices with Salesforce
    public function syncInvoices()
    {
        return (int)$this->getStoreConfig(self::INVOICE_SYNC_ENABLE);
    }

    /**
     * @return int
     */
    public function syncInvoiceNotes()
    {
        return (int)$this->getStoreConfig(self::INVOICE_NOTES_SYNC);
    }

    /**
     * @return bool
     */
    public function syncInvoicesForOrder()
    {
        return $this->syncInvoices()
            && Mage::helper('tnw_salesforce')->integrationOrderAllowed();
    }

    /**
     * @return bool
     */
    public function syncInvoicesForOpportunity()
    {
        return $this->syncInvoices()
            && Mage::helper('tnw_salesforce')->integrationOpportunityAllowed();
    }
}