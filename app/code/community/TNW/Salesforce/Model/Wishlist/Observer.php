<?php

class TNW_Salesforce_Model_Wishlist_Observer
{
    /**
     * @param $observer Varien_Event_Observer
     * @throws Exception
     */
    public function productAddAfter($observer)
    {
        /** @var Mage_Wishlist_Model_Item[] $items */
        $items = $observer->getEvent()->getData('items');
        $wishlistIds = array_unique(array_map(function (Mage_Wishlist_Model_Item $item) {
            return $item->getWishlistId();
        }, $items));

        $this->syncWishlist($wishlistIds);
    }

    /**
     * @param array $entityIds
     * @throws Exception
     */
    public function syncWishlist(array $entityIds)
    {
        $groupWebsite = array();
        foreach (array_chunk($entityIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT) as $_entityIds) {
            /** @var Varien_Db_Select $select */
            $select = Mage::getSingleton('tnw_salesforce/localstorage')
                ->generateSelectForType('wishlist/wishlist', $_entityIds);

            foreach ($select->getAdapter()->fetchAll($select) as $row) {
                $groupWebsite[$row['website_id']][] = $row['object_id'];
            }
        }

        foreach ($groupWebsite as $websiteId => $_entityIds) {
            $this->syncWishlistForWebsite($_entityIds, $websiteId);
        }
    }

    /**
     * @param array $entityIds
     * @param null $website
     * @throws Exception
     */
    public function syncWishlistForWebsite(array $entityIds, $website = null)
    {
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($entityIds) {
            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveNotice('SKIPPING: API Integration is disabled');

                return;
            }

            if (!Mage::helper('tnw_salesforce/config_wishlist')->syncWishlist()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveNotice('SKIPPING: Wishlist Integration is disabled');

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
                if (!$helper->isRealTimeType() || count($entityIds) > $helper->getRealTimeSyncMaxCount()) {
                    $success = Mage::getModel('tnw_salesforce/localstorage')
                        ->addObject($entityIds, 'Wishlist', 'wishlist', $syncBulk);

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
                    /** @var TNW_Salesforce_Helper_Salesforce_Wishlist $manualSync */
                    $manualSync = Mage::helper(sprintf('tnw_salesforce/%s_wishlist', $syncBulk ? 'bulk' : 'salesforce'));
                    if ($manualSync->reset() && $manualSync->massAdd($entityIds) && $manualSync->process('full') && $successCount = $manualSync->countSuccessEntityUpsert()) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Total of %d record(s) were successfully synchronized', $successCount));
                    }
                }
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError($e->getMessage());
            }
        });
    }
}