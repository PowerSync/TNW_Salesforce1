<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Config_Sales_Invoice extends TNW_Salesforce_Helper_Config_Sales
{
    const INVOICE_SYNC_ENABLE = 'salesforce_order/invoice_configuration/invoice_sync_enable';

    // Allow Magento to synchronize invoices with Salesforce
    public function syncInvoices()
    {
        return (int)$this->getStoreConfig(self::INVOICE_SYNC_ENABLE);
    }

    /**
     * @return bool
     */
    public function syncInvoicesForOrder()
    {
        return $this->syncInvoices()
            && self::SYNC_TYPE_ORDER == strtolower($this->_helper()->getOrderObject());
    }
}