<?php

class TNW_Salesforce_Model_Order_Creditmemo_Observer
{
    const OBJECT_TYPE = 'creditmemo';

    protected $deferredSyncCreditMemo;
    protected $refund = false;

    /**
     * @param Varien_Event_Observer $_observer
     * @return bool|void
     * @throws \Exception
     */
    public function saveAfter(Varien_Event_Observer $_observer)
    {
        /** @var Mage_Sales_Model_Order_Creditmemo $_creditmemo */
        $_creditmemo = $_observer->getEvent()->getCreditmemo();

        if ($this->refund) {
            $this->deferredSyncCreditMemo = $_creditmemo->getId();
            return;
        }

        Mage::getSingleton('tnw_salesforce/observer')
            ->setExportedOpportunity(array(
                'opportunity' => array(),
                'abandoned' => array(),
            ))
            ->setExportedOrders(array())
        ;

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace("TNW EVENT: Credit Memo #{$_creditmemo->getIncrementId()} Sync");

        $this->syncCreditMemo(array($_creditmemo->getId()));
    }

    /**
     * @param Varien_Event_Observer $_observer
     */
    public function refund(Varien_Event_Observer $_observer)
    {
        $this->refund = true;
    }

    /**
     * @param Varien_Event_Observer $_observer
     * @throws \Exception
     */
    public function saveOrder(Varien_Event_Observer $_observer)
    {
        if (empty($this->deferredSyncCreditMemo)) {
            return;
        }

        $this->syncCreditMemo(array($this->deferredSyncCreditMemo));
        $this->deferredSyncCreditMemo = null;
        $this->refund = false;
    }

    /**
     * @param array $entityIds
     * @throws Exception
     */
    public function syncCreditMemo(array $entityIds, $isManualSync = false)
    {
        $groupWebsite = array();
        foreach (array_chunk($entityIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT) as $_entityIds) {
            /** @var Varien_Db_Select $select */
            $select = Mage::getSingleton('tnw_salesforce/localstorage')
                ->generateSelectForType('sales/order_creditmemo', $_entityIds);

            foreach ($select->getAdapter()->fetchAll($select) as $row) {
                $groupWebsite[$row['website_id']][] = $row['object_id'];
            }
        }

        foreach ($groupWebsite as $websiteId => $entityIds) {
            $this->syncCreditMemoForWebsite($entityIds, $websiteId, $isManualSync);
        }
    }

    /**
     * @param array $entityIds
     * @param null $website
     * @throws Exception
     */
    public function syncCreditMemoForWebsite(array $entityIds, $website = null, $isManualSync = false)
    {
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($entityIds, $isManualSync) {
            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPPING: API Integration is disabled');

                return;
            }

            if (!$isManualSync && !Mage::helper('tnw_salesforce/config_sales_creditmemo')->autoSyncCreditMemo()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPPING: Credit Memo synchronization disabled');

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

            try {
                if (count($entityIds) > $helper->getRealTimeSyncMaxCount() || !$helper->isRealTimeType()) {
                    $syncBulk = (count($entityIds) > 1);

                    $success = Mage::getModel('tnw_salesforce/localstorage')
                        ->addObject($entityIds, 'Creditmemo', 'creditmemo', $syncBulk);

                    if (!$success) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveError('Could not add to the queue!');
                    } elseif ($syncBulk) {

                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Selected records were added into <a href="%s">synchronization queue</a> and will be processed in the background.', Mage::helper('adminhtml')->getUrl('*/salesforcesync_queue_to/bulk')));
                    } else {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Records are pending addition into the queue!'));
                    }
                } else {
                    Mage::dispatchEvent('tnw_salesforce_sync_creditmemo_for_website', array(
                        'entityIds' => $entityIds,
                        'syncType' => 'realtime'
                    ));
                }
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError($e->getMessage());
            }
        });
    }
}