<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Customer
 */
class TNW_Salesforce_Helper_Salesforce_NewsletterSubscriber extends TNW_Salesforce_Helper_Salesforce_Abstract
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

        if(!$this->validate($subscriber)){
            return false;
        }

        $helper->log("###################################### Subscriber Update Start ######################################");

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
        /** @var TNW_Salesforce_Helper_Salesforce_Data_Account $helperAccount */
        $helperAccount = Mage::helper('tnw_salesforce/salesforce_data_account');
        /** @var TNW_Salesforce_Helper_Salesforce_Data_Lead $helperLead */
        $helperLead = Mage::helper('tnw_salesforce/salesforce_data_lead');


        // Check for Contact and Account
        $contactLookup = $helperContact->lookup( array($customerId=>$email),
                                                 array($customerId => $this->_websiteSfIds[$websiteId]) );
        $accountLookup = $helperAccount->lookup( array($customerId=>$email),
                                                 array($customerId => $this->_websiteSfIds[$websiteId]) );
        $leadLookup = null;
        if(!$contactLookup){
            $leadLookup = $helperLead->lookup( array($customerId=>$email),
                                               array($customerId => $this->_websiteSfIds[$websiteId]) );
        }

        $this->_obj = new stdClass();
        $id = NULL;
        $isLead = true;
        $isContact = false;
        $isPerson = false;

        if ($leadLookup && array_key_exists($this->_websiteSfIds[$websiteId], $leadLookup)
            && array_key_exists($email, $leadLookup[$this->_websiteSfIds[$websiteId]]))
        {
            // Existing Lead
            $id = $leadLookup[$this->_websiteSfIds[$websiteId]][$email]->Id;
        }


        if ($contactLookup && array_key_exists($this->_websiteSfIds[$websiteId], $contactLookup)
            && array_key_exists($email, $contactLookup[$this->_websiteSfIds[$websiteId]]))
        {
            // Existing Contact
            $id = $this->_cache['contactsLookup'][$this->_websiteSfIds[$websiteId]][$email]->Id;
            $isContact = true;
            $isLead = false;
            if ( Mage::app()->getWebsite($websiteId)->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_PERSON_ACCOUNT)
                 && property_exists($contactLookup[$this->_websiteSfIds[$websiteId]][$email], 'Account')
                 && property_exists($contactLookup[$this->_websiteSfIds[$websiteId]][$email]->Account, 'IsPersonAccount')
            ) {
                $isPerson = true;
            }
        }

        if ($id) {
            $this->_obj->Id = $id;
        } elseif ($_type == 'delete') {
            // No lead or a contact in Salesforce, nothing to update
            $helper->log("SKIPPING: No Lead or Contact in Salesforce, nothing to update.");
            return false;
        }


        if ($customer) {
            $firstName = ($customer->getFirstname()) ? $customer->getFirstname() : '';
            $lastName = ($customer->getLastname()) ? $customer->getLastname() : $email;
        } else {
            $name = (is_object(Mage::getSingleton('customer/session')->getCustomer())) ? Mage::getSingleton('customer/session')->getCustomer()->getName() : NULL;
            if (!$name) {
                $helper->log("SKIPPING: No Customer and No Customer Name found");
                return false;
            }
            $customerName = explode(' ', $name);
            if (count($customerName) > 1) {
                $firstName = $customerName[0];
                $lastName = $customerName[1];
            } else {
                $lastName = $customerName;
                $firstName = '';
            }
        }
        $this->_obj->LastName = $lastName;
        $this->_obj->FirstName = $firstName;

        if (empty($this->_obj->LastName)){
            // No last name, Salesforce will error out
            $helper->log("SKIPPING: No LastName defined for a subscriber: " . strip_tags($email) . ".");
            return false;
        }

        $status = $subscriber->getSubscriberStatus();
        $this->_obj->HasOptedOutOfEmail =
            ($status == Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED || $_type == 'delete') ? 0 : 1;
        $this->_obj->Email = strip_tags($email);


        // Link to a Website
        if ( $websiteId !== NULL && array_key_exists($websiteId, $this->_websiteSfIds)
             && $this->_websiteSfIds[$websiteId])
        {
            $this->_obj->{Mage::helper('tnw_salesforce/config')->getMagentoWebsiteField()} = $this->_websiteSfIds[$websiteId];
        }


        if ($isLead) {
            if (!Mage::app()->getWebsite($websiteId)->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_PERSON_ACCOUNT)) {
                $this->_obj->Company = 'N/A';
            }

            foreach ($this->_obj as $key => $value) {
                $helper->log("Lead Object: " . $key . " = '" . $value . "'");
            }

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
                    Mage::helper('tnw_salesforce')->log('SUCCESS: Lead upserted (id: ' . $result->id . ')');
                    $id = $result->id;
                    $this->_prepareCampaignMember('LeadId', $id, $subscriber, $customerId);
                } else {
                    $this->_processErrors($result, 'lead', $this->_cache['leadsToUpsert'][$key]);
                }
            }
        } elseif ($isContact) {
            if ($isPerson) {
                $this->_obj->PersonHasOptedOutOfEmail = $this->_obj->HasOptedOutOfEmail;
                unset($this->_obj->HasOptedOutOfEmail);

            }
            foreach ($this->_obj as $key => $value) {
                $helper->log("Contact Object: " . $key . " = '" . $value . "'");
            }

            if ($helper->getType() == "PRO") {
                $syncParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . "disableMagentoSync__c";
                $this->_obj->$syncParam = true;
            }

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
        }

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