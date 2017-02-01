<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Abandoned
{
    /**
     * @return Mage_Reports_Model_Resource_Quote_Collection
     */
    public function getAbandonedCollection($showSynchronized = false)
    {
        $collection = Mage::getResourceModel('reports/quote_collection');

        $dateLimit = Mage::helper('tnw_salesforce/config_sales_abandoned')->getDateLimit()->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);
        if ($showSynchronized) {
            $collection->addFieldToFilter(array('main_table.updated_at', 'main_table.salesforce_id'), array(array('lteq' => $dateLimit), array('notnull' => true)));
        } else {
            $collection->addFieldToFilter('main_table.updated_at', array('lteq' => $dateLimit));
        }

        $collection->addFieldToFilter('main_table.is_active', 1);
        $collection->addFieldToFilter('main_table.items_count', array('neq' => 0));
        $collection->addFieldToFilter('main_table.base_subtotal', array('neq' => 0.0000));

        return $collection;
    }

    /**
     * @param array $entityIds
     * @throws Exception
     */
    public function syncAbandoned(array $entityIds)
    {
        $groupWebsite = array();
        foreach (array_chunk($entityIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT) as $_entityIds) {
            /** @var Varien_Db_Select $select */
            $select = Mage::getSingleton('tnw_salesforce/localstorage')
                ->generateSelectForType('sales/quote', $_entityIds);

            foreach ($select->getAdapter()->fetchAll($select) as $row) {
                $groupWebsite[$row['website_id']][] = $row['object_id'];
            }
        }

        foreach ($groupWebsite as $websiteId => $entityIds) {
            $this->syncAbandonedForWebsite($entityIds, $websiteId);
        }
    }

    /**
     * @param array $entityIds
     * @param null $website
     * @throws Exception
     */
    public function syncAbandonedForWebsite(array $entityIds, $website = null)
    {
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($entityIds) {
            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveNotice('SKIPPING: API Integration is disabled');

                return;
            }

            if (!Mage::helper('tnw_salesforce/config_sales_abandoned')->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveNotice('SKIPPING: Abandoned Integration is disabled');

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
                    /** @var TNW_Salesforce_Model_Mysql4_Quote_Item_Collection $_collection */
                    $_collection = Mage::getResourceModel('tnw_salesforce/quote_item_collection')
                        ->addFieldToFilter('quote_id', array('in' => $entityIds));

                    $productIds = $_collection->walk('getProductId');

                    $success = Mage::getModel('tnw_salesforce/localstorage')
                        ->addObjectProduct(array_unique($productIds), 'Product', 'product', $syncBulk);

                    $success = $success && Mage::getModel('tnw_salesforce/localstorage')
                            ->addObject($entityIds, 'Abandoned', 'abandoned', $syncBulk);

                    if (!$success) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveError('Could not add to the queue!');
                    } elseif ($syncBulk) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveNotice($helper->__('ISSUE: Too many records selected.'));

                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Selected records were added into <a href="%s">synchronization queue</a> and will be processed in the background.', Mage::helper('adminhtml')->getUrl('*/salesforcesync_queue_to/bulk')));
                    }
                    else {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Records are pending addition into the queue!'));
                    }
                } else {
                    $_syncType = strtolower(Mage::helper('tnw_salesforce')->getAbandonedObject());
                    Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                        'orderIds' => $entityIds,
                        'message' => $helper->__('Total of %d abandoned(s) were synchronized', count($entityIds)),
                        'type' => $syncBulk ? 'bulk' : 'salesforce',
                        'object_type' => 'abandoned'
                    ));
                }
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError($e->getMessage());
            }
        });
    }
}