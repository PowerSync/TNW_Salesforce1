<?php

/**
 * Class TNW_Salesforce_Model_Customer_Observer
 */
class TNW_Salesforce_Model_Customer_Observer
{
    public function __construct()
    {
    }

    /**
     * @param $observer
     */
    public function salesforceTriggerEvent($observer)
    {
        // Triggers TNW event that pushes to SF
        $customer = $observer->getEvent()->getCustomer();
        Mage::getModel('tnw_salesforce/tool_log')->saveNotice('MAGENTO EVENT: Customer Sync (Email: ' . $customer->getEmail() . ')');

        // Check if AITOC is installed
        $modules = Mage::getConfig()->getNode('modules')->children();
        // Only dispatch event if AITOC is not installed, otherwise we use different event
        if (!property_exists($modules, 'Aitoc_Aitcheckoutfields')) {
            Mage::dispatchEvent('tnw_customer_save', array('customer' => $customer));
        }
    }

    /**
     * @param $observer
     */
    public function salesforceAitocTriggerEvent($observer)
    {
        // Triggers TNW event that pushes to SF
        // Hack need to remove in May
        $customer = ($observer->getEvent()->getCustomer()) ? $observer->getEvent()->getCustomer() : $observer->getEvent()->getOrder();
        Mage::getModel('tnw_salesforce/tool_log')->saveNotice('AITOC EVENT: Customer Sync (Email: ' . $customer->getEmail() . ')');

        Mage::dispatchEvent('tnw_customer_save', array('customer' => $customer));
    }

    /**
     * @param $observer
     */
    public function triggerWebToLead($observer)
    {
        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getModel('tnw_salesforce/tool_log')->saveError('ERROR: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }
        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledContactForm()
        ) {
            Mage::getModel('tnw_salesforce/tool_log')->saveNotice('SKIPING: Contact form synchronization disabled');
            return; // Disabled
        }

        $formData = $observer->getData('controller_action')->getRequest()->getPost();

        if (!empty($formData)) {
            try {
                $manualSync = Mage::helper('tnw_salesforce/salesforce_customer');
                $manualSync->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
                $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));

                if ($manualSync->reset()) {
                    $manualSync->pushLead($formData);
                }
            } catch (Exception $e) {
                Mage::getModel('tnw_salesforce/tool_log')->saveError('SKIPING: Contact form synchronization, error: ' . $e->getMessage());
            }
        }
    }

    /**
     * @param $observer
     * @return bool
     */
    public function salesforcePush($observer)
    {
        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::getModel('tnw_salesforce/tool_log')->saveNotice('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }
        $customer = $observer->getEvent()->getCustomer();

        Mage::getModel('tnw_salesforce/tool_log')->saveNotice('TNW EVENT: Customer Sync (Email: ' . $customer->getEmail() . ')');

        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledCustomerSync()
        ) {
            Mage::getModel('tnw_salesforce/tool_log')->saveNotice('SKIPPING: Customer synchronization is disabled');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getModel('tnw_salesforce/tool_log')->saveError('ERROR: Salesforce connection could not be established, SKIPPING customer sync');
            return; // Disabled
        }

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            // pass data to local storage
            // TODO add level up abstract class with Order as static values, now we have word 'Customer' as parameter
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array(intval($customer->getData('entity_id'))), 'Customer', 'customer');
            if (!$res) {
                Mage::getModel('tnw_salesforce/tool_log')->saveError('error: customer not saved to local storage');
                return false;
            }
            return true;
        }

        $_customerSync = Mage::helper('tnw_salesforce/salesforce_customer');
        $_customerSync->reset();
        $_customerSync->updateMagentoEntityValue($customer->getId(), NULL, 'sf_insync', 'customer_entity_int');

        $manualSync = Mage::helper('tnw_salesforce/salesforce_customer');
        $manualSync->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
        $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));

        if ($manualSync->reset()) {
            $manualSync->massAdd(array($customer->getId()));
            $manualSync->process();
            if (Mage::helper('tnw_salesforce')->displayErrors()
                && Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Customer (email: ' . $customer->getEmail() . ') is successfully synchronized'));
            }
        } else {
            if (Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('Salesforce connection could not be established!');
            }
        }
    }

    public function beforeImport()
    {
        Mage::getSingleton('core/session')->setFromSalesForce(true);
    }

    public function afterImport()
    {
        Mage::getSingleton('core/session')->setFromSalesForce(false);
    }
}