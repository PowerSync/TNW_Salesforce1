<?php

class TNW_Salesforce_Model_Order_Creditmemo_Observer
{
    const OBJECT_TYPE = 'creditmemo';

    /**
     * @param Varien_Event_Observer $_observer
     * @return bool|void
     */
    public function saveAfter(Varien_Event_Observer $_observer)
    {
        /** @var Mage_Sales_Model_Order_Creditmemo $_creditmemo */
        $_creditmemo = $_observer->getEvent()->getCreditmemo();
        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace("TNW EVENT: Credit Memo #{$_creditmemo->getIncrementId()} Sync");

        $this->syncCreditMemo(array($_creditmemo->getId()));
    }

    /**
     * @param array $entityIds
     */
    public function syncCreditMemo(array $entityIds)
    {
        /** @var Varien_Db_Select $select */
        $select = TNW_Salesforce_Model_Localstorage::generateSelectForType('sales/order_creditmemo', $entityIds);

        $groupWebsite = array();
        foreach ($select->getAdapter()->fetchAll($select) as $row) {
            $groupWebsite[$row['website_id']][] = $row['object_id'];
        }

        foreach ($groupWebsite as $websiteId => $entityIds) {
            $this->syncCreditMemoForWebsite($entityIds, $websiteId);
        }
    }

    /**
     * @param array $entityIds
     * @param null $website
     */
    public function syncCreditMemoForWebsite(array $entityIds, $website = null)
    {
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($entityIds) {
            $website = Mage::app()->getWebsite();

            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError(sprintf('SKIPPING: API Integration is disabled in Website: %s', $website->getName()));

                return;
            }

            if (!Mage::helper('tnw_salesforce/config_sales_creditmemo')->syncCreditMemoForOrder()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('SKIPPING: Credit Memo synchronization disabled in Website: %s', $website->getName()));

                return;
            }

            if (Mage::getSingleton('core/session')->getFromSalesForce()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('INFO: Updating from Salesforce, skip synchronization to Salesforce.');

                return;
            }

            if (!$helper->canPush()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR: Salesforce connection could not be established, SKIPPING sync');

                return;
            }

            $syncBulk = (count($entityIds) > 1);

            try {
                if (count($entityIds) > $helper->getRealTimeSyncMaxCount() || !$helper->isRealTimeType()) {
                    $success = Mage::getModel('tnw_salesforce/localstorage')
                        ->addObject($entityIds, 'Creditmemo', 'creditmemo', $syncBulk);

                    if (!$success) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveError('Could not add to the queue!');
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
                    $_syncType = strtolower($helper->getCreditmemoObject());
                    Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                        'creditmemoIds' => $entityIds,
                        'message' => $helper->__('Total of %d records(s) were synchronized in Website: %s', count($entityIds), $website->getName()),
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