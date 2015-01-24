<?php

class TNW_Salesforce_Model_Sale_Observer
{
    public function __construct()
    {

    }

    /* Shipment Sync Event */
    public function triggerSalesforceShippmentEvent($observer)
    {
        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::helper("tnw_salesforce")->log('ERROR: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }
        // Triggers TNW event that pushes to SF
        $shipment = $observer->getEvent()->getShipment();
        Mage::dispatchEvent('tnw_sales_order_shipment_save', array('shipment' => $shipment));
    }

    /* Shipment Sync */
    public function shipmentPush($observer)
    {
        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::helper("tnw_salesforce")->log('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();

        if (!Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::helper('tnw_salesforce/salesforce_opportunity')->resetOrder($order->getId());
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::helper("tnw_salesforce")->log('ERROR: Salesforce connection could not be established, SKIPPING shipment sync');
            return; // Disabled
        }
        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledOrderSync()
        ) {
            Mage::helper("tnw_salesforce")->log('SKIPING: Order synchronization disabled');
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

            //$res = Mage::getModel('tnw_salesforce/localstorage')->addObject($_productIds, 'Product', 'product');
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObjectProduct($_productIds, 'Product', 'product');
            if (!$res) {
                Mage::helper("tnw_salesforce")->log('error: products from the order were not saved in local storage');
                return false;
            }

            // TODO add level up abstract class with Order as static values, now we have word 'Order' as parameter
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array(intval($order->getData('entity_id'))), 'Order', 'order');
            if (!$res) {
                Mage::helper("tnw_salesforce")->log('error: order not saved to local storage');
                return false;
            }
            return true;
        }

        if (
            $order->getId() &&
            $order->getSalesforceId()
        ) {
            Mage::helper('tnw_salesforce')->log("###################################### Shipping Start ######################################");
            Mage::helper('tnw_salesforce')->log("----- Shipping itmes from Order #" . $order->getRealOrderId() . " -----");
            Mage::helper('tnw_salesforce/shipment')->salesforcePush($shipment, $order->getSalesforceId());
            Mage::helper('tnw_salesforce')->log("###################################### Shipping End ########################################");
        } else {
            Mage::helper('tnw_salesforce')->log("---- SKIPPING ORDER SHIPMENT. ERRORS FOUND. PLEASE REFER TO LOG FILE ----");
        }
    }

