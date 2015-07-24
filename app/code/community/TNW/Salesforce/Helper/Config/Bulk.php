<?php

class TNW_Salesforce_Helper_Config_Bulk extends TNW_Salesforce_Helper_Config
{
    const BULK_PRODUCT_PATH = 'salesforce/development_and_debugging/bulk/product_batch_size';
    const BULK_CUSTOMER_PATH = 'salesforce/development_and_debugging/bulk/customer_batch_size';
    const BULK_WEBSITE_PATH = 'salesforce/development_and_debugging/bulk/website_batch_size';
    const BULK_ORDER_PATH = 'salesforce/development_and_debugging/bulk/order_batch_size';
    const BULK_ABANDONED_PATH = 'salesforce/development_and_debugging/bulk/abandoned_batch_size';
    const BULK_INVOICE_PATH = 'salesforce/development_and_debugging/bulk/invoice_batch_size';

    // Get Product batch size
    public function getProductBatchSize()
    {
        return $this->getStoreConfig(self::BULK_PRODUCT_PATH);
    }

    // Get Customer batch size
    public function getCustomerBatchSize()
    {
        return $this->getStoreConfig(self::BULK_CUSTOMER_PATH);
    }

    // Get Website batch size
    public function getWebsiteBatchSize()
    {
        return $this->getStoreConfig(self::BULK_WEBSITE_PATH);
    }

    // Get Order batch size
    public function getOrderBatchSize()
    {
        return $this->getStoreConfig(self::BULK_ORDER_PATH);
    }

    // Get Abandoned Carts batch size
    public function getAbandonedBatchSize()
    {
        return $this->getStoreConfig(self::BULK_ABANDONED_PATH);
    }

    // Get Invoice batch size
    public function getInvoiceBatchSize()
    {
        return $this->getStoreConfig(self::BULK_INVOICE_PATH);
    }
}