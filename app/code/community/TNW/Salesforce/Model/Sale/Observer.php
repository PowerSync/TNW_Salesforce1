<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Sale_Observer
{
    protected $orderObject = NULL;
    protected $orderHelper = NULL;

    /**
     * Shipment Sync Event
     * @param $observer
     * @deprecated not used anywhere
     */
    public function triggerSalesforceShippmentEvent($observer)
    {
        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR:: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }

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


    /* Shipment Sync */
    public function shipmentPush($observer)
    {
        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();

        if (!Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::helper('tnw_salesforce/salesforce_opportunity')->resetOrder($order->getId());
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR:: Salesforce connection could not be established, SKIPPING shipment sync');
            return; // Disabled
        }
        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledOrderSync()
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Order synchronization disabled');
            return; // Disabled
        }

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            // pass data to local storage

            // Extract all purchased products and add to local storage for sync
            $_productIds = array();
            foreach ($order->getAllVisibleItems() as $_item) {
                $_productIds[] = (int)Mage::helper('tnw_salesforce/salesforce_opportunity')->getProductIdFromCart($_item);
            }

            $res = Mage::getModel('tnw_salesforce/localstorage')->addObjectProduct($_productIds, 'Product', 'product');
            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR:: products from the order were not saved in local storage');
                return;
            }

            // TODO add level up abstract class with Order as static values, now we have word 'Order' as parameter
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array(intval($order->getData('entity_id'))), 'Order', 'order');
            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR:: order not saved to local storage');
            }
        } else {
            if ($order->getId() && $order->getSalesforceId()) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("###################################### Shipping Start ######################################");
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("----- Shipping itmes from Order #" . $order->getRealOrderId() . " -----");
                Mage::helper('tnw_salesforce/shipment')->salesforcePush($shipment, $order->getSalesforceId());
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("###################################### Shipping End ########################################");
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---- SKIPPING ORDER SHIPMENT. ERRORS FOUND. PLEASE REFER TO LOG FILE ----");
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
        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledOrderSync()
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Order synchronization disabled');
            return; // Disabled
        }

        /* My no longer be used, need to test
         * */
        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR:: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        if ($order->getData('status') == $order->getOrigData('status')) {
            return; // Disabled
        }

        if (!$order->getSalesforceId() && $order->getId() && $order->getStatus()) {
            // Never was synced, new order
            Mage::dispatchEvent('tnw_salesforce_order_save', array('order' => $order));
            return;
        }

        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            // pass data to local storage

            // TODO add level up abstract class with Order as static values, now we have word 'Order' as parameter
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array($order->getId()), 'Order', 'order');
            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR:: order status update not saved to local storage');
            }
        } else {
            $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("###################################### Order Status Update Start ######################################");
            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_status_update', $_syncType),
                array('order' => $order));
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("###################################### Order Status Update End ########################################");
        }
    }

    /**
     * order sync event
     *
     * @param $observer
     */
    public function triggerSalesforceEvent($observer)
    {
        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR:: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }
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

    /* Order Sync */
    public function salesforcePush($observer)
    {
        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }
        $order = $observer->getEvent()->getOrder();

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('TNW EVENT: Order #' . $order->getRealOrderId() . ' Sync');

        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledOrderSync()
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Order synchronization disabled');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR:: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            // pass data to local storage

            // Extract all purchased products and add to local storage for sync
            $_productIds = Mage::helper('tnw_salesforce/salesforce_order')->getProductIdsFromEntity($order);
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObjectProduct($_productIds, 'Product', 'product');
            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR:: products from the order were not saved in local storage');
                return;
            }

            // TODO add level up abstract class with Order as static values, now we have word 'Order' as parameter
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array(intval($order->getData('entity_id'))), 'Order', 'order');
            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR:: order not saved to local storage');
            }
            return;
        }

        $exportedOrders = Mage::getSingleton('tnw_salesforce/observer')->getExportedOrders();

        if (
            !Mage::getSingleton('core/session')->getFromSalesForce()
            && !in_array($order->getId(), $exportedOrders)
        ) {

            Mage::helper('tnw_salesforce/salesforce_opportunity')->resetOrder($order->getId());
        }

        $_order = Mage::getModel('sales/order')->load($order->getId());

        if ($order->getId() &&
            $_order->getStatus() // commented cause order status is <empty> or 'pending' and if <empty> then order was not synced with sf
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("############################ New Order Start ############################");
            $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());

            Mage::dispatchEvent(
                sprintf('tnw_salesforce_%s_process', $_syncType),
                array(
                    'orderIds'      => array($order->getId()),
                    'message'       => "SUCCESS: Upserting Order #" . $order->getRealOrderId(),
                    'type'   => 'salesforce'
                )
            );

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("############################ New Order End ############################");
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---- SKIPPING ORDER SYNC ----");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Order Status: " . $_order->getStatus());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Order Id: " . $order->getId());
            if (Mage::getSingleton('core/session')->getFromSalesForce()) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Transaction is from Salesforce!");
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("--------");
        }
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

    /**
     * Assign customer to product's campaign, send this data to SF
     * @param $observer
     */
    public function sendOrderItemCampaing($observer)
    {
        $mode = $observer->getEvent()->getMode();

        /**
         * use_product_campaign_assignment
         */
        if (Mage::helper('tnw_salesforce/config_sales')->useProductCampaignAssignment()) {
            if ($mode == 'bulk') {
                Mage::helper('tnw_salesforce/salesforce_newslettersubscriber')->updateCampaingsBulk();
            } else {
                Mage::helper('tnw_salesforce/salesforce_newslettersubscriber')->updateCampaings();
            }
        }
    }

    public function afterSalesRuleSave($observer)
    {
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('SKIPING: Synchronization disabled');

            return; // Disabled
        }

        /** @var Mage_SalesRule_Model_Rule $rule */
        $rule = $observer->getEvent()->getRule();
        if (!$rule->getId()) {
            return; // Disabled
        }

        try {
            if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {

                $res = Mage::getModel('tnw_salesforce/localstorage')
                    ->addObject(array($rule->getId()), 'Campaign_SalesRule', 'salesrule');

                if (!$res) {
                    Mage::getSingleton('adminhtml/session')->addError('Could not add catalog rule(s) to the queue!');
                }
                else if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(
                        $this->__('Records are pending addition into the queue!')
                    );
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
}
