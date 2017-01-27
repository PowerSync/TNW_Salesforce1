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
     */
    public function orderStatusUpdateTrigger($observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        /**
         * is it order address save event
         */
        if ($address = $observer->getEvent()->getAddress()) {
            if ($address instanceof Mage_Sales_Model_Order_Address) {
                $order = $address->getOrder();
            }
        }

        if (!$address && $order->getData('status') == $order->getOrigData('status')) {
            return; // Disabled
        }

        if (!$order->getSalesforceId() && $order->getId() && $order->getStatus()) {
            // Never was synced, new order
            Mage::dispatchEvent('tnw_salesforce_order_save', array('order' => $order));
            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace("###################################### Order Status Update Start ######################################");

        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($order->getStore()->getWebsite(), function () use($order) {
            $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_status_update', $_syncType), array('order' => $order));
        });

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace("###################################### Order Status Update End ########################################");
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
     */
    public function syncOrder(array $entityIds)
    {
        /** @var Varien_Db_Select $select */
        $select = Mage::getSingleton('tnw_salesforce/localstorage')
            ->generateSelectForType('sales/order', $entityIds);

        $groupWebsite = array();
        foreach ($select->getAdapter()->fetchAll($select) as $row) {
            $groupWebsite[$row['website_id']][] = $row['object_id'];
        }

        foreach ($groupWebsite as $websiteId => $entityIds) {
            $this->syncOrderForWebsite($entityIds, $websiteId);
        }
    }

    /**
     * @param array $entityIds
     * @param null $website
     */
    public function syncOrderForWebsite(array $entityIds, $website = null)
    {
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($entityIds) {
            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('SKIPPING: API Integration is disabled');

                return;
            }

            if (!$helper->isEnabledOrderSync()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('SKIPPING: Order Integration is disabled');

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
                $syncBulk = count($entityIds) > 1;

                if (count($entityIds) > $helper->getRealTimeSyncMaxCount() || !$helper->isRealTimeType()) {
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
                            ->saveNotice($helper->__('ISSUE: Too many records selected.'));

                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Selected records were added into <a href="%s">synchronization queue</a> and will be processed in the background.', Mage::helper('adminhtml')->getUrl('*/salesforcesync_queue_to/bulk')));
                    } else {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Records are pending addition into the queue!'));
                    }
                } else {
                    $_syncType = strtolower($helper->getOrderObject());
                    Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                        'orderIds' => $entityIds,
                        'message' => $helper->__('Total of %d order(s) were synchronized', count($entityIds)),
                        'type' => $syncBulk ? 'bulk' : 'salesforce'
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
     */
    public function orderCancelled($observer)
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ INVENTORY SYNC: START ================");

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        $orderHelperName = $this->orderHelper;
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($order->getStore()->getWebsite(), function () use($order, $orderHelperName) {
            $orderHelperName = $orderHelperName ?: sprintf('tnw_salesforce/salesforce_%s', strtolower(Mage::helper('tnw_salesforce')->getOrderObject()));

            /** @var TNW_Salesforce_Helper_Salesforce_Abstract_Order $orderHelper */
            $orderHelper = Mage::helper($orderHelperName);

            // Extract all purchased products and add to local storage for sync
            $_productIds = array();
            foreach ($orderHelper->getItems($order) as $_item) {
                $_productIds[] = (int)$orderHelper->getProductIdFromCart($_item);
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
                            ->saveError('SKIPPING: API Integration is disabled');

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
     */
    public function syncSalesRule(array $entityIds)
    {

        /** @var Varien_Db_Select $select */
        $select = Mage::getSingleton('tnw_salesforce/localstorage')
            ->generateSelectForType('salesrule/rule', $entityIds);

        $groupWebsite = array();
        foreach ($select->getAdapter()->fetchAll($select) as $row) {
            $groupWebsite[$row['website_id']][] = $row['object_id'];
        }

        foreach ($groupWebsite as $websiteId => $entityIds) {
            $this->syncSalesRuleForWebsite($entityIds, $websiteId);
        }
    }

    /**
     * @param array $entityIds
     * @param null $website
     */
    public function syncSalesRuleForWebsite(array $entityIds, $website = null)
    {
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($entityIds) {
            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('SKIPPING: API Integration is disabled');

                return;
            }

            if (!$helper->isOrderRulesEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('SKIPPING: Sales Rule Integration is disabled');

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
                        ->addObject($entityIds, 'Campaign_SalesRule', 'salesrule', $syncBulk);

                    if (!$success) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveError('Could not add catalog rule(s) to the queue!');
                    } elseif ($syncBulk) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveNotice($helper->__('ISSUE: Too many records selected.'));

                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Selected records were added into <a href="%s">synchronization queue</a> and will be processed in the background.', Mage::helper('adminhtml')->getUrl('*/salesforcesync_queue_to/bulk')));
                    } else {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Records are pending addition into the queue!'));
                    }
                }
                else {
                    /** @var TNW_Salesforce_Helper_Salesforce_Campaign_Salesrule $campaignMember */
                    $campaignMember = Mage::helper(sprintf('tnw_salesforce/%s_campaign_salesrule', $syncBulk ? 'bulk' : 'salesforce'));
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
