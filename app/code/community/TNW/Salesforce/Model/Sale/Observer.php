<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Sale_Observer
{
    protected $orderObject      = null;
    protected $orderHelper      = null;
    protected $assignToCampaign = null;

    /**
     * Shipment Sync Event
     * @param $observer
     * @deprecated not used anywhere
     */
    public function triggerSalesforceShippmentEvent($observer)
    {
        // Triggers TNW event that pushes to SF
        $shipment = $observer->getEvent()->getShipment();
        Mage::dispatchEvent('tnw_salesforce_order_shipment_save', array('shipment' => $shipment));
    }

    /**
     * @comment try to find related opportunity for order
     * @param $observer Varien_Event_Observer
     */
    public function addOpportunity($observer)
    {
        /**
         * @var $order Mage_Sales_Model_Order
         */
        $order = $observer->getEvent()->getOrder();

        if ($quoteId = $order->getQuoteId()) {

            $quote = Mage::getModel('sales/quote')->load($quoteId);

            if ($quote->getSalesforceId()) {

                $update = array(
                    'opportunity_id' => $quote->getSalesforceId()
                );

                $where = array(
                    'entity_id = ?' => $order->getId()
                );

                Mage::getSingleton('core/resource')->getConnection('core_write')
                    ->update(
                        Mage::helper('tnw_salesforce')->getTable('sales/order'),
                        $update,
                        $where
                    );

                $order->setOpportunityId($quote->getSalesforceId());
            }
        }
    }

    /**
     * order sync event
     *
     * @param $observer Varien_Event_Observer
     * @throws Exception
     */
    public function orderStatusUpdateTrigger($observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();

        /**
         * is it order address save event
         */
        $address = $observer->getEvent()->getAddress();
        if ($address instanceof Mage_Sales_Model_Order_Address) {
            $order = $address->getOrder();
        }

        if (!$address && $order->getData('status') == $order->getOrigData('status')) {
            return; // Disabled
        }

        if (!$order->getSalesforceId() && $order->getId() && $order->getStatus()) {
            // Never was synced, new order
            Mage::dispatchEvent('tnw_salesforce_order_save', array('order' => $order));
            return;
        }

        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($order->getStore()->getWebsite(), function () use($order) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('###################################### Order Status Update Start ######################################');

            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPPING: API Integration is disabled');

                return;
            }

            if (!$helper->isEnabledOrderSync()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPPING: Order Integration is disabled');

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

            if (!$helper->isRealTimeType()) {
                $success = Mage::getModel('tnw_salesforce/localstorage')
                    ->addObject(array($order->getId()), 'Order', 'order');

                if (!$success) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError('Could not add to the queue!');
                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveSuccess($helper->__('Records are pending addition into the queue!'));
                }
            } else {
                Mage::dispatchEvent('tnw_salesforce_sync_order_status_for_website', array('order' => $order));
            }

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('###################################### Order Status Update End ########################################');
        });
    }

    /**
     * order sync event
     *
     * @param $observer
     */
    public function triggerSalesforceEvent($observer)
    {
        // Triggers TNW event that pushes to SF
        $order = $observer->getEvent()->getOrder();
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('MAGENTO EVENT: Order #' . $order->getRealOrderId() . ' Sync');

        // Check if AITOC is installed
        $modules = Mage::getConfig()->getNode('modules')->children();
        // Only dispatch event if AITOC is not installed, otherwise we use different event
        if (!property_exists($modules, 'Aitoc_Aitcheckoutfields')) {
            Mage::dispatchEvent('tnw_salesforce_order_save', array('order' => $order));
        }
    }

    /**
     * order sync event for aitoc
     *
     * @param $observer
     */
    public function triggerAitocSalesforceEvent($observer)
    {
        // Triggers TNW event that pushes to SF
        $order = $observer->getEvent()->getOrder();
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('AITOC EVENT: Order #' . $order->getRealOrderId() . ' Sync');

        Mage::dispatchEvent('tnw_salesforce_order_save', array('order' => $order));
    }

    /**
     * Order Sync
     * @param $observer Varien_Event_Observer
     * @throws Exception
     */
    public function salesforcePush($observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace("TNW EVENT: Order #{$order->getRealOrderId()} Sync");

        $this->syncOrder(array($order->getId()));
    }

    /**
     * @param array $entityIds
     * @param bool $isManualSync
     * @throws Exception
     */
    public function syncOrder(array $entityIds, $isManualSync = false)
    {
        $groupWebsite = array();
        foreach (array_chunk($entityIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT) as $_entityIds) {
            /** @var Varien_Db_Select $select */
            $select = Mage::getSingleton('tnw_salesforce/localstorage')
                ->generateSelectForType('sales/order', $_entityIds);

            foreach ($select->getAdapter()->fetchAll($select) as $row) {
                $groupWebsite[$row['website_id']][] = $row['object_id'];
            }
        }

        foreach ($groupWebsite as $websiteId => $_entityIds) {
            $this->syncOrderForWebsite($_entityIds, $websiteId, $isManualSync);
        }
    }

    /**
     * @param array $entityIds
     * @param null $website
     * @param bool $isManualSync
     * @throws Exception
     */
    public function syncOrderForWebsite(array $entityIds, $website = null, $isManualSync = false)
    {
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($entityIds, $isManualSync) {
            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPPING: API Integration is disabled');

                return;
            }

            if (!$isManualSync && !$helper->isEnabledOrderSync()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPPING: Order Integration is disabled');

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
                    $syncBulk = count($entityIds) > 1;

                    $_collection = Mage::getResourceModel('sales/order_item_collection')
                        ->addFieldToFilter('order_id', array('in' => $entityIds));

                    // use Mage::helper('tnw_salesforce/salesforce_order')->getProductIdFromCart(
                    $productIds = $_collection->walk(array(
                        Mage::helper('tnw_salesforce/salesforce_order'), 'getProductIdFromCart'
                    ));

                    $success = Mage::getModel('tnw_salesforce/localstorage')
                        ->addObjectProduct(array_unique($productIds), 'Product', 'product', $syncBulk);

                    $success = $success && Mage::getModel('tnw_salesforce/localstorage')
                            ->addObject($entityIds, 'Order', 'order', $syncBulk);

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
                    Mage::dispatchEvent('tnw_salesforce_sync_order_for_website', array(
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

    /**
     * Order Cancel Event
     * @param $observer Varien_Event_Observer
     * @throws Exception
     * @deprecated
     */
    public function orderCancelled($observer)
    {
        //TODO: BUG method!!! Deleted???

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ INVENTORY SYNC: START ================");

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($order->getStore()->getWebsite(), function () use($order) {
            $_productIds = array();
            /** @var Mage_Sales_Model_Order_Item $_item */
            foreach ($order->getAllItems() as $_item) {
                $_productIds[] = (int)$_item->getProductId();
            }

            Mage::getSingleton('tnw_salesforce/product_observer')->syncProductForWebsite($_productIds);
        });

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ INVENTORY SYNC: END ================");
    }

    /**
     * @param $observer Varien_Event_Observer
     */
    public function afterSalesRuleSave($observer)
    {
        /** @var Mage_SalesRule_Model_Rule $rule */
        $rule = $observer->getEvent()->getRule();
        if (!$rule->getId()) {
            return; // Disabled
        }

        $website = Mage::getSingleton('tnw_salesforce/localstorage')
            ->getWebsiteIdForType('salesrule/rule', $rule->getId());

        if (is_null($website)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('Unable to determine the entry Website');

            return;
        }

        $assignToCampaign = $this->assignToCampaign;
        if (!empty($assignToCampaign) && ($rule->getData('salesforce_id') != $assignToCampaign)) {
            try {
                Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($assignToCampaign, $rule) {
                    /** @var TNW_Salesforce_Helper_Data $helper */
                    $helper = Mage::helper('tnw_salesforce');

                    if (!$helper->isEnabled()) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveTrace('SKIPPING: API Integration is disabled');

                        return;
                    }

                    if (!$helper->isOrderRulesEnabled()) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveTrace('SKIPPING: Sales Rule Integration is disabled');

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

                    $obj = new stdClass();
                    $obj->Id = $assignToCampaign;
                    $obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . 'Magento_ID__c'} = 'sr_'.$rule->getId();

                    // Assign Campaign
                    TNW_Salesforce_Model_Connection::createConnection()->getClient()
                        ->update(array($obj), 'Campaign');

                    $rule->setData('salesforce_id', $assignToCampaign);
                    $rule->getResource()->save($rule);
                });
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Assign Campaign: '.$e->getMessage());
                return;
            }
        }

        $this->syncSalesRuleForWebsite(array($rule->getId()), $website);
    }

    /**
     * @param array $entityIds
     * @throws Exception
     */
    public function syncSalesRule(array $entityIds)
    {
        $groupWebsite = array();
        foreach (array_chunk($entityIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT) as $_entityIds) {
            /** @var Varien_Db_Select $select */
            $select = Mage::getSingleton('tnw_salesforce/localstorage')
                ->generateSelectForType('salesrule/rule', $_entityIds);

            foreach ($select->getAdapter()->fetchAll($select) as $row) {
                $groupWebsite[$row['website_id']][] = $row['object_id'];
            }
        }

        foreach ($groupWebsite as $websiteId => $entityIds) {
            $this->syncSalesRuleForWebsite($entityIds, $websiteId);
        }
    }

    /**
     * @param array $entityIds
     * @param null $website
     * @throws Exception
     */
    public function syncSalesRuleForWebsite(array $entityIds, $website = null)
    {
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($entityIds) {
            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPPING: API Integration is disabled');

                return;
            }

            if (!$helper->isOrderRulesEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPPING: Sales Rule Integration is disabled');

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
                        ->addObject($entityIds, 'Campaign_SalesRule', 'salesrule', $syncBulk);

                    if (!$success) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveError('Could not add catalog rule(s) to the queue!');
                    } elseif ($syncBulk) {

                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Selected records were added into <a href="%s">synchronization queue</a> and will be processed in the background.', Mage::helper('adminhtml')->getUrl('*/salesforcesync_queue_to/bulk')));
                    } else {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Records are pending addition into the queue!'));
                    }
                }
                else {
                    /** @var TNW_Salesforce_Helper_Salesforce_Campaign_Salesrule $campaignMember */
                    $campaignMember = Mage::helper('tnw_salesforce/salesforce_campaign_salesrule');
                    if ($campaignMember->reset() && $campaignMember->massAdd($entityIds) && $campaignMember->process()) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Total of %d record(s) were successfully synchronized', count($entityIds)));
                    }
                }
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError($e->getMessage());
            }
        });
    }

    public function controllerSalesRulePrepareSave($observer)
    {
        /** @var Mage_Core_Controller_Request_Http $request */
        $request = $observer->getEvent()->getRequest();
        $this->assignToCampaign = $request->getParam('assign_to_campaign');
    }

    /**
     * @param $observer
     */
    public function quoteSubmitBefore($observer)
    {
        $postOrder = Mage::app()->getRequest()->getPost('order');
        if (empty($postOrder['owner_salesforce_id'])) {
            return;
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getData('order');
        $order->setData('owner_salesforce_id',  $postOrder['owner_salesforce_id']);
    }
}
