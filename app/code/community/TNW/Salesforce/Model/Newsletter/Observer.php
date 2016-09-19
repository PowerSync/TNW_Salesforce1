<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Newsletter_Observer
{
    /**
     * @param $observer
     */
    public function triggerCreateEvent($observer)
    {
        /** @var Mage_Newsletter_Model_Subscriber $subscriber */
        $subscriber = $observer->getSubscriber();

        $this->_syncSubscriber($subscriber);
    }

    /**
     * @param $observer
     */
    public function triggerDeleteEvent($observer)
    {
        /** @var Mage_Newsletter_Model_Subscriber $subscriber */
        $subscriber = $observer->getSubscriber();
        $subscriber->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);

        $this->_syncSubscriber($subscriber);
    }

    /**
     * @param $subscriber Mage_Newsletter_Model_Subscriber
     * @return bool|mixed
     */
    protected function _syncSubscriber($subscriber)
    {
        /** @var $manualSync TNW_Salesforce_Helper_Salesforce_Newslettersubscriber */
        $manualSync = Mage::helper('tnw_salesforce/salesforce_newslettersubscriber');
        if (!$manualSync->validateSync()) {
            return false;
        }

        $issetCustomer = is_numeric($subscriber->getCustomerId()) && (bool)$subscriber->getCustomerId();
        if (!$issetCustomer) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace(sprintf("Subscription synchronization skipped for subscriber (%s), customer is not registered.", $subscriber->getEmail()));

            return false;
        }

        $customerId = $subscriber->getCustomerId();
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            return Mage::getModel('tnw_salesforce/localstorage')
                ->addObject(array($customerId), 'Customer', 'customer');
        }
        else {
            /** @var $manualSync TNW_Salesforce_Helper_Salesforce_Customer */
            $manualSync = Mage::helper('tnw_salesforce/salesforce_customer');
            return $manualSync->reset() && $manualSync->massAdd(array($customerId)) && $manualSync->process();
        }
    }
}