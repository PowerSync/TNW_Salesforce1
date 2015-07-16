<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Customer
 */
class TNW_Salesforce_Helper_Salesforce_Newslettersubscriber extends TNW_Salesforce_Helper_Salesforce_Abstract
{
    /**
     * Validation before sync
     *
     * @param Mage_Newsletter_Model_Subscriber $subscriber
     * @return bool
     */
    private function validate(Mage_Newsletter_Model_Subscriber $subscriber)
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $this->reset();

        if(!$helper->isEnabled()){
            $helper->log('SKIPPING: Powersync is disabled');
            return false;
        }

        if(!$helper->getCustomerNewsletterSync()){
            $helper->log('SKIPPING: Newsletter Sync is disabled');
            return false;
        }

        if ($helper->getType() != "PRO") {
            $helper->log("IMPORTANT: Skipping newsletter synchronization, please upgrade to Enterprise version!");
            return false;
        }

        $this->checkConnection();

        /** @var  TNW_Salesforce_Helper_Salesforce_Data $helper_sf_data */
        $helper_sf_data = Mage::helper('tnw_salesforce/salesforce_data');

        if (!$helper_sf_data->isLoggedIn() || !$helper->canPush()) {
            $helper->log("CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
            return false;
        }
        if (!is_object($subscriber) || !$subscriber->getData('subscriber_email')) {
            $helper->log("SKIPPING: Subscriber object is invalid.");
            return false;
        }
        $status = $subscriber->getSubscriberStatus();

        if ($status === NULL) {
            $helper->log("SKIPPING: Unknown subscriber status.");
            return false;
        }

        return true;

    }


    /**
     * @param int $id
     * @param Mage_Newsletter_Model_Subscriber $subscriber
     * @param int $websiteId
     * @param Mage_Customer_Model_Customer $customer
     * @return stdClass
     */
    private function subscribeLead($id, $subscriber, $websiteId, $customer)
    {
        $this->_obj = $this->getTransferObject($id, $subscriber, $websiteId, $customer);

        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        if (!Mage::app()->getWebsite($websiteId)->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_PERSON_ACCOUNT)) {
            $this->_obj->Company = 'N/A';
        }

        foreach ($this->_obj as $key => $value) {
            $helper->log("Lead Object: " . $key . " = '" . $value . "'");
        }

        $customerId = $subscriber->getData('customer_id');

        $this->_cache['leadsToUpsert'][$customerId] = $this->_obj;

        $assignmentRule = $helper->isLeadRule();

        if ($assignmentRule) {
            $helper->log("Assignment Rule used: " . $assignmentRule);
            $header = new Salesforce_AssignmentRuleHeader($assignmentRule, false);
            $this->_mySforceConnection->setAssignmentRuleHeader($header);
            unset($assignmentRule, $header);
        }

        Mage::dispatchEvent("tnw_salesforce_lead_send_before", array("data" => $this->_cache['leadsToUpsert']));

