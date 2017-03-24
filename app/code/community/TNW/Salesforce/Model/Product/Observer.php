<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Product_Observer
{
    public function __construct()
    {
    }

    /**
     * @param $observer
     */
    public function salesforceTriggerEvent($observer)
    {
       $_product = $observer->getEvent()->getProduct();

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('MAGENTO EVENT: Product #' . $_product->getId() . ' Sync');

        Mage::dispatchEvent('tnw_salesforce_product_save', array('product' => $_product));

        return;
    }

    /**
     * @param $observer Varien_Event_Observer
     */
    public function salesforcePush($observer)
    {
        /** @var Mage_Catalog_Model_Product $_product */
        $_product = $observer->getEvent()->getProduct();
        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace("TNW EVENT: Product #{$_product->getId()} Sync");

        if ($_product->getIsDuplicate()) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('SKIPING: Product duplicate process');

            return;
        }

        $this->syncProduct(array($_product->getId()));
    }

    /**
     * @param array $entityIds
     * @param bool $isManualSync
     * @throws Exception
     */
    public function syncProduct(array $entityIds, $isManualSync = false)
    {
        $groupWebsite = array();
        foreach (array_chunk($entityIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT) as $_entityIds) {
            /** @var Varien_Db_Select $select */
            $select = Mage::getSingleton('tnw_salesforce/localstorage')
                ->generateSelectForType('catalog/product', $_entityIds);

            foreach ($select->getAdapter()->fetchAll($select) as $row) {
                $groupWebsite[$row['website_id']][] = $row['object_id'];
            }
        }

        foreach ($groupWebsite as $websiteId => $_entityIds) {
            $this->syncProductForWebsite($_entityIds, $websiteId, $isManualSync);
        }
    }

    /**
     * @param array $entityIds
     * @param null $website
     * @param bool $isManualSync
     * @throws Exception
     */
    public function syncProductForWebsite(array $entityIds, $website = null, $isManualSync = false)
    {
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($entityIds, $isManualSync) {
            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPING: API Integration is disabled');

                return;
            }

            if (!$isManualSync && !$helper->isEnabledProductSync()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPING: Product Integration is disabled');

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
                if (!$helper->isRealTimeType() || count($entityIds) > $helper->getRealTimeSyncMaxCount()) {
                    $syncBulk = (count($entityIds) > 1);

                    $success = Mage::getModel('tnw_salesforce/localstorage')
                        ->addObjectProduct($entityIds, 'Product', 'product', $syncBulk);

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
                    /** @var TNW_Salesforce_Helper_Salesforce_Product $manualSync */
                    $manualSync = Mage::helper('tnw_salesforce/salesforce_product');
                    if ($manualSync->reset() && $manualSync->massAdd($entityIds) && $manualSync->process()) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Total of %d product(s) were successfully synchronized', count($entityIds)));
                    }
                }
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError($e->getMessage());
            }
        });
    }

    public function beforeImport()
    {
        Mage::getSingleton('core/session')->setFromSalesForce(true);
    }

    public function afterImport()
    {
        Mage::getSingleton('core/session')->setFromSalesForce(false);
    }
}