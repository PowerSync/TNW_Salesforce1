<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Config_Sales_Invoice extends TNW_Salesforce_Helper_Config_Sales
{
    const INVOICE_SYNC_ENABLE = 'salesforce_invoice/invoice_configuration/sync_enable';
    const INVOICE_NOTES_SYNC  = 'salesforce_invoice/invoice_configuration/notes_synchronize';

    /**
     * @return bool
     * @deprecated
     */
    public function syncInvoices()
    {
        return $this->autoSyncInvoices();
    }

    /**
     * @return bool
     */
    public function autoSyncInvoices()
    {
        return (bool)$this->getStoreConfig(self::INVOICE_SYNC_ENABLE);
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
        /** if Order & Opportunity enabled - OrderInvoice will be sync-ed through the OpportunityInvoice process in fact */
        return $this->isProfessionalEdition()
            && Mage::helper('tnw_salesforce/config_sales')->integrationOnlyOrderAllowed();
    }

    /**
     * @return bool
     */
    public function syncInvoicesForOpportunity()
    {
        return $this->isProfessionalEdition()
            && Mage::helper('tnw_salesforce/config_sales')->integrationOpportunityAllowed();
    }
}