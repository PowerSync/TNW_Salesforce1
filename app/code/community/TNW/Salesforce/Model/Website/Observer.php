<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Website_Observer
{
    public function __construct()
    {

    }

    /**
     * Function listens to Website changes (new, edits) captures them and either adds them to the queue
     * or pushes data into Salesforce
     * @param $observer Varien_Event_Observer
     */
    public function salesforcePush($observer)
    {
        /** @var Mage_Core_Model_Website $website */
        $website = $observer->getEvent()->getWebsite();

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace("TNW EVENT: Website Sync (Code: {$website->getData('code')})");

        $this->syncWebsite(array($website->getId()));
    }

    /**
     * @param array $entityIds
     * @throws Exception
     */
    public function syncWebsite(array $entityIds)
    {
        $groupWebsite = array();
        foreach (array_chunk($entityIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT) as $_entityIds) {
            /** @var Varien_Db_Select $select */
            $select = Mage::getSingleton('tnw_salesforce/localstorage')
                ->generateSelectForType('core/website', $_entityIds);

            foreach ($select->getAdapter()->fetchAll($select) as $row) {
                $groupWebsite[$row['website_id']][] = $row['object_id'];
            }
        }

        foreach ($groupWebsite as $websiteId => $entityIds) {
            $this->syncWebsiteForWebsite($entityIds, $websiteId);
        }
    }

    /**
     * @param array $entityIds
     * @param null $website
     * @throws Exception
     */
    public function syncWebsiteForWebsite(array $entityIds, $website = null)
    {
        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($entityIds) {
            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveNotice('SKIPPING: API Integration is disabled');

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
                        ->addObject($entityIds, 'Website', 'website', $syncBulk);

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
                    /** @var TNW_Salesforce_Helper_Salesforce_Website $manualSync */
                    $manualSync = Mage::helper(sprintf('tnw_salesforce/%s_website', $syncBulk ? 'bulk' : 'salesforce'));
                    if ($manualSync->reset() && $manualSync->massAdd($entityIds) && $manualSync->process()) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($helper->__('Total of %d record(s) were successfully synchronized', count($entityIds)));
                    }
                }
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError($e->getMessage());
            }
        });
    }

    /**
     * @param $observer Varien_Event_Observer
     */
    public function updateForm($observer)
    {
        $_block = $observer->getEvent()->getBlock();
        if (!$_block) {
            return;
        }

        if (Mage::registry('store_type') == 'website') {
            $_form = $_block->getForm();

            $fieldset = $_form->addFieldset('website_fieldset_salesforce', array(
                'legend' => Mage::helper('core')->__('Salesforce Data')
            ));

            $_options = array();
            foreach (Mage::getModel('tnw_salesforce/config_pricebooks')->toOptionArray() as $_pricebook) {
                $_options[$_pricebook['value']] = $_pricebook['label'];
            }

            $websiteModel = Mage::registry('store_data') ? : new Varien_Object();

            if ($postData = Mage::registry('store_post_data')) {
                $websiteModel->setData($postData['website']);
            }

            $fieldset->addField('website_pricebook_id', 'select', array(
                'name'      => 'website[pricebook_id]',
                'label'     => Mage::helper('tnw_salesforce')->__('Pricebook'),
                'value'     => $websiteModel->getData('pricebook_id'),
                'options'   => $_options,
                'required'  => false
            ));
        }
    }
}