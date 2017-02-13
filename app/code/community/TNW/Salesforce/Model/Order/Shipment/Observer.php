<?php

class TNW_Salesforce_Model_Order_Shipment_Observer
{
    const OBJECT_TYPE = 'shipment';

    /**
     * @param Varien_Event_Observer $_observer
     * @return bool|void
     */
    public function saveAfter(Varien_Event_Observer $_observer)
    {
        /** @var Mage_Sales_Model_Order_Shipment $_shipment */
        $_shipment = $_observer->getEvent()->getShipment();
        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace("TNW EVENT: Shipment #{$_shipment->getIncrementId()} Sync");

        $this->syncShipment(array($_shipment->getId()));
    }

    /**
     * @param array $entityIds
     * @throws Exception
     */
    public function syncShipment(array $entityIds)
    {
        $groupWebsite = array();
        foreach (array_chunk($entityIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT) as $_entityIds) {
            /** @var Varien_Db_Select $select */
            $select = Mage::getSingleton('tnw_salesforce/localstorage')
                ->generateSelectForType('sales/order_shipment', $_entityIds);

            foreach ($select->getAdapter()->fetchAll($select) as $row) {
                $groupWebsite[$row['website_id']][] = $row['object_id'];
            }
        }

        foreach ($groupWebsite as $websiteId => $entityIds) {
            $this->syncShipmentForWebsite($entityIds, $websiteId);
        }
    }

    /**
     * @param array $entityIds
     * @param null $website
     * @throws Exception
     */
    public function syncShipmentForWebsite(array $entityIds, $website = null)
    {
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($entityIds) {
            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPING: API Integration is disabled');

                return;
            }

            if (!Mage::helper('tnw_salesforce/config_sales_shipment')->syncShipments()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPING: Shipment synchronization disabled');

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
                        ->addObjectProduct($entityIds, 'Shipment', 'shipment', $syncBulk);

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
                    $_syncType = strtolower($helper->getShipmentObject());
                    Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                        'shipmentIds' => $entityIds,
                        'message' => $helper->__('Total of %d shipment(s) were synchronized', count($entityIds)),
                        'type' => 'salesforce'
                    ));
                }
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError($e->getMessage());
            }
        });
    }

    /**
     * @param Varien_Event_Observer $_observer
     */
    public function saveTrackAfter(Varien_Event_Observer $_observer)
    {
        /** @var Mage_Sales_Model_Order_Shipment_Track $_track */
        $_track = $_observer->getEvent()->getTrack();
        $entity = $_track->getShipment();
        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace("TNW EVENT: Shipment #{$entity->getIncrementId()} Track Sync");

        if (!$entity->getSalesforceId()) {
            $this->syncShipment(array($entity->getId()));
            return;
        }

        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($entity->getStore()->getWebsite(), function () use($entity, $_track) {
            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');
            if (!$helper->isRealTimeType()) {
                $success = Mage::getModel('tnw_salesforce/localstorage')
                    ->addObject(array($entity->getId()), 'Shipment', 'shipment');

                if (!$success) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError('Could not add to the queue!');

                    return;
                }

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveSuccess($helper->__('Records are pending addition into the queue!'));

                return;
            }

            /** @var TNW_Salesforce_Helper_Salesforce_Order_Shipment $syncHelper */
            $syncHelper = Mage::helper('tnw_salesforce/salesforce_order_shipment');
            if(!$syncHelper->reset()) {
                return;
            }

            $syncHelper->_cache['orderShipmentLookup'] = Mage::helper('tnw_salesforce/salesforce_data_shipment')
                ->lookup(array($entity->getIncrementId()));
            $syncHelper->_cache['upserted' . $syncHelper->getManyParentEntityType()][$entity->getIncrementId()] = $entity->getSalesforceId();
            $syncHelper->createObjTrack(array($_track));
            $syncHelper->pushDataTrack();
        });
    }
}