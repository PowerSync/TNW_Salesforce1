<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Customer_Observer
{
    /**
     * @param Varien_Event_Observer $observer
     */
    public function salesforceTriggerEvent($observer)
    {
        // Triggers TNW event that pushes to SF
        $customer = $observer->getEvent()->getCustomer();
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('MAGENTO EVENT: Customer Sync (Email: ' . $customer->getEmail() . ')');

        // Check if AITOC is installed
        $modules = Mage::getConfig()->getNode('modules')->children();
        // Only dispatch event if AITOC is not installed, otherwise we use different event
        if (!property_exists($modules, 'Aitoc_Aitcheckoutfields')) {
            Mage::dispatchEvent('tnw_salesforce_customer_save', array('customer' => $customer));
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function salesforceAitocTriggerEvent($observer)
    {
        // Triggers TNW event that pushes to SF
        // Hack need to remove in May
        $customer = ($observer->getEvent()->getCustomer()) ? $observer->getEvent()->getCustomer() : $observer->getEvent()->getOrder();
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('AITOC EVENT: Customer Sync (Email: ' . $customer->getEmail() . ')');

        Mage::dispatchEvent('tnw_salesforce_customer_save', array('customer' => $customer));
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function triggerWebToLead($observer)
    {
        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledContactForm()
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Contact form synchronization disabled');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Salesforce connection could not be established, SKIPPING order sync');
            return; // Disabled
        }

        $formData = $observer->getData('controller_action')->getRequest()->getPost();

        if (!empty($formData)) {
            try {
                /** @var tnw_salesforce_helper_salesforce_customer $manualSync */
                $manualSync = Mage::helper('tnw_salesforce/salesforce_customer');
                if ($manualSync->reset()) {
                    $manualSync->pushLead($formData);
                }
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('SKIPING: Contact form synchronization, error: ' . $e->getMessage());
            }
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     * @return bool
     */
    public function salesforcePush($observer)
    {
        /** @var Mage_Customer_Model_Customer $customer */
        $customer = $observer->getEvent()->getCustomer();

        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledCustomerSync()
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING: Customer synchronization is disabled');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Salesforce connection could not be established, SKIPPING customer sync');
            return; // Disabled
        }

        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace('TNW EVENT: Customer Sync (Email: ' . $customer->getEmail() . ')');

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            // pass data to local storage
            // TODO add level up abstract class with Order as static values, now we have word 'Customer' as parameter
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array(intval($customer->getData('entity_id'))), 'Customer', 'customer');
            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('error: customer not saved to local storage');
                return;
            }

            return;
        }

        /** @var tnw_salesforce_helper_salesforce_customer $manualSync */
        $manualSync = Mage::helper('tnw_salesforce/salesforce_customer');
        if ($manualSync->reset() && $manualSync->massAdd(array($customer->getId())) && $manualSync->process()) {
            if (Mage::helper('tnw_salesforce')->displayErrors()
                && Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
                Mage::getSingleton('adminhtml/session')
                    ->addSuccess(Mage::helper('adminhtml')->__('Customer (email: ' . $customer->getEmail() . ') is successfully synchronized'));
            }
        }
    }

    /**
     * Address after save event handler
     *
     * @param Varien_Event_Observer $observer
     */
    public function afterAddressSave($observer)
    {
        /** @var $customerAddress Mage_Customer_Model_Address */
        $customerAddress = $observer->getCustomerAddress();
        $customer        = $customerAddress->getCustomer();

        if ($customer->getOrigData('default_billing') != $customer->getData('default_billing')) {
            return;
        }

        if ($customer->getOrigData('default_shipping') != $customer->getData('default_shipping')) {
            return;
        }

        if (!in_array($customerAddress->getId(), array(
            $customer->getData('default_billing'),
            $customer->getData('default_shipping')))
        ) {
            return;
        }

        Mage::dispatchEvent('tnw_salesforce_customer_save', array('customer' => $customer));
    }

    public function beforeImport()
    {
        Mage::getSingleton('core/session')->setFromSalesForce(true);
    }

    public function afterImport()
    {
        Mage::getSingleton('core/session')->setFromSalesForce(false);
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function afterSave($observer)
    {
        $saveAttributes = array(
            'salesforce_contact_owner_id',
            'salesforce_account_owner_id',
            'salesforce_lead_owner_id',
        );

        /** @var Mage_Customer_Model_Customer $customer */
        $customer = $observer->getData('customer');
        $account  = $observer->getData('request')->getPost('account', array());

        foreach ($saveAttributes as $saveAttribute) {
            if (!array_key_exists($saveAttribute, $account)) {
                continue;
            }

            $value = $account[$saveAttribute];
            if (empty($value)) {
                continue;
            }

            $customer->setData($saveAttribute, $value);
            $customer->getResource()->saveAttribute($customer, $saveAttribute);
        }
    }
}