    /**
     * order sync event
     *
     * @param $observer
     */
    public function orderStatusUpdateTrigger($observer)
    {
        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::helper("tnw_salesforce")->log('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }

        $order = $observer->getEvent()->getOrder();
        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledOrderSync()
        ) {
            Mage::helper("tnw_salesforce")->log('SKIPING: Order synchronization disabled');
            return; // Disabled
        }

        if (!$order->getSalesforceId() && $order->getId() && $order->getStatus()) {
            // Never was synced, new order
            Mage::dispatchEvent('tnw_sales_order_save', array('order' => $order));
            return;
        }

        if (!Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::helper('tnw_salesforce/salesforce_opportunity')->resetOrder($order->getId());
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::helper("tnw_salesforce")->log('ERROR: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }

        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            // pass data to local storage

            // TODO add level up abstract class with Order as static values, now we have word 'Order' as parameter
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array(intval($order->getData('entity_id'))), 'Order', 'order');
            if (!$res) {
                Mage::helper("tnw_salesforce")->log('error: order status update not saved to local storage');
                return false;
            }
            return true;
        }

        if (
            $order->getId() &&
            $order->getStatus()
        ) {
            $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
            Mage::helper('tnw_salesforce')->log("###################################### Order Status Update Start ######################################");
            if ($_syncType == "order") {
                Mage::helper('tnw_salesforce/salesforce_' . $_syncType)->updateStatus($order);
            } else {
                // Opportunity... need to deprecate
                Mage::helper('tnw_salesforce/order')->updateStatus($order);
            }
            Mage::helper('tnw_salesforce')->log("###################################### Order Status Update End ########################################");
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
            Mage::helper("tnw_salesforce")->log('ERROR: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }
        // Triggers TNW event that pushes to SF
        $order = $observer->getEvent()->getOrder();
        Mage::helper("tnw_salesforce")->log('MAGENTO EVENT: Order #' . $order->getRealOrderId() . ' Sync');

        // Check if AITOC is installed
        $modules = Mage::getConfig()->getNode('modules')->children();
        // Only dispatch event if AITOC is not installed, otherwise we use different event
        if (!property_exists($modules, 'Aitoc_Aitcheckoutfields')) {
            Mage::dispatchEvent('tnw_sales_order_save', array('order' => $order));
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
        Mage::helper("tnw_salesforce")->log('AITOC EVENT: Order #' . $order->getRealOrderId() . ' Sync');

        Mage::dispatchEvent('tnw_sales_order_save', array('order' => $order));
    }

    /* Order Sync */
    public function salesforcePush($observer)
    {
        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::helper("tnw_salesforce")->log('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }
        $order = $observer->getEvent()->getOrder();

        Mage::helper("tnw_salesforce")->log('TNW EVENT: Order #' . $order->getRealOrderId() . ' Sync');

        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledOrderSync()
        ) {
            Mage::helper("tnw_salesforce")->log('SKIPING: Order synchronization disabled');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::helper("tnw_salesforce")->log('ERROR: Salesforce connection could not be established, SKIPPING order sync');
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

            //$res = Mage::getModel('tnw_salesforce/localstorage')->addObject($_productIds, 'Product', 'product');
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObjectProduct($_productIds, 'Product', 'product');
            if (!$res) {
                Mage::helper("tnw_salesforce")->log('error: products from the order were not saved in local storage');
                return false;
            }

            // TODO add level up abstract class with Order as static values, now we have word 'Order' as parameter
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array(intval($order->getData('entity_id'))), 'Order', 'order');
            if (!$res) {
                Mage::helper("tnw_salesforce")->log('error: order not saved to local storage');
                return false;
            }
            return true;
        }

        if (!Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::helper('tnw_salesforce/salesforce_opportunity')->resetOrder($order->getId());
        }

        $_order = Mage::getModel('sales/order')->load($order->getId());

        if (
            $order->getId() &&
            $_order->getStatus() // commented cause order status is <empty> or 'pending' and if <empty> then order was not synced with sf
        ) {
            Mage::helper('tnw_salesforce')->log("############################ New Order Start ############################");
            $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());

            Mage::dispatchEvent(
                'tnw_sales_process_' . $_syncType,
                array(
                    'orderIds'      => array($order->getId()),
                    'message'       => "SUCCESS: Upserting Order #" . $order->getRealOrderId(),
                    'type'   => 'salesforce'
                )
            );

            Mage::helper('tnw_salesforce')->log("############################ New Order End ############################");
        } else {
            Mage::helper('tnw_salesforce')->log("---- SKIPPING ORDER SYNC ----");
            Mage::helper('tnw_salesforce')->log("Order Status: " . $_order->getStatus());
            Mage::helper('tnw_salesforce')->log("Order Id: " . $order->getId());
            if (Mage::getSingleton('core/session')->getFromSalesForce()) {
                Mage::helper('tnw_salesforce')->log("Transaction is from Salesforce!");
            }
            Mage::helper('tnw_salesforce')->log("--------");
        }
    }

    /* Order Cancel Event */
    public function orderCancelled($observer)
    {
        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::helper("tnw_salesforce")->log('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }
        $order = $observer->getEvent()->getOrder();
        if (!Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::helper('tnw_salesforce/salesforce_opportunity')->resetOrder($order->getId());
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::helper("tnw_salesforce")->log('ERROR: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }
        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledOrderSync()
        ) {
            Mage::helper("tnw_salesforce")->log('SKIPING: Order synchronization disabled');
            return; // Disabled
        }

        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            // pass data to local storage

            // Extract all purchased products and add to local storage for sync
            $_productIds = array();
            foreach ($order->getAllVisibleItems() as $_item) {
                $_productIds[] = (int)Mage::helper('tnw_salesforce/salesforce_opportunity')->getProductIdFromCart($_item);
            }

            //$res = Mage::getModel('tnw_salesforce/localstorage')->addObject($_productIds, 'Product', 'product');
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObjectProduct($_productIds, 'Product', 'product');
            if (!$res) {
                Mage::helper("tnw_salesforce")->log('error: products from the order were not saved in local storage');
                return false;
            }

            // Add order into the Queue
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array(intval($order->getData('entity_id'))), 'Order', 'order');
            if (!$res) {
                Mage::helper("tnw_salesforce")->log('error: order cancellation not saved to local storage');
                return false;
            }
            return true;
        }

        if (
            $order->getId() &&
            //$order->getSalesforceId() &&
            $order->getStatus()
        ) {
            Mage::helper('tnw_salesforce')->log("================ INVENTORY SYNC: START ================");
            $manualSync = Mage::helper('tnw_salesforce/salesforce_product');
            $manualSync->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
            $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));

            if ($manualSync->reset()) {
                $itemIds = array();
                foreach ($order->getAllVisibleItems() as $_item) {
                    $itemIds[] = (int)Mage::helper('tnw_salesforce/salesforce_' . $_syncType)->getProductIdFromCart($_item);
                }
                if (!empty($itemIds)) {
                    $manualSync->massAdd($itemIds);
                    $manualSync->process();
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Product inventory was synchronized with Salesforce'));
                }
            } else {
                Mage::getSingleton('adminhtml/session')->addError('Salesforce Connection failed!');
            }
            Mage::helper('tnw_salesforce')->log("================ INVENTORY SYNC: END ================");
        }
    }


}
