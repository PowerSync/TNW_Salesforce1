<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Customer
 */
class TNW_Salesforce_Helper_Salesforce_Newslettersubscriber extends TNW_Salesforce_Helper_Salesforce_Abstract
{
    /**
     * Validation before sync
     *
     * @return bool
     */
    protected function validate()
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

        return true;

    }


    /**
     * Add Lead in cache for future sync
     *
     * @param string $id
     * @param Mage_Newsletter_Model_Subscriber $subscriber
     * @param int $websiteId
     * @param Mage_Customer_Model_Customer $customer
     * @param int $index
     */
    protected function addLeadForSubscription($id, $subscriber, $websiteId, $customer, $index)
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

        $this->_cache['leadsToUpsert'][$index] = $this->_obj;

    }


    /**
     * Sync Leads
     *
     * @param Mage_Newsletter_Model_Subscriber[] $subscribers
     * @return bool
     */
    protected function subscribeLeads($subscribers)
    {
        if(empty($this->_cache['leadsToUpsert'])) return false;

        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $assignmentRule = $helper->isLeadRule();

        if ($assignmentRule) {
            $helper->log("Assignment Rule used: " . $assignmentRule);
            $header = new Salesforce_AssignmentRuleHeader($assignmentRule, false);
            $this->_mySforceConnection->setAssignmentRuleHeader($header);
            unset($assignmentRule, $header);
        }

        $subscriberIndexes = array_keys($this->_cache['leadsToUpsert']);

        Mage::dispatchEvent("tnw_salesforce_lead_send_before", array("data" => $this->_cache['leadsToUpsert']));

        $results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['leadsToUpsert']), 'Lead');

        Mage::dispatchEvent("tnw_salesforce_lead_send_after", array(
            "data" => $this->_cache['leadsToUpsert'],
            "result" => $results
        ));

        foreach ($results as $key => $result) {
            //Report Transaction
            $this->_cache['responses']['leads'][$subscriberIndexes[$key]] = $result;

            if (property_exists($result, 'success') && $result->success) {
                $helper->log('SUCCESS: Lead upserted (id: ' . $result->id . ')');
                $id = $result->id;
                $this->_prepareCampaignMember('LeadId', $id, $subscribers[$subscriberIndexes[$key]], $subscriberIndexes[$key]);
            } else {
                $this->_processErrors($result, 'lead', $this->_cache['leadsToUpsert'][$subscriberIndexes[$key]]);
            }
        }

        return true;
    }


    /**
     * Add Contact in cache for future sync
     *
     * @param string $id
     * @param Mage_Newsletter_Model_Subscriber $subscriber
     * @param int $websiteId
     * @param Mage_Customer_Model_Customer $customer
     * @param bool $isPerson
     * @param int $index
     *
     * @return bool
     */
    protected function addAccountContactForSubscription($id, $subscriber, $websiteId, $customer, $isPerson, $index)
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
            $helper->log("Account Contact Object: " . $key . " = '" . $value . "'");
        }

        if ($helper->getType() == "PRO") {
            $syncParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . "disableMagentoSync__c";
            $this->_obj->$syncParam = true;

        }

        $this->_cache['accountContactsToUpsert'][$index] = $this->_obj;


        $this->_obj = new stdClass();
        $this->_obj->Name = $subscriber->getData('subscriber_email');
        $this->_cache['accountsToUpsert'][$index] = $this->_obj;

        return true;
    }


    /**
     * Add Contact in cache for future sync
     *
     * @param string $id
     * @param Mage_Newsletter_Model_Subscriber $subscriber
     * @param int $websiteId
     * @param Mage_Customer_Model_Customer $customer
     * @param bool $isPerson
     * @param int $index
     */
    protected function addContactForSubscription($id, $subscriber, $websiteId, $customer, $isPerson, $index, $accountId = null)
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $this->_obj = $this->getTransferObject($id, $subscriber, $websiteId, $customer);

        if ($isPerson) {
            $this->_obj->PersonHasOptedOutOfEmail = $this->_obj->HasOptedOutOfEmail;
            unset($this->_obj->HasOptedOutOfEmail);
        }

        if($accountId){
            $this->_obj->AccountId = $accountId;
        }

        // Log Contact Object
        foreach ($this->_obj as $key => $value) {
            $helper->log("Contact Object: " . $key . " = '" . $value . "'");
        }

        if ($helper->getType() == "PRO") {
            $syncParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . "disableMagentoSync__c";
            $this->_obj->$syncParam = true;
        }

        $this->_cache['contactsToUpsert'][$index] = $this->_obj;

    }


    /**
     * Sync Contacts
     *
     * @param Mage_Newsletter_Model_Subscriber[] $subscribers
     * @return bool
     */
    protected function subscribeContacts($subscribers)
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        if(!empty($this->_cache['contactsToUpsert'])) {



            $subscriberIndexes = array_keys($this->_cache['contactsToUpsert']);

            Mage::dispatchEvent("tnw_salesforce_contact_send_before", array("data" => $this->_cache['contactsToUpsert']));

            $results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['contactsToUpsert']), 'Contact');

            Mage::dispatchEvent("tnw_salesforce_contact_send_after", array(
                "data" => $this->_cache['contactsToUpsert'],
                "result" => $results
            ));

            foreach ($results as $key => $result) {
                //Report Transaction
                $this->_cache['responses']['contacts'][$subscriberIndexes[$key]] = $result;

                if (property_exists($result, 'success') && $result->success) {
                    $helper->log('SUCCESS: Contact updated (id: ' . $result->id . ')');
                    $id = $result->id;
                    // create campaign member using campaign id form magento config and id as current contact
                    $this->_prepareCampaignMember('ContactId', $id, $subscribers[$subscriberIndexes[$key]], $subscriberIndexes[$key]);
                } else {
                    $this->_processErrors($result, 'contact', $this->_cache['contactsToUpsert'][$subscriberIndexes[$key]]);
                }
            }
        }

        // If there is new contact without account - creating new accounts and then creating new Contacts
        if(!empty($this->_cache['accountsToUpsert'])){

            $accountIndexes = array_keys($this->_cache['accountsToUpsert']);

            Mage::dispatchEvent("tnw_salesforce_account_send_before", array("data" => $this->_cache['accountsToUpsert']));

            $results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['accountsToUpsert']), 'Account');

            Mage::dispatchEvent("tnw_salesforce_account_send_before", array(
                "data" => $this->_cache['accountsToUpsert'],
                "result" => $results
            ));

            $unsetKeys = array();
            foreach ($results as $key => $result) {
                //Report Transaction
                $this->_cache['responses']['accounts'][$accountIndexes[$key]] = $result;

                if (property_exists($result, 'success') && $result->success) {
                    $helper->log('SUCCESS: Account created (id: ' . $result->id . ')');
                    $this->_cache['accountContactsToUpsert'][$key]->AccountId = $result->id;
                } else {
                    $unsetKeys[] = $key;
                    $this->_processErrors($result, 'account', $this->_cache['accountsToUpsert'][$accountIndexes[$key]]);
                }
            }

            foreach($unsetKeys as $key){
                unset($this->_cache['accountContactsToUpsert'][$key]);
            }

            $subscriberIndexes = array_keys($this->_cache['accountContactsToUpsert']);

            Mage::dispatchEvent("tnw_salesforce_contact_send_before", array("data" => $this->_cache['accountContactsToUpsert']));

            $results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['accountContactsToUpsert']), 'Contact');

            Mage::dispatchEvent("tnw_salesforce_contact_send_after", array(
                "data" => $this->_cache['accountContactsToUpsert'],
                "result" => $results
            ));

            foreach ($results as $key => $result) {
                //Report Transaction
                $this->_cache['responses']['contacts'][$subscriberIndexes[$key]] = $result;

                if (property_exists($result, 'success') && $result->success) {
                    $helper->log('SUCCESS: Contact updated (id: ' . $result->id . ')');
                    $id = $result->id;
                    // create campaign member using campaign id form magento config and id as current contact
                    $this->_prepareCampaignMember('ContactId', $id, $subscribers[$subscriberIndexes[$key]], $subscriberIndexes[$key]);
                } else {
                    $this->_processErrors($result, 'contact', $this->_cache['accountContactsToUpsert'][$subscriberIndexes[$key]]);
                }
            }
        }
        return true;
    }

    /**
     * Build transfer objes from subscriber
     *
     * @param int $id
     * @param Mage_Newsletter_Model_Subscriber $subscriber
     * @param int $websiteId
     * @param Mage_Customer_Model_Customer $customer
     * @return stdClass
     */
    protected function getTransferObject($id, $subscriber, $websiteId, $customer)
    {
        $this->_obj = new stdClass();
        $this->_obj->Id = $id;

        // If no loaded customer - checking seession
        if(!$customer && Mage::getSingleton('customer/session')->isLoggedIn()){
            $customer =  Mage::getSingleton('customer/session')->getCustomer();
        }

        if($customer) {
            $this->_obj->FirstName = $customer->getFirstname();
            $this->_obj->LastName = $customer->getLastname();
        }

        if(empty($this->_obj->LastName)){
            $this->_obj->LastName = $subscriber->getData('subscriber_email');
        }

        $this->_obj->Email = $subscriber->getData('subscriber_email');
        $status = $subscriber->getSubscriberStatus();
        $this->_obj->HasOptedOutOfEmail =
            ($status == Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED) ? 1 : 0;

        // Link to a Website
        if ( $websiteId !== NULL && array_key_exists($websiteId, $this->_websiteSfIds)
            && $this->_websiteSfIds[$websiteId])
        {
            $this->_obj->{Mage::helper('tnw_salesforce/config')->getMagentoWebsiteField()} = $this->_websiteSfIds[$websiteId];
        }

        return $this->_obj;
    }


    /**
     * Manual multi-record newsletter subscriber sync
     *
     * @param Mage_Newsletter_Model_Subscriber[] $subscribers
     * @param string $type
     * @return bool
     */
    public function newsletterSubscription($subscribers, $type = 'update')
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        // 1. Validate config
        if(!$this->validate()){
            return false;
        }

        $helper->log("###################################### Subscribers Update Start ######################################");


        $emailsArray = array();
        $websitesArray = array();
        $sfWebsitesArray = array();
        $customersArray = array();
        $contactsEmailArray = array();
        $subscriberIdsArray = array();


        // 2 Prepare Data for Lookup and Updates
        foreach($subscribers as $index => $subscriber)
        {
            // 2.1 Extract subscriber information
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

            // 2.1 Save data by indexes
            $customersArray[$index] = $customer;
            $emailsArray[$index] = $email;
            $websitesArray[$index] = $websiteId;
            $sfWebsitesArray[$index] = $this->_websiteSfIds[$websiteId];
            $subscriberIdsArray[$index] = $subscriber->getId();
        }



        // 3.1 Check for Contact
        /** @var TNW_Salesforce_Helper_Salesforce_Data_Contact $helperContact */
        $helperContact = Mage::helper('tnw_salesforce/salesforce_data_contact');
        $contactLookup = $helperContact->lookup($emailsArray, $sfWebsitesArray);

        /** @var TNW_Salesforce_Helper_Salesforce_Data $helperSf */
        $helperSf = Mage::helper('tnw_salesforce/salesforce_data');
        $accountsFound = $helperSf->accountLookupByEmailDomain($emailsArray);

        foreach($subscribers as $index => $subscriber)
        {
            $email = $emailsArray[$index];
            $websiteId = $websitesArray[$index];
            $customer = $customersArray[$index];

            // 3.1 Going throw Contact matches and add Contact for sync
            if($contactLookup && array_key_exists($this->_websiteSfIds[$websiteId], $contactLookup)
                && array_key_exists($email, $contactLookup[$this->_websiteSfIds[$websiteId]]) )
            {
                $isPerson = false;
                $id = $contactLookup[$this->_websiteSfIds[$websiteId]][$email]->Id;
                // Check for PersonAccount config
                if (Mage::app()->getWebsite($websiteId)->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_PERSON_ACCOUNT)
                    && property_exists($contactLookup[$this->_websiteSfIds[$websiteId]][$email], 'Account')
                    && property_exists($contactLookup[$this->_websiteSfIds[$websiteId]][$email]->Account, 'IsPersonAccount')
                ) {
                    $isPerson = true;
                }
                $this->addContactForSubscription($id, $subscriber, $websiteId, $customer, $isPerson, $index);
                $contactsEmailArray[$index] = $email;
            // 3.2 Looking for Account and if found - create Contact linked to the Account
            }elseif(!$helper->isCustomerAsLead() && array_key_exists($index,$accountsFound)){
                $accountId = $accountsFound[$index];
                $isPerson = false;
                $id = null;
                $this->addContactForSubscription($id, $subscriber, $websiteId, $customer, $isPerson, $index, $accountId);
                $contactsEmailArray[$index] = $email;
            // 3.3 Create Account add AccountId and create new Contact
            }elseif(!$helper->isCustomerAsLead()){
                $isPerson = false;
                $id = null;
                $this->addAccountContactForSubscription($id, $subscriber, $websiteId, $customer, $isPerson, $index);
                $contactsEmailArray[$index] = $email;
            }
        }

        // sync Contacts
        $this->subscribeContacts($subscribers);


        // 3.3 Check for Leads
        if($helper->isCustomerAsLead()) {
            /** @var TNW_Salesforce_Helper_Salesforce_Data_Lead $helperLead */
            $helperLead = Mage::helper('tnw_salesforce/salesforce_data_lead');
            $leadLookup = $helperLead->lookup($emailsArray, $sfWebsitesArray);

            // 4.1 Going throw Leads matches and add Lync for sync
            foreach ($subscribers as $index => $subscriber) {
                $email = $emailsArray[$index];

                if (in_array($email, $contactsEmailArray)) continue;
                $websiteId = $websitesArray[$index];
                $customer = $customersArray[$index];
                $id = null;

                if ($leadLookup && array_key_exists($this->_websiteSfIds[$websiteId], $leadLookup)
                    && array_key_exists($email, $leadLookup[$this->_websiteSfIds[$websiteId]])
                ) {
                    // Existing Lead
                    $id = $leadLookup[$this->_websiteSfIds[$websiteId]][$email]->Id;
                }

                $this->addLeadForSubscription($id, $subscriber, $websiteId, $customer, $index);
            }

            //6. sync Leads
            $this->subscribeLeads($subscribers);
        }




        //7. update campaigns
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
                    $this->_cache['responses']['campaigns'][$key] = $result;
                }
            } catch (Exception $e) {
                $helper->log("error [add lead as campaign member to sf failed]: " . $e->getMessage());
            }
        }

        //8. Finalization
        $this->_onComplete();
        $helper->log("###################################### Subscriber Update End ######################################");

        return true;
    }


    /**
     * Prepare Campaign Member
     *
     * @param string $_type
     * @param $_id
     * @param $_subscription
     * @param $index
     */
    protected function _prepareCampaignMember($_type = 'LeadId', $_id, $_subscription, $index)
    {
        // create campaign member using campaign id form magento config and id as current lead
        if (
            $_subscription->getData('subscriber_status') == 1
            && Mage::helper('tnw_salesforce')->getCutomerCampaignId()
        ) {
            $campaignMemberOb = new stdClass();
            $campaignMemberOb->{$_type} = strval($_id);
            $campaignMemberOb->CampaignId = strval(Mage::helper('tnw_salesforce')->getCutomerCampaignId());

            $this->_cache['campaignsToUpsert'][$index] = $campaignMemberOb;
        }
        Mage::helper('tnw_salesforce')->log("Campaigns prepared");
    }

}