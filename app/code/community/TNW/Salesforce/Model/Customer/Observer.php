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
                    $manualSync->pushContactUs($formData);
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
     * @throws Exception
     */
    public function syncCustomer(array $entityIds, $isManualSync = false)
    {
        $groupWebsite = array();
        foreach (array_chunk($entityIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT) as $_entityIds) {
            /** @var Varien_Db_Select $select */
            $select = Mage::getSingleton('tnw_salesforce/localstorage')
                ->generateSelectForType('customer/customer', $_entityIds);

            foreach ($select->getAdapter()->fetchAll($select) as $row) {
                $groupWebsite[$row['website_id']][] = $row['object_id'];
            }
        }

        foreach ($groupWebsite as $websiteId => $_entityIds) {
            $this->syncCustomerForWebsite($_entityIds, $websiteId, $isManualSync);
        }
    }

    /**
     * @param array $entityIds
     * @param null $website
     * @param bool $isManualSync
     * @throws Exception
     */
    public function syncCustomerForWebsite(array $entityIds, $website = null, $isManualSync = false)
    {
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($entityIds, $isManualSync) {
            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPPING: API Integration is disabled');

                return;
            }

            if (!$isManualSync && !$helper->isEnabledCustomerSync()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPPING: Customer synchronization disabled');

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
                        ->addObject($entityIds, 'Customer', 'customer', $syncBulk);

                    if (!$success) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveError('Could not add to the queue!');
                    } elseif ($syncBulk) {

                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Selected records were added into <a href="%s">synchronization queue</a> and will be processed in the background.', Mage::helper('adminhtml')->getUrl('*/salesforcesync_queue_to/bulk')));
                    } else {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Records are pending addition into the queue!'));
                    }
                } else {
                    /** @var TNW_Salesforce_Helper_Salesforce_Customer $manualSync */
                    $manualSync = Mage::helper('tnw_salesforce/salesforce_customer');
                    if ($manualSync->reset() && $manualSync->massAdd($entityIds) && $manualSync->process() && $successCount = $manualSync->countSuccessEntityUpsert()) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Total of %d customer(s) were successfully synchronized', $successCount));
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

        if (!empty($account['salesforce_account_owner_id']) && empty($account['salesforce_contact_owner_id'])) {
            $customer->setData('salesforce_contact_owner_id', $account['salesforce_account_owner_id']);
        }

        if (!empty($account['salesforce_sales_person'])) {
            $customer->setData('salesforce_contact_owner_id', $account['salesforce_sales_person']);
            $customer->setData('salesforce_lead_owner_id', $account['salesforce_sales_person']);
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function customerSaveBefore($observer)
    {
        $isAllowed = Mage::getSingleton('admin/session')
            ->isAllowed('tnw_salesforce/edit_sales_owner');

        if (!$isAllowed) {
            /** @var Mage_Customer_Model_Customer $customer */
            $customer = $observer->getData('data_object');
            $customer->setData('salesforce_account_owner_id', $customer->getOrigData('salesforce_account_owner_id'));
            $customer->setData('salesforce_contact_owner_id', $customer->getOrigData('salesforce_contact_owner_id'));
            $customer->setData('salesforce_lead_owner_id', $customer->getOrigData('salesforce_lead_owner_id'));
        }
    }
}