<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Newsletter_Observer
{
    /**
     * @param $observer
     * @return bool
     */
    public function triggerCreateEvent($observer)
    {
        $subscriber = $observer->getSubscriber();
        /** @var TNW_Salesforce_Helper_Salesforce_Newslettersubscriber  $manualSync */
        $manualSync = Mage::helper('tnw_salesforce/salesforce_newslettersubscriber');
        $manualSync->newsletterSubscription(array($subscriber));
    }

    /**
     * @param $observer
     * @return bool
     */
    public function triggerDeleteEvent($observer)
    {
        /** @var Mage_Newsletter_Model_Subscriber $subscriber */
        $subscriber = $observer->getSubscriber();
        $subscriber->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
        /** @var TNW_Salesforce_Helper_Salesforce_Newslettersubscriber  $manualSync */
        $manualSync = Mage::helper('tnw_salesforce/salesforce_newslettersubscriber');
        $manualSync->newsletterSubscription(array($subscriber));
    }
}