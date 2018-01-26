<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Customer
 */
class TNW_Salesforce_Helper_Salesforce_Newslettersubscriber extends TNW_Salesforce_Helper_Salesforce_Abstract
{
    /**
     * Public method for validate
     *
     * @param bool|false $skipNewsletterChecking
     * @return bool
     */
    public function validateSync($skipNewsletterChecking = false)
    {
        return $this->validate($skipNewsletterChecking);
    }

    /**
     * Validation before sync
     * @param bool|false $skipNewsletterChecking used for Campaign member synchronization
     * @return bool
     */
    protected function validate($skipNewsletterChecking = false)
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $this->reset();

        if (!$helper->isEnabled()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING: Powersync is disabled');
            return false;
        }

        if (!$helper->getCustomerNewsletterSync() && !$skipNewsletterChecking) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING: Newsletter Sync is disabled');
            return false;
        }

        /** @var  TNW_Salesforce_Helper_Salesforce_Data $helper_sf_data */
        $helper_sf_data = Mage::helper('tnw_salesforce/salesforce_data');

        if (!$helper_sf_data->isLoggedIn() || !$helper->canPush()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
            return false;
        }

        return true;

    }
}