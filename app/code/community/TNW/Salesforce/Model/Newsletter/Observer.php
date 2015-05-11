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
        // check if newsletter synchronization enabled
        if ((bool)Mage::helper('tnw_salesforce')->getCustomerNewsletterSync() == false) {
            return false;
        }

        // Triggers TNW event that pushes to SF
        //  TODO: Build queue functionality in addition to realtime sync
        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
        ) {
            Mage::helper("tnw_salesforce")->log('SKIPPING: Powersync is disabled');
            return; // Disabled sync
        }
        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::helper("tnw_salesforce")->log('ERROR: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }
        $_data = $observer->getSubscriber();
        $manualSync = Mage::helper('tnw_salesforce/salesforce_customer');
        $manualSync->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
        $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));

        if ($manualSync->reset()) {
            $manualSync->newsletterSubscription($_data, $_data->getSubscriberStatus());
        }
    }

    /**
     * @param $observer
     * @return bool
     */
    public function triggerDeleteEvent($observer)
    {
        // check if newsletter synchronization enabled
        if ((bool)Mage::helper('tnw_salesforce')->getCustomerNewsletterSync() == false) {
            return false;
        }

        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
        ) {
            Mage::helper("tnw_salesforce")->log('SKIPPING: Powersync is disabled');
            return; // Disabled sync
        }

        // Triggers TNW event that pushes to SF
        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::helper("tnw_salesforce")->log('ERROR: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }
        $_data = $observer->getSubscriber();
        $manualSync = Mage::helper('tnw_salesforce/salesforce_customer');
        $manualSync->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
        $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));

        if ($manualSync->reset()) {
            $manualSync->newsletterSubscription($_data, false, 'delete');
        }
    }
}