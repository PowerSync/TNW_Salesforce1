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
     */
    public function salesforcePush($observer)
    {
        /** @var Mage_Customer_Model_Customer $customer */
        $customer = $observer->getEvent()->getCustomer();
        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace("TNW EVENT: Customer Sync (Email: {$customer->getEmail()})");

        $this->syncCustomer(array($customer->getId()));
    }

    /**
     * @param array $entityIds
     */
    public function syncCustomer(array $entityIds)
    {
        /** @var Varien_Db_Select $select */
        $select = TNW_Salesforce_Model_Localstorage::generateSelectForType('customer/customer', $entityIds);

        $groupWebsite = array();
        foreach ($select->getAdapter()->fetchAll($select) as $row) {
            $groupWebsite[$row['website_id']][] = $row['object_id'];
        }

        foreach ($groupWebsite as $websiteId => $entityIds) {
            $this->syncCustomerForWebsite($entityIds, $websiteId);
        }
    }

    public function syncCustomerForWebsite(array $entityIds, $website = null)
    {
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($entityIds) {
            $website = Mage::app()->getWebsite();

            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveNotice(sprintf('SKIPPING: API Integration is disabled in Website: %s', $website->getName()));

                return;
            }

            if (!$helper->isEnabledCustomerSync()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('SKIPPING: Customer synchronization disabled in Website: %s', $website->getName()));

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

            $syncBulk = (count($entityIds) > 1);

            try {
                if (count($entityIds) > $helper->getRealTimeSyncMaxCount() || !$helper->isRealTimeType()) {
                    $success = Mage::getModel('tnw_salesforce/localstorage')
                        ->addObject($entityIds, 'Customer', 'customer', $syncBulk);

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
                    /** @var TNW_Salesforce_Helper_Salesforce_Customer $manualSync */
                    $manualSync = Mage::helper(sprintf('tnw_salesforce/%s_customer', $syncBulk ? 'bulk' : 'salesforce'));
                    if ($manualSync->reset() && $manualSync->massAdd($entityIds) && $manualSync->process()) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Total of %d record(s) were successfully synchronized in Website: %s', count($entityIds), $website->getName()));
                    }
                }
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError($e->getMessage());
            }
        });
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
    public function prepareSave($observer)
    {
        /** @var Mage_Customer_Model_Customer $customer */
        $customer = $observer->getData('customer');
        $account  = $observer->getData('request')->getPost('account', array());

        if (!empty($account['salesforce_account_owner_id'])) {
            $customer->setData('salesforce_account_owner_id', $account['salesforce_account_owner_id']);
        }

        if (!empty($account['salesforce_sales_person'])) {
            $customer->setData('salesforce_contact_owner_id', $account['salesforce_sales_person']);
            $customer->setData('salesforce_lead_owner_id', $account['salesforce_sales_person']);
        }
    }
}