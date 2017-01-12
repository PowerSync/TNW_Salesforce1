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
     * @param $observer
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
     * @param $observer
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

        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_status_update', $_syncType), array('order' => $order));

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
        $select = TNW_Salesforce_Model_Localstorage::generateSelectForType('sales/order', $entityIds);

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
            $website = Mage::app()->getWebsite();

            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError(sprintf('SKIPPING: API Integration is disabled in Website: %s', $website->getName()));

                return;
            }

            if (!$helper->isEnabledOrderSync()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError(sprintf('SKIPPING: Order Integration is disabled in Website: %s', $website->getName()));

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
                        'message' => $helper->__('Total of %d order(s) were synchronized in Website: %s', count($entityIds), $website->getName()),
                        'type' => $syncBulk ? 'bulk' : 'salesforce'
                    ));
                }
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError($e->getMessage());
            }
        });
    }

    /* Order Cancel Event */
    public function orderCancelled($observer)
    {
        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        if (!Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::helper('tnw_salesforce/salesforce_opportunity')
                ->resetEntity($order->getId());
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('ERROR:: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }

        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledOrderSync()
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('SKIPING: Order synchronization disabled');
            return; // Disabled
        }

        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
        if (!$this->orderHelper) {
            $this->orderHelper = 'tnw_salesforce/salesforce_' . $_syncType;
        }

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            // pass data to local storage

            // Extract all purchased products and add to local storage for sync
            $_productIds = array();
            foreach (Mage::helper($this->orderHelper)->getItems($order) as $_item) {
                $_productIds[] = (int)Mage::helper($this->orderHelper)->getProductIdFromCart($_item);
            }

            $res = Mage::getModel('tnw_salesforce/localstorage')
                ->addObjectProduct($_productIds, 'Product', 'product');

            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR:: products from the order were not saved in local storage');
                return;
            }

            // Add order into the Queue
            $res = Mage::getModel('tnw_salesforce/localstorage')
                ->addObject(array(intval($order->getData('entity_id'))), 'Order', 'order');

            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR:: order cancellation not saved to local storage');
                return;
            }

            return;
        }

        if ($order->getId() && $order->getStatus()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("================ INVENTORY SYNC: START ================");
            $manualSync = Mage::helper('tnw_salesforce/salesforce_product');
            if ($manualSync->reset()) {
                $itemIds = array();
                foreach (Mage::helper($this->orderHelper)->getItems($order) as $_item) {
                    $itemIds[] = (int)Mage::helper($this->orderHelper)->getProductIdFromCart($_item);
                }

                if (!empty($itemIds) && $manualSync->massAdd($itemIds) && $manualSync->process()) {
                    Mage::getSingleton('adminhtml/session')
                        ->addSuccess(Mage::helper('adminhtml')->__('Product inventory was synchronized with Salesforce'));
                }
            }
            else {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Salesforce Connection failed!');
            }

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("================ INVENTORY SYNC: END ================");
        }
    }

    public function afterSalesRuleSave($observer)
    {
        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isOrderRulesEnabled()
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('SKIPING: Synchronization disabled');

            return; // Disabled
        }

        /** @var Mage_SalesRule_Model_Rule $rule */
        $rule = $observer->getEvent()->getRule();
        if (!$rule->getId()) {
            return; // Disabled
        }

        if (!empty($this->assignToCampaign) && ($rule->getData('salesforce_id') != $this->assignToCampaign)) {
            $obj = new stdClass();
            $obj->Id = $this->assignToCampaign;
            $obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . 'Magento_ID__c'} = 'sr_'.$rule->getId();

            // Assign Campaign
            try {
                TNW_Salesforce_Model_Connection::createConnection()->getClient()
                    ->update(array($obj), 'Campaign');

                $rule->setData('salesforce_id', $this->assignToCampaign);
                $rule->getResource()->save($rule);
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Assign Campaign: '.$e->getMessage());
                return; // Disabled
            }
        }

        try {
            if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {

                $res = Mage::getModel('tnw_salesforce/localstorage')
                    ->addObject(array($rule->getId()), 'Campaign_SalesRule', 'salesrule');

                if (!$res) {
                    Mage::getSingleton('adminhtml/session')->addError('Could not add catalog rule(s) to the queue!');
                }
                else if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                    Mage::getSingleton('adminhtml/session')->addSuccess('Records are pending addition into the queue!');
                }
            }
            else {
                $campaignMember = Mage::helper('tnw_salesforce/salesforce_campaign_salesrule');
                if ($campaignMember->reset() && $campaignMember->massAdd(array($rule->getId()))) {
                    $campaignMember->process();
                }
            }
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }
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
        if (!Mage::helper('tnw_salesforce')->isEnabled() || !Mage::helper('tnw_salesforce')->isEnabledOrderSync()) {
            return; // Disabled
        }

        $postOrder = Mage::app()->getRequest()->getPost('order');
        if (!$postOrder || empty($postOrder['owner_salesforce_id'])) {
            return;
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getData('order');
        $order->setData('owner_salesforce_id',  $postOrder['owner_salesforce_id']);
    }
}
