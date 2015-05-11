<?php

/**
 * Class TNW_Salesforce_Model_Sale_Notes_Observer
 */
class TNW_Salesforce_Model_Sale_Notes_Observer
{
    /**
     * @param $observer
     */
    public function notesPush($observer)
    {
        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::helper("tnw_salesforce")->log('ERROR: Salesforce connection could not be established, SKIPPING order notes sync');
            return; // Disabled
        }
        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledOrderSync()
        ) {
            Mage::helper("tnw_salesforce")->log('SKIPING: Order synchronization disabled');
            return; // Disabled
        }

        $event = $observer->getEvent();
        $order = Mage::getModel('sales/order')->load($event->getOid());
        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            // pass data to local storage

            // TODO add level up abstract class with Order as static values, now we have word 'Order' as parameter
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array(intval($order->getData('entity_id'))), 'Order', 'order');
            if (!$res) {
                Mage::helper("tnw_salesforce")->log('ERROR: Order could not be added to the queue');
            }
            return;
        }

        if (
            !Mage::getSingleton('core/session')->getFromSalesForce()
        ) {
            if ($order->getSalesforceId()) {
                // Process Notes
                Mage::helper('tnw_salesforce/order_notes')->process($event->getNote(), $order, $event->getType());
                Mage::helper('tnw_salesforce')->log("###################################### Order Status Update Start (Notes) ######################################");
                Mage::dispatchEvent(
                    'tnw_sales_status_update_' . $_syncType,
                    array(
                        'order'  => $order
                    )
                );

                Mage::helper('tnw_salesforce')->log("###################################### Order Status Update End (Notes) ########################################");
            } else {
                // Never was synced, new order
                Mage::dispatchEvent(
                    'tnw_sales_process_' . $_syncType,
                    array(
                        'orderIds'      => array($order->getId()),
                        'message'       => NULL,
                        'type'   => 'salesforce'
                    )
                );

                Mage::helper('tnw_salesforce/order_notes')->process($event->getNote(), $order, $event->getType());
            }
        } else {
            Mage::helper('tnw_salesforce')->log("---- SKIPPING ORDER NOTES SYNC. ERRORS FOUND. PLEASE REFER TO LOG FILE ----");
        }
    }
}