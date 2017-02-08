<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Order_Invoice_Observer
{
    const OBJECT_TYPE = 'invoice';

    /**
     * @param $_observer Varien_Event_Observer
     */
    public function saveAfter($_observer)
    {
        /** @var Mage_Sales_Model_Order_Invoice $_invoice */
        $_invoice = $_observer->getEvent()->getInvoice();
        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace("TNW EVENT: Invoice #{$_invoice->getIncrementId()} Sync");

        $this->syncInvoice(array($_invoice->getId()));
    }

    /**
     * @param array $entityIds
     * @throws Exception
     */
    public function syncInvoice(array $entityIds)
    {
        $groupWebsite = array();
        foreach (array_chunk($entityIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT) as $_entityIds) {
            /** @var Varien_Db_Select $select */
            $select = Mage::getSingleton('tnw_salesforce/localstorage')
                ->generateSelectForType('sales/order_invoice', $_entityIds);

            foreach ($select->getAdapter()->fetchAll($select) as $row) {
                $groupWebsite[$row['website_id']][] = $row['object_id'];
            }
        }

        foreach ($groupWebsite as $websiteId => $entityIds) {
            $this->syncInvoiceForWebsite($entityIds, $websiteId);
        }
    }

    /**
     * @param array $entityIds
     * @param null $website
     * @throws Exception
     */
    public function syncInvoiceForWebsite(array $entityIds, $website = null)
    {
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($entityIds) {
            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('API Integration is disabled');

                return;
            }

            if (!Mage::helper('tnw_salesforce/config_sales_invoice')->syncInvoices()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Invoice Integration is disabled');

                return;
            }

            if (Mage::getSingleton('core/session')->getFromSalesForce()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('INFO: Updating from Salesforce, skip synchronization to Salesforce.');

                return;
            }

            if (!$helper->canPush()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR: Salesforce connection could not be established, SKIPPING order sync');

                return;
            }

            $syncBulk = (count($entityIds) > 1);

            try {
                if (count($entityIds) > $helper->getRealTimeSyncMaxCount() || !$helper->isRealTimeType()) {
                    $success = Mage::getModel('tnw_salesforce/localstorage')
                        ->addObject($entityIds, 'Invoice', 'invoice', $syncBulk);

                    if (!$success) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->addError('Could not add to the queue!');
                    } elseif ($syncBulk) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveNotice($helper->__('ISSUE: Too many records selected.'));

                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Selected records were added into <a href="%s">synchronization queue</a> and will be processed in the background.', Mage::helper('adminhtml')->getUrl('*/salesforcesync_queue_to/bulk')));
                    } else {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Records are pending addition into the queue!'));
                    }
                } else {
                    $_syncType = strtolower($helper->getInvoiceObject());
                    Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                        'invoiceIds' => $entityIds,
                        'message' => $helper->__('Total of %d invoice(s) were synchronized', count($entityIds)),
                        'type' => $syncBulk ? 'bulk' : 'salesforce'
                    ));
                }
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError($e->getMessage());
            }
        });
    }
}