        $results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['leadsToUpsert']), 'Lead');

        Mage::dispatchEvent("tnw_salesforce_lead_send_after", array(
            "data" => $this->_cache['leadsToUpsert'],
            "result" => $results
        ));

        foreach ($results as $key => $result) {
            //Report Transaction
            $this->_cache['responses']['leads'][$customerId] = $result;

            if (property_exists($result, 'success') && $result->success) {
                $helper->log('SUCCESS: Lead upserted (id: ' . $result->id . ')');
                $id = $result->id;
                $this->_prepareCampaignMember('LeadId', $id, $subscriber, $customerId);
            } else {
                $this->_processErrors($result, 'lead', $this->_cache['leadsToUpsert'][$key]);
            }
        }

        return true;
    }

    /**
     * @param int $id
     * @param Mage_Newsletter_Model_Subscriber $subscriber
     * @param int $websiteId
     * @param Mage_Customer_Model_Customer $customer
     * @param book $isPerson
     * @return stdClass
     */
    private function subscribeContact($id, $subscriber, $websiteId, $customer, $isPerson)
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $this->_obj = $this->getTransferObject($id, $subscriber, $websiteId, $customer);

        if ($isPerson) {
            $this->_obj->PersonHasOptedOutOfEmail = $this->_obj->HasOptedOutOfEmail;
            unset($this->_obj->HasOptedOutOfEmail);
        }

        // Log Contact Object
        foreach ($this->_obj as $key => $value) {
            $helper->log("Contact Object: " . $key . " = '" . $value . "'");
        }

        if ($helper->getType() == "PRO") {
            $syncParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . "disableMagentoSync__c";
            $this->_obj->$syncParam = true;
        }

        $customerId = $subscriber->getData('customer_id');

        $this->_cache['contactsToUpsert'][$customerId] = $this->_obj;

        $contactIds = array_keys($this->_cache['contactsToUpsert']);

        Mage::dispatchEvent("tnw_salesforce_contact_send_before", array("data" => $this->_cache['contactsToUpsert']));

        $results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['contactsToUpsert']), 'Contact');

        Mage::dispatchEvent("tnw_salesforce_contact_send_after", array(
            "data" => $this->_cache['contactsToUpsert'],
            "result" => $results
        ));

        foreach ($results as $key => $result) {
            //Report Transaction
            $this->_cache['responses']['contacts'][$customerId] = $result;

            if (property_exists($result, 'success') && $result->success) {
                $helper->log('SUCCESS: Contact updated (id: ' . $result->id . ')');
                $id = $result->id;
                // create campaign member using campaign id form magento config and id as current contact
                $this->_prepareCampaignMember('ContactId', $id, $subscriber, $customerId);
            } else {
                $this->_processErrors($result, 'contact', $this->_cache['contactsToUpsert'][$contactIds[$key]]);
            }
        }

        return true;

    }

    /**
     * @param int $id
     * @param Mage_Newsletter_Model_Subscriber $subscriber
     * @param int $websiteId
     * @param Mage_Customer_Model_Customer $customer
     * @return stdClass
     */
    private function getTransferObject($id, $subscriber, $websiteId, $customer)
    {
        $this->_obj = new stdClass();
        $this->_obj->Id = $id;

        // If no loaded customer - checking seession
        if(!$customer) $customer = Mage::getSingleton('customer/session')->getCustomer();

        if($customer) {
            $this->_obj->FirstName = $customer->getFirstname();
            $this->_obj->LastName = $customer->getLastname();
            if(empty($this->_obj->LastName)){
                $this->_obj->LastName = $subscriber->getData('subscriber_email');
            }
        }

        $this->_obj->Email = $subscriber->getData('subscriber_email');
        $status = $subscriber->getSubscriberStatus();
        $this->_obj->HasOptedOutOfEmail =
            ($status == Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED) ? 0 : 1;

        // Link to a Website
        if ( $websiteId !== NULL && array_key_exists($websiteId, $this->_websiteSfIds)
            && $this->_websiteSfIds[$websiteId])
        {
            $this->_obj->{Mage::helper('tnw_salesforce/config')->getMagentoWebsiteField()} = $this->_websiteSfIds[$websiteId];
        }

        return $this->_obj;
    }



    /**
     * Manual one record newsletter subscriber sync
     *
     * @param Mage_Newsletter_Model_Subscriber $subscriber
     * @param string $_type
     * @return bool
     */
    public function newsletterSubscription($subscriber = NULL, $_type = 'update')
    {
        /*
         * NOTE: This method only works with a signle subscription - 1 magento subscriber - 1 campaign
         */

        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        // 1. Validate config and data
        if(!$this->validate($subscriber)){
            return false;
        }

        $helper->log("###################################### Subscriber Update Start ######################################");

        // 2. Extract subscriber information
        $email = strtolower($subscriber->getData('subscriber_email'));
        $customerId = $subscriber->getData('customer_id');

        /** @var Mage_Customer_Model_Customer|null $customer */
        $customer = null;

        if ($customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            $websiteId = $customer->getData('website_id');
        }else{
            $websiteId = Mage::getModel('core/store')->load($subscriber->getData('store_id'))->getWebsiteId();
        }

        /** @var TNW_Salesforce_Helper_Salesforce_Data_Contact $helperContact */
        $helperContact = Mage::helper('tnw_salesforce/salesforce_data_contact');

        /** @var TNW_Salesforce_Helper_Salesforce_Data_Lead $helperLead */
        $helperLead = Mage::helper('tnw_salesforce/salesforce_data_lead');

        $id = NULL;
        $isContact = $isPerson = false;

        // 3. Check for Contact
        $contactLookup = $helperContact->lookup( array($customerId=>$email),
            array($customerId => $this->_websiteSfIds[$websiteId]) );
        $accountLookup = null;
        $leadLookup = null;
        // 3.1 If Contact - Take Contact Id
        if($contactLookup && array_key_exists($this->_websiteSfIds[$websiteId], $contactLookup)
            && array_key_exists($email, $contactLookup[$this->_websiteSfIds[$websiteId]]) )
        {
            $id = $contactLookup[$this->_websiteSfIds[$websiteId]][$email]->Id;
            $isContact = true;
            // Check for PersonAccount config
            if (Mage::app()->getWebsite($websiteId)->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_PERSON_ACCOUNT)
                && property_exists($contactLookup[$this->_websiteSfIds[$websiteId]][$email], 'Account')
                && property_exists($contactLookup[$this->_websiteSfIds[$websiteId]][$email]->Account, 'IsPersonAccount')
            ) {
                $isPerson = true;
            }
        }

        // 4. if no Contact and no Account - check for Lead
        if(!$contactLookup){
            $leadLookup = $helperLead->lookup( array($customerId=>$email),
                array($customerId => $this->_websiteSfIds[$websiteId]) );

            if ($leadLookup && array_key_exists($this->_websiteSfIds[$websiteId], $leadLookup)
                && array_key_exists($email, $leadLookup[$this->_websiteSfIds[$websiteId]]))
            {
                // Existing Lead
                $id = $leadLookup[$this->_websiteSfIds[$websiteId]][$email]->Id;
            }
        }

        // 5. do subscription
        if($isContact && $id){
            $sResult = $this->subscribeContact($id, $subscriber, $websiteId, $customer, $isPerson);
        }else{
            $sResult = $this->subscribeLead($id, $subscriber, $websiteId, $customer);
        }

        if(!$sResult){
            $helper->log("###################################### Subscriber Update Failed ######################################");
            return false;
        }

        // 6. update campaigns
        if (!empty($this->_cache['campaignsToUpsert'])) {
            try {

                Mage::dispatchEvent("tnw_salesforce_campaignmember_send_before", array("data" => $this->_cache['campaignsToUpsert']));

                $results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['campaignsToUpsert']), 'CampaignMember');

                Mage::dispatchEvent("tnw_salesforce_campaignmember_send_after", array(
                    "data" => $this->_cache['campaignsToUpsert'],
                    "result" => $results
                ));

                foreach ($results as $key => $result) {
                    //Report Transaction
                    $this->_cache['responses']['campaigns'][$customerId] = $result;
                }
            } catch (Exception $e) {
                $helper->log("error [add lead as campaign member to sf failed]: " . $e->getMessage());
            }
        }

        $this->_onComplete();
        $helper->log("###################################### Subscriber Update End ######################################");

        return true;
    }

    /**
     * Assing campaign for newsletter
     *
     * @param string $_type
     * @param $_id
     * @param $_subscription
     * @param $_key
     */
    protected function _prepareCampaignMember($_type = 'LeadId', $_id, $_subscription, $_key)
    {
        // create campaign member using campaign id form magento config and id as current lead
        if (
            $_subscription->getData('subscriber_status') == 1
            && Mage::helper('tnw_salesforce')->getCutomerCampaignId()
        ) {
            $campaignMemberOb = new stdClass();
            $campaignMemberOb->{$_type} = strval($_id);
            $campaignMemberOb->CampaignId = strval(Mage::helper('tnw_salesforce')->getCutomerCampaignId());

            $this->_cache['campaignsToUpsert'][$_key] = $campaignMemberOb;
        }
        Mage::helper('tnw_salesforce')->log("Campaigns prepared");
    }

}