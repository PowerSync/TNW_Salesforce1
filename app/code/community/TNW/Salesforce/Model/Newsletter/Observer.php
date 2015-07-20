<?php

/**
 * Class TNW_Salesforce_Model_Newsletter_Observer
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
        $manualSync->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
        $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));
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
        $manualSync->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
        $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));
        $manualSync->newsletterSubscription(array($subscriber));
    }
}