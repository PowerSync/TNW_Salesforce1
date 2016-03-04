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
     * @param $observer
     * @return bool
     */
    public function salesforcePush($observer)
    {
        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }
        $website = $observer->getEvent()->getWebsite();
        $_webstieId = intval($website->getData('website_id'));

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('TNW EVENT: Website Sync (Code: ' . $website->getData('code') . ')');

        // check if queue sync setting is on - then save to database
        if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
            // pass data to local storage
            $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array($_webstieId), 'Website', 'website');
            if (!$res) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Website could not be added to queue');
                return false;
            }
            return true;
        }
        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: processing Saleforce trigger');
            return; // Disabled
        }

        if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Salesforce connection could not be established, SKIPPING website sync');
            return; // Disabled
        }

        $manualSync = Mage::helper('tnw_salesforce/salesforce_website');
        $manualSync->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
        $manualSync->setSalesforceSessionId(Mage::getSingleton('core/session')->getSalesforceSessionId());

        if ($manualSync->reset()) {
            $manualSync->massAdd(array($_webstieId));
            $manualSync->process();
            if (Mage::helper('tnw_salesforce')->displayErrors()
                && Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Website (code: ' . $website->getData('code') . ') is successfully synchronized'));
            }
        }
    }

    public function updateForm($observer) {
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