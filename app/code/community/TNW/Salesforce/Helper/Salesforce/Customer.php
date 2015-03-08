<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Customer
 */
class TNW_Salesforce_Helper_Salesforce_Customer extends TNW_Salesforce_Helper_Salesforce_Abstract
{
    /**
     * @var null
     */
    protected $_currentCustomer = NULL;

    /**
     * @var null
     */
    protected $_customerAccountId = NULL;

    /**
     * @var null
     */
    protected $_customerOwnerId = NULL;

    /**
     * @var array
     */
    protected $_customerAccounts = array();

    /**
     * @var null
     */
    protected $_forcedCustomerId = NULL;

    /**
     * @var bool
     */
    protected $_isPushingGuestData = false;

    /**
     * @param null $_subscription
     * @param null $_status
     * @param string $_type
     * @return bool
     */
    public function newsletterSubscription($_subscription = NULL, $_status = NULL, $_type = 'update')
    {
        /*
         * NOTE: This method only works with a signle subscription - 1 magento subscriber - 1 campaign
         */
        if (Mage::helper('tnw_salesforce')->getType() != "PRO") {
            Mage::helper('tnw_salesforce')->log("IMPORTANT: Skipping newsletter synchronization, please upgrade to Enterprise version!");
            return false;
        }
        if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
            Mage::helper('tnw_salesforce')->log("CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
            return false;
        }
        if (!is_object($_subscription) || !$_subscription->getData('subscriber_email')) {
            Mage::helper('tnw_salesforce')->log("SKIPPING: Subscriber object is invalid.");
            return false;
        }
        if ($_status === NULL) {
            Mage::helper('tnw_salesforce')->log("SKIPPING: Unknown subscriber status.");
            return false;
        }

        Mage::helper('tnw_salesforce')->log("###################################### Subscriber Update Start ######################################");

        $_email = strtolower($_subscription->getData('subscriber_email'));
        $_websiteId = Mage::getModel('core/store')->load($_subscription->getData('store_id'))->getWebsiteId();
        $_customerId = ($_subscription->getData('customer_id')) ? $_subscription->getData('customer_id') : '0';
        if ($_customerId != '0') {
            $_customer = Mage::getModel('customer/customer')->load($_customerId);
            $_websiteId = $_customer->getData('website_id');
        }
        // Check for Contact and Account
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup(array($_customerId => $_email), array($_email => $this->_websiteSfIds[$_websiteId]));
        $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup(array($_customerId => $_email), array($_email => $this->_websiteSfIds[$_websiteId]));

        $this->_obj = new stdClass();
        $_id = NULL;
        $_isLead = true;
        $_isContact = false;
        $_isPerson = false;

        if (
            $this->_cache['leadLookup']
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['leadLookup'])
            && array_key_exists($_email, $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]])
        ) {
            // Existing Lead
            $_id = $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->Id;
        }
        if (
            $this->_cache['contactsLookup']
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['contactsLookup'])
            && array_key_exists($_email, $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]])
        ) {
            // Existing Contact
            $_id = $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->Id;
            $_isContact = true;
            $_isLead = false;
            if (
                Mage::helper('tnw_salesforce')->usePersonAccount()
                && property_exists($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email], 'Account')
                && property_exists($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->Account, 'IsPersonAccount')
            ) {
                $_isPerson = true;
            }
        }

        if ($_id) {
            $this->_obj->Id = $_id;
        } elseif ($_type == 'delete') {
            // No lead or a contact in Salesforce, nothing to update
            Mage::helper('tnw_salesforce')->log("SKIPPING: No Lead or Contact in Salesforce, nothing to update.");
            return;
        }

        if ($_subscription->getData('customer_id')) {
            $_customer = Mage::getModel('customer/customer')->load($_subscription->getCustomerId());
            $this->_obj->FirstName = ($_customer->getFirstname()) ? $_customer->getFirstname() : '';
            $this->_obj->LastName = ($_customer->getLastname()) ? $_customer->getLastname() : $_email;
            $_customerId = $_subscription->getCustomerId();
        } else {
            $_name = (is_object(Mage::getSingleton('customer/session')->getCustomer())) ? Mage::getSingleton('customer/session')->getCustomer()->getName() : NULL;
            if (!$_name) {
                // unknown customer, skip
                return;
            }
            $_customerName = explode(' ', $_name);
            $this->_obj->FirstName = (count($_customerName) > 1) ? $_customerName[0] : '';

            $_lastName = $_customerName[1];
            if (count($_customerName) > 1) {
                unset($_customerName[0]);
                $_lastName = join(' ', $_customerName);
            }
            $this->_obj->LastName = $_lastName;
            $_customerId = $_email;
        }
        $this->_obj->HasOptedOutOfEmail = ($_status == 3 || $_type == 'delete') ? 1 : 0;
        $this->_obj->Email = strip_tags($_email);

        // Link to a Website
        if (
            $_websiteId !== NULL
            && array_key_exists($_websiteId, $this->_websiteSfIds)
            && $this->_websiteSfIds[$_websiteId]
        ) {
            $this->_obj->{$this->_prefix . 'Website__c'} = $this->_websiteSfIds[$_websiteId];
        }

        $syncParam = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix() . "disableMagentoSync__c";
        $this->_obj->$syncParam = true;

        if ($_isLead) {
            if (!Mage::helper('tnw_salesforce')->usePersonAccount()) {
                $this->_obj->Company = 'N/A';
            }

            foreach ($this->_obj as $key => $value) {
                Mage::helper('tnw_salesforce')->log("Lead Object: " . $key . " = '" . $value . "'");
            }

            $this->_cache['leadsToUpsert'][$_customerId] = $this->_obj;

            $assignmentRule = Mage::helper('tnw_salesforce')->isLeadRule();
            if (!empty($assignmentRule) && $assignmentRule != "" && $assignmentRule != 0) {
                Mage::helper('tnw_salesforce')->log("Assignment Rule used: " . $assignmentRule);
                $header = new Salesforce_AssignmentRuleHeader($assignmentRule, false);
                $this->_mySforceConnection->setAssignmentRuleHeader($header);
                unset($assignmentRule, $header);
            }

            Mage::dispatchEvent("tnw_salesforce_lead_send_before", array("data" => $this->_cache['leadsToUpsert']));
            $_results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['leadsToUpsert']), 'Lead');
            Mage::dispatchEvent("tnw_salesforce_lead_send_after", array(
                "data" => $this->_cache['leadsToUpsert'],
                "result" => $_results
            ));
            foreach ($_results as $_key => $_result) {
                //Report Transaction
                $this->_cache['responses']['leads'][$_customerId] = $_result;

                if (property_exists($_result, 'success') && $_result->success) {
                    Mage::helper('tnw_salesforce')->log('SUCCESS: Lead upserted (id: ' . $_result->id . ')');
                    $_id = $_result->id;

                    $this->_prepareCampaignMember('LeadId', $_id, $_subscription, $_customerId);
                } else {
                    $this->_processErrors($_result, 'lead', $this->_cache['leadsToUpsert'][$_key]);
                }
            }
        } elseif ($_isContact) {
            if ($_isPerson) {
                $this->_obj->PersonHasOptedOutOfEmail = $this->_obj->HasOptedOutOfEmail;
                unset($this->_obj->HasOptedOutOfEmail);
            }
            foreach ($this->_obj as $key => $value) {
                Mage::helper('tnw_salesforce')->log("Contact Object: " . $key . " = '" . $value . "'");
            }

            $this->_cache['contactsToUpsert'][$_customerId] = $this->_obj;
            $_contactIds = array_keys($this->_cache['contactsToUpsert']);
            Mage::dispatchEvent("tnw_salesforce_contact_send_before", array("data" => $this->_cache['contactsToUpsert']));
            $_results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['contactsToUpsert']), 'Contact');
            Mage::dispatchEvent("tnw_salesforce_contact_send_after", array(
                "data" => $this->_cache['contactsToUpsert'],
                "result" => $_results
            ));
            foreach ($_results as $_key => $_result) {
                //Report Transaction
                $this->_cache['responses']['contacts'][$_customerId] = $_result;

                if (property_exists($_result, 'success') && $_result->success) {
                    Mage::helper('tnw_salesforce')->log('SUCCESS: Contact updated (id: ' . $_result->id . ')');
                    $_id = $_result->id;

                    // create campaign member using campaign id form magento config and id as current contact
                    $this->_prepareCampaignMember('ContactId', $_id, $_subscription, $_customerId);
                } else {
                    $this->_processErrors($_result, 'contact', $this->_cache['contactsToUpsert'][$_contactIds[$_key]]);
                }
            }
        }

        if (!empty($this->_cache['campaignsToUpsert'])) {
            try {
                Mage::dispatchEvent("tnw_salesforce_campaignmember_send_before", array("data" => $this->_cache['campaignsToUpsert']));
                $_results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['campaignsToUpsert']), 'CampaignMember');
                Mage::dispatchEvent("tnw_salesforce_campaignmember_send_after", array(
                    "data" => $this->_cache['campaignsToUpsert'],
                    "result" => $_results
                ));
                foreach ($_results as $_key => $_result) {
                    //Report Transaction
                    $this->_cache['responses']['campaigns'][$_customerId] = $_result;
                }
            } catch (Exception $e) {
                Mage::helper('tnw_salesforce')->log("error [add lead as campaign member to sf failed]: " . $e->getMessage());
            }
        }

        $this->_onComplete();
        Mage::helper('tnw_salesforce')->log("###################################### Subscriber Update End ######################################");
    }

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

    /**
     * @param $_formData
     * @return bool
     */
    public function pushLead($_formData)
    {
        if (Mage::helper('tnw_salesforce')->getType() != "PRO") {
            Mage::helper('tnw_salesforce')->log("IMPORTANT: Skipping form synchronization, please upgrade to Enterprise version!");
            return false;
        }
        if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
            Mage::helper('tnw_salesforce')->log("CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
            return false;
        }

        $logger = Mage::helper('tnw_salesforce/report');
        $logger->reset();

        $_data = $_formData->getData();
        $_email = strtolower($_data['email']);
        $_websiteId = Mage::app()->getWebsite()->getId();
        // Check for Contact and Account
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup(array(0 => $_email), array(0 => $this->_websiteSfIds[$_websiteId]));
        $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup(array(0 => $_email), array(0 => $this->_websiteSfIds[$_websiteId]));

        $this->_obj = new stdClass();
        $_id = NULL;
        if (
            $this->_cache['contactsLookup']
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['contactsLookup'])
            && array_key_exists($_email, $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]])
        ) {
            // Existing Contact
            $_id = $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->Id;
        } else if (
            $this->_cache['leadLookup']
            && array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['leadLookup'])
            && array_key_exists($_email, $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]])
        ) {
            // Existing Lead
            $_id = $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->Id;
        } else {
            $_fullName = explode(' ', strip_tags($_data['name']));
            if (count($_fullName) == 1) {
                $_lastName = NULL;
            } else if (count($_fullName) == 2) {
                $_lastName = $_fullName[1];
            } else {
                unset($_fullName[0]);
                $_lastName = join(' ', $_fullName);
            }

            $this->_obj->FirstName = ($_lastName) ? $_fullName[0] : '';
            $this->_obj->LastName = ($_lastName) ? $_lastName : $_fullName[0];
            $this->_obj->Company = (array_key_exists('company', $_data)) ? strip_tags($_data['company']) : NULL;
            if (!$this->_obj->Company && !Mage::helper('tnw_salesforce')->usePersonAccount()) {
                $this->_obj->Company = 'N/A';
            }
            $this->_obj->Phone = strip_tags($_data['telephone']);
            $this->_obj->Email = strip_tags($_email);

            // Link to a Website
            if (
                $_websiteId !== NULL
                && array_key_exists($_websiteId, $this->_websiteSfIds)
                && $this->_websiteSfIds[$_websiteId]
            ) {
                $this->_obj->{$this->_prefix . 'Website__c'} = $this->_websiteSfIds[$_websiteId];
            }

            $this->_cache['leadsToUpsert']['contactUs'] = $this->_obj;

            $assignmentRule = Mage::helper('tnw_salesforce')->isLeadRule();
            if (!empty($assignmentRule) && $assignmentRule != "" && $assignmentRule != 0) {
                Mage::helper('tnw_salesforce')->log("Assignment Rule used: " . $assignmentRule);
                $header = new Salesforce_AssignmentRuleHeader($assignmentRule, false);
                $this->_mySforceConnection->setAssignmentRuleHeader($header);
                unset($assignmentRule, $header);
            }

            $_keys = array_keys($this->_cache['leadsToUpsert']);
            Mage::dispatchEvent("tnw_salesforce_lead_send_before", array("data" => $this->_cache['leadsToUpsert']));
            $_results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['leadsToUpsert']), 'Lead');
            Mage::dispatchEvent("tnw_salesforce_lead_send_after", array(
                "data" => $this->_cache['leadsToUpsert'],
                "result" => $_results
            ));
            foreach ($_results as $_key => $_result) {
                $this->_cache['responses']['leads']['contactUs'] = $_result;
                if (property_exists($_result, 'success') && $_result->success) {
                    Mage::helper('tnw_salesforce')->log('SUCCESS: Lead upserted (id: ' . $_result->id . ')');
                    $_id = $_result->id;
                } else {
                    $this->_processErrors($_result, 'lead', $this->_cache['leadsToUpsert'][$_keys[$_key]]);
                }
            }

            $logger->add('Salesforce', 'Lead', $this->_cache['leadsToUpsert'], $this->_cache['responses']['leads']);
        }

        if ($_id) {
            // Create a Task
            $this->_obj = new stdClass();
            $this->_obj->WhoId = $_id;
            $this->_obj->Subject = 'Contact form request';
            $this->_obj->Status = 'Not Started';
            $this->_obj->Priority = 'High';
            $this->_obj->Description = strip_tags($_data['comment']);
            if (Mage::helper('tnw_salesforce')->getTaskAssignee() && Mage::helper('tnw_salesforce')->getTaskAssignee() != 0) {
                $this->_obj->OwnerId = Mage::helper('tnw_salesforce')->getTaskAssignee();
            }

            foreach ($this->_obj as $key => $value) {
                Mage::helper('tnw_salesforce')->log("Task Object: " . $key . " = '" . $value . "'");
            }

            Mage::dispatchEvent("tnw_salesforce_task_send_before", array("data" => array($this->_obj)));
            $_results = $this->_mySforceConnection->upsert('Id', array($this->_obj), 'Task');
            Mage::dispatchEvent("tnw_salesforce_task_send_after", array(
                "data" => array($this->_obj),
                "result" => $_results
            ));
            $_sfResult = array();
            foreach ($_results as $_key => $_result) {
                $_sfResult['note'] = $_result;
                if (property_exists($_result, 'success') && $_result->success) {
                    Mage::helper('tnw_salesforce')->log('SUCCESS: Task created (id: ' . $_result->id . ')');
                } else {
                    $this->_processErrors($_result, 'task', $this->_obj);
                }
            }

            $logger->add('Salesforce', 'Note', array('note' => $this->_obj), $_sfResult);
        }
        //Send Transaction Data
        if (Mage::helper('tnw_salesforce')->isRemoteLogEnabled()) {
            $logger->send();
        }
    }

    /**
     * @param bool $_return
     * @return bool|mixed
     */
    public function process($_return = false)
    {
        if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
            Mage::helper('tnw_salesforce')->log("CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addWarning('WARNING: SKIPPING synchronization, could not establish Salesforce connection.');
            }
            return false;
        }
        Mage::helper('tnw_salesforce')->log("================ MASS SYNC: START ================");
        if (!is_array($this->_cache) || empty($this->_cache['entitiesUpdating'])) {
            Mage::helper('tnw_salesforce')->log("WARNING: Sync customers, cache is empty!");
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: SKIPPING synchronization, could not locate customer data to synchronize.');
            }
            return false;
        }

        try {
            // Prepare Data
            $this->_prepareLeads();
            $this->_prepareContacts();
            $this->_prepareNew();
            $this->clearMemory();

            // Push Data
            $this->_pushToSalesforce($_return);
            $this->clearMemory();

            // Update Magento
            if ($this->_customerEntityTypeCode) {
                $this->_updateMagento();
            } else {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Failed to update Magento with Salesforce Ids');
                }
                Mage::helper('tnw_salesforce')->log("WARNING: Failed to update Magento with Salesforce Ids. Try manual synchronization.", 2);
            }

            if ($_return) {
                if (!empty($this->_cache['guestsFromOrder'])) {
                    $currentCustomer = $this->_cache['guestsFromOrder']['guest_0'];
                } else {
                    if ($this->_forcedCustomerId && Mage::registry('customer_cached_' . $this->_forcedCustomerId)) {
                        $currentCustomer = Mage::registry('customer_cached_' . $this->_forcedCustomerId);
                    } else {
                        if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                            Mage::getSingleton('adminhtml/session')->addError('WARNING: Could not locate synced customer from the order, Oportunity Account may end up blank!');
                        }
                        Mage::helper('tnw_salesforce')->log("ERROR: Could not locate synced customer from the order, Oportunity Account may end up blank!");
                        $currentCustomer = false;
                    }
                }
            }
            $this->_onComplete();

            Mage::helper('tnw_salesforce')->log("================= MASS SYNC: END =================");
            if ($_return) {
                return $currentCustomer;
            }
        } catch (Eception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log("CRITICAL: " . $e->getMessage());
        }
    }

    protected function _onComplete()
    {
        parent::_onComplete();

        if (Mage::helper('tnw_salesforce')->isRemoteLogEnabled()) {
            $logger = Mage::helper('tnw_salesforce/report');
            $logger->reset();
            if (array_key_exists('Id', $this->_cache['leadsToUpsert'])) {
                $logger->add('Salesforce', 'Lead', $this->_cache['leadsToUpsert']['Id'], $this->_cache['responses']['leads']);
            }
            if (array_key_exists($this->_magentoId, $this->_cache['leadsToUpsert'])) {
                $logger->add('Salesforce', 'Lead', $this->_cache['leadsToUpsert'][$this->_magentoId], $this->_cache['responses']['leads']);
            }
            if (array_key_exists('Id', $this->_cache['accountsToUpsert'])) {
                $logger->add('Salesforce', 'Account', $this->_cache['accountsToUpsert']['Id'], $this->_cache['responses']['accounts']);
            }
            if (array_key_exists('Id', $this->_cache['contactsToUpsert'])) {
                $logger->add('Salesforce', 'Contact', $this->_cache['contactsToUpsert']['Id'], $this->_cache['responses']['contacts']);
            }
            if (array_key_exists($this->_magentoId, $this->_cache['contactsToUpsert'])) {
                $logger->add('Salesforce', 'Contact', $this->_cache['contactsToUpsert'][$this->_magentoId], $this->_cache['responses']['contacts']);
            }
            if (!empty($this->_cache['campaignsToUpsert'])) {
                $logger->add('Salesforce', 'CampaignMember', $this->_cache['campaignsToUpsert'], $this->_cache['responses']['campaigns']);
            }

            $logger->send();
        }

        $this->reset();
        $this->clearMemory();
    }

    protected function _updateMagento()
    {
        Mage::helper('tnw_salesforce')->log("---------- Start: Magento Update ----------");

        foreach ($this->_cache['toSaveInMagento'] as $_websiteId => $_websiteCustomers) {
            foreach ($_websiteCustomers as $_customer) {
                if (!is_object($_customer) || !property_exists($_customer, 'MagentoId') || !$_customer->MagentoId || strpos($_customer->MagentoId, 'guest_') === 0) {
                    continue;
                }
                $_customer->SalesforceId = (property_exists($_customer, 'SalesforceId')) ? $_customer->SalesforceId : NULL;
                $_customer->AccountId = (property_exists($_customer, 'AccountId')) ? $_customer->AccountId : NULL;
                $_customer->LeadId = (property_exists($_customer, 'LeadId')) ? $_customer->LeadId : NULL;
                $_customer->IsPersonAccount = (property_exists($_customer, 'IsPersonAccount')) ? $_customer->IsPersonAccount : NULL;
                $_customer->SfInSync = (property_exists($_customer, 'SfInSync')) ? $_customer->SfInSync : 0;
                $_customer->FirstName = (property_exists($_customer, 'FirstName')) ? $_customer->FirstName : NULL;
                $_customer->LastName = (property_exists($_customer, 'LastName')) ? $_customer->LastName : NULL;

                $this->updateMagentoEntityValue($_customer->MagentoId, $_customer->SalesforceId, 'salesforce_id');
                $this->updateMagentoEntityValue($_customer->MagentoId, $_customer->AccountId, 'salesforce_account_id');
                $this->updateMagentoEntityValue($_customer->MagentoId, $_customer->LeadId, 'salesforce_lead_id');
                $this->updateMagentoEntityValue($_customer->MagentoId, $_customer->IsPersonAccount, 'salesforce_is_person');
                $this->updateMagentoEntityValue($_customer->MagentoId, $_customer->SfInSync, 'sf_insync', 'customer_entity_int');
                if ($_customer->FirstName) {
                    $this->updateMagentoEntityValue($_customer->MagentoId, $_customer->FirstName, 'firstname');
                }
                if ($_customer->LastName) {
                    $this->updateMagentoEntityValue($_customer->MagentoId, $_customer->LastName, 'lastname');
                }

                if (Mage::registry('customer_cached_' . $_customer->MagentoId)) {
                    Mage::unregister('customer_cached_' . $_customer->MagentoId);
                }
                $_updatedCustomer = Mage::getModel('customer/customer')->load($_customer->MagentoId);
                Mage::register('customer_cached_' . $_customer->MagentoId, $_updatedCustomer);
                $_updatedCustomer = NULL;
                unset($_updatedCustomer);
            }

            Mage::helper('tnw_salesforce')->log("Updated: " . count($_websiteCustomers) . " customers!");
        }

        Mage::helper('tnw_salesforce')->log("---------- End: Magento Update ----------");
    }

    public function updateMagentoEntityValue($_customerId = NULL, $_value = 0, $_attributeName = NULL, $_tableName = 'customer_entity_varchar')
    {
        if (empty($_customerId)) {
            Mage::helper('tnw_salesforce')->log("No magento customer id while updating from salesforce");
            return;
        }
        $_table = Mage::helper('tnw_salesforce')->getTable($_tableName);
        // in case if called from somewhere else
        if (!$this->_write) {
            $this->reset();
        }
        if (!$_attributeName) {
            Mage::helper('tnw_salesforce')->log('Could not update Magento customer values: attribute name is not specified', 1, "sf-errors");
            return false;
        }
        $sql = '';
        if ($_value || $_value === 0) {
            // Update Account Id
            $sqlCheck = "SELECT value_id FROM `" . $_table . "` WHERE attribute_id = " . $this->_attributes[$_attributeName] . " AND entity_id = " . $_customerId;
            $row = $this->_write->query($sqlCheck)->fetch();
            if ($row && array_key_exists('value_id', $row)) {
                //Update
                $sql .= "UPDATE `" . $_table . "` SET value = '" . $_value . "' WHERE value_id = " . $row['value_id'] . ";";
            } else {
                // Insert
                $sql .= "INSERT INTO `" . $_table . "` VALUES (NULL," . $this->_customerEntityTypeCode . "," . $this->_attributes[$_attributeName] . "," . $_customerId . ",'" . $_value . "');";
            }
        } else {
            // Reset value
            $sqlCheck = "SELECT value_id FROM `" . $_table . "` WHERE attribute_id = " . $this->_attributes[$_attributeName] . " AND entity_id = " . $_customerId;
            $row = $this->_write->query($sqlCheck)->fetch();
            if ($row && array_key_exists('value_id', $row)) {
                //Update
                $sql .= "DELETE FROM `" . $_table . "` WHERE value_id = " . $row['value_id'] . ";";
            }
        }
        if (!empty($sql)) {
            Mage::helper('tnw_salesforce')->log("SQL: " . $sql);
            $this->_write->query($sql);
        }
    }

    protected function _pushToSalesforce($_isOrder)
    {
        if (!empty($this->_cache['accountsToUpsert']['Id'])) {
            Mage::helper('tnw_salesforce')->log("---------- Start: Account Sync ----------");
            $this->_dumpObjectToLog($this->_cache['accountsToUpsert']['Id'], 'Account');
            // Accounts upsert
            $_pushOn = 'Id';

            $_contactIds = array_keys($this->_cache['accountsToUpsert']['Id']);
            try {
                Mage::dispatchEvent("tnw_salesforce_account_send_before", array("data" => $this->_cache['accountsToUpsert']['Id']));
                $_results = $this->_mySforceConnection->upsert($_pushOn, array_values($this->_cache['accountsToUpsert']['Id']), 'Account');
                Mage::dispatchEvent("tnw_salesforce_account_send_after", array(
                    "data" => $this->_cache['accountsToUpsert']['Id'],
                    "result" => $_results
                ));
            } catch (Exception $e) {
                $_response = $this->_buildErrorResponse($e->getMessage());
                foreach ($_contactIds as $_id) {
                    $this->_cache['responses']['accounts'][$_id] = $_response;
                }
                $_results = array();
                Mage::helper('tnw_salesforce')->log('CRITICAL: Push of accounts to SalesForce failed' . $e->getMessage());
            }

            $_entitites = array();

            foreach ($_results as $_key => $_result) {
                //Report Transaction
                $this->_cache['responses']['accounts'][$_contactIds[$_key]] = $_result;

                if (property_exists($_result, 'success') && $_result->success) {
                    $_entitites[] = $_result->id;
                    if (array_key_exists('guest_0', $this->_cache['guestsFromOrder'])) {
                        $this->_cache['guestsFromOrder']['guest_0']->setSalesforceAccountId($_result->id);
                    }

                    $_email = $this->_cache['entitiesUpdating'][$_contactIds[$_key]];
                    $_websiteId = $this->_getWebsiteIdByCustomerId($_contactIds[$_key]);
                    if (
                        array_key_exists($_contactIds[$_key], $this->_cache['accountsToUpsert']['Id'])
                        && !property_exists($this->_cache['accountsToUpsert']['Id'][$_contactIds[$_key]], 'PersonEmail')
                    ) {
                        if (array_key_exists('guest_0', $this->_cache['guestsFromOrder'])) {
                            $this->_cache['contactsToUpsert']['Id']['guest_0']->AccountId = $_result->id;
                        }
                        foreach ($this->_cache['contactsToUpsert'] as $_id => $_objects) {
                            if (array_key_exists($_contactIds[$_key], $_objects)) {
                                $this->_cache['contactsToUpsert'][$_id][$_contactIds[$_key]]->AccountId = $_result->id;
                            }
                        }
                    }
                    if (
                        array_key_exists($_contactIds[$_key], $this->_cache['accountsToUpsert']['Id'])
                        && property_exists($this->_cache['accountsToUpsert']['Id'][$_contactIds[$_key]], 'PersonEmail')
                    ) {
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = $_result->id;
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount = 1;
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 1;
                    }
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = $_result->id;
                } else {
                    $this->_processErrors($_result, 'account', $this->_cache['accountsToUpsert']['Id'][$_contactIds[$_key]]);
                    //Force Skip Contact Update
                    $this->_cache['contactsToUpsert']['Id'] = array();
                    $this->_cache['contactsToUpsert'][$this->_magentoId] = array();
                }
            }
            Mage::helper('tnw_salesforce')->log("Accounts: " . implode(',', $_entitites) . " upserted!");
            Mage::helper('tnw_salesforce')->log("---------- End: Account Sync ----------");
        }

        // On Id
        if (!empty($this->_cache['contactsToUpsert']['Id'])) {
            Mage::helper('tnw_salesforce')->log("---------- Start: Contact Sync ----------");
            $this->_dumpObjectToLog($this->_cache['contactsToUpsert']['Id'], 'Contact');
            Mage::helper('tnw_salesforce')->log("Contacts: on Id");

            $_contactIds = array_keys($this->_cache['contactsToUpsert']['Id']);
            try {
                Mage::dispatchEvent("tnw_salesforce_contact_send_before", array("data" => $this->_cache['contactsToUpsert']['Id']));
                $_results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['contactsToUpsert']['Id']), 'Contact');
                Mage::dispatchEvent("tnw_salesforce_contact_send_after", array(
                    "data" => $this->_cache['contactsToUpsert']['Id'],
                    "result" => $_results
                ));
            } catch (Exception $e) {
                $_response = $this->_buildErrorResponse($e->getMessage());
                foreach ($_contactIds as $_id) {
                    $this->_cache['responses']['contacts'][$_id] = $_response;
                }
                $_results = array();
                Mage::helper('tnw_salesforce')->log('CRITICAL: Push of contact to SalesForce failed' . $e->getMessage());
            }
            $_entitites = array();

            foreach ($_results as $_key => $_result) {
                //Report Transaction
                $this->_cache['responses']['contacts'][$_contactIds[$_key]] = $_result;

                if (property_exists($_result, 'success') && $_result->success) {
                    $_entitites[] = $_result->id;
                    if (array_key_exists('guest_0', $this->_cache['guestsFromOrder'])) {
                        $this->_cache['guestsFromOrder']['guest_0']->setSalesforceId($_result->id);
                    }
                    $_email = $this->_cache['entitiesUpdating'][$_contactIds[$_key]];
                    $_websiteId = $this->_getWebsiteIdByCustomerId($_contactIds[$_key]);
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 1;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = $_result->id;

                    if (
                        !property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'AccountId') ||
                        !$this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId
                    ) {
                        if (!property_exists($_result, $this->_prefix . 'Website__c')) {
                            $websiteKey = $_result->{$this->_prefix . 'Website__c'};
                        } else {
                            $websiteKey = 0;
                        }
                        //Check in Lookup
                        if (
                            array_key_exists($websiteKey, $this->_cache['contactsLookup']) &&
                            array_key_exists($_email, $this->_cache['contactsLookup'][$websiteKey]) &&
                            property_exists($this->_cache['contactsLookup'][$websiteKey][$_email], 'AccountId')
                        ) {
                            // Updating contacts with same account,
                            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = $this->_cache['contactsLookup'][$websiteKey][$_email]->AccountId;
                        } else if (
                            array_key_exists($_contactIds[$_key], $this->_cache['accountsToUpsert']['Id']) &&
                            property_exists($this->_cache['accountsToUpsert']['Id'][$_contactIds[$_key]], 'AccountId')
                        ) {
                            $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = $this->_cache['accountsToUpsert']['Id'][$_contactIds[$_key]]->AccountId;
                        }
                    }
                } else {
                    $this->_processErrors($_result, 'contact', $this->_cache['contactsToUpsert'][$_contactIds[$_key]]);
                }
            }

            Mage::helper('tnw_salesforce')->log("Contacts: " . implode(',', $_entitites) . " upserted!");
            Mage::helper('tnw_salesforce')->log("---------- End: Contact Sync ----------");
        }

        // On Magento Id
        if (!empty($this->_cache['contactsToUpsert'][$this->_magentoId])) {
            Mage::helper('tnw_salesforce')->log("---------- Start: Contact Sync ----------");
            $this->_dumpObjectToLog($this->_cache['contactsToUpsert'][$this->_magentoId], 'Contact');

            Mage::helper('tnw_salesforce')->log("Contacts: on " . $this->_magentoId);

            $_contactIds = array_keys($this->_cache['contactsToUpsert'][$this->_magentoId]);
            try {
                Mage::dispatchEvent("tnw_salesforce_contact_send_before", array("data" => $this->_cache['contactsToUpsert'][$this->_magentoId]));
                $_results = $this->_mySforceConnection->upsert($this->_magentoId, array_values($this->_cache['contactsToUpsert'][$this->_magentoId]), 'Contact');
                Mage::dispatchEvent("tnw_salesforce_contact_send_after", array(
                    "data" => $this->_cache['contactsToUpsert'][$this->_magentoId],
                    "result" => $_results
                ));
            } catch (Exception $e) {
                $_response = $this->_buildErrorResponse($e->getMessage());
                foreach ($_contactIds as $_id) {
                    $this->_cache['responses']['contacts'][$_id] = $_response;
                }
                $_results = array();
                Mage::helper('tnw_salesforce')->log('CRITICAL: Push of contact to SalesForce failed' . $e->getMessage());
            }
            $_entitites = array();

            foreach ($_results as $_key => $_result) {
                //Report Transaction
                $this->_cache['responses']['contacts'][$_contactIds[$_key]] = $_result;
                $_websiteId = $this->_getWebsiteIdByCustomerId($_contactIds[$_key]);

                if (property_exists($_result, 'success') && $_result->success) {
                    $_entitites[] = $_result->id;
                    if (array_key_exists('guest_0', $this->_cache['guestsFromOrder'])) {
                        $this->_cache['guestsFromOrder']['guest_0']->setSalesforceId($_result->id);
                    }
                    $_email = $this->_cache['entitiesUpdating'][$_contactIds[$_key]];
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = $_result->id;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 1;
                    // Skip Magento update if guest
                    if (array_key_exists('guest_0', $this->_cache['guestsFromOrder'])) {
                        unset($this->_cache['toSaveInMagento'][$_websiteId][$_email]);
                    }
                } else {
                    $this->_processErrors($_result, 'contact', $this->_cache['contactsToUpsert'][$_contactIds[$_key]]);
                }
            }

            Mage::helper('tnw_salesforce')->log("Contacts: " . implode(',', $_entitites) . " upserted!");
            Mage::helper('tnw_salesforce')->log("---------- End: Contact Sync ----------");
        }

        // On Magento ID
        if (!empty($this->_cache['leadsToUpsert'][$this->_magentoId])) {
            // Lead Sync
            Mage::helper('tnw_salesforce')->log("---------- Start: Lead Sync ----------");
            $this->_dumpObjectToLog($this->_cache['leadsToUpsert'][$this->_magentoId], 'Lead');

            $assignmentRule = Mage::helper('tnw_salesforce')->isLeadRule();
            if (!empty($assignmentRule) && $assignmentRule != "" && $assignmentRule != 0) {
                Mage::helper('tnw_salesforce')->log("Assignment Rule used: " . $assignmentRule);
                $header = new Salesforce_AssignmentRuleHeader($assignmentRule, false);
                $this->_mySforceConnection->setAssignmentRuleHeader($header);
                unset($assignmentRule, $header);
            }

            $_contactIds = array_keys($this->_cache['leadsToUpsert'][$this->_magentoId]);
            try {
                Mage::dispatchEvent("tnw_salesforce_lead_send_before", array("data" => $this->_cache['leadsToUpsert'][$this->_magentoId]));
                $_results = $this->_mySforceConnection->upsert($this->_magentoId, array_values($this->_cache['leadsToUpsert'][$this->_magentoId]), 'Lead');
                Mage::dispatchEvent("tnw_salesforce_lead_send_after", array(
                    "data" => $this->_cache['leadsToUpsert'][$this->_magentoId],
                    "result" => $_results
                ));
            } catch (Exception $e) {
                $_response = $this->_buildErrorResponse($e->getMessage());
                foreach ($_contactIds as $_id) {
                    $this->_cache['responses']['leads'][$_id] = $_response;
                }
                $_results = array();
                Mage::helper('tnw_salesforce')->log('CRITICAL: Push of contact to SalesForce failed' . $e->getMessage());
            }
            $_entitites = array();

            foreach ($_results as $_key => $_result) {
                //Report Transaction
                $this->_cache['responses']['leads'][$_contactIds[$_key]] = $_result;
                $_websiteId = $this->_getWebsiteIdByCustomerId($_contactIds[$_key]);

                if (property_exists($_result, 'success') && $_result->success) {
                    $_entitites[] = $_result->id;
                    $_email = $this->_cache['entitiesUpdating'][$_contactIds[$_key]];
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId = $_result->id;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 1;
                } else {
                    $this->_processErrors($_result, 'lead', $this->_cache['leadsToUpsert'][$_contactIds[$_key]]);
                }
            }
            Mage::helper('tnw_salesforce')->log("Leads: " . implode(',', $_entitites) . " upserted!");
            Mage::helper('tnw_salesforce')->log("---------- End: Lead Sync ----------");
        }

        // On Id
        if (!empty($this->_cache['leadsToUpsert']['Id'])) {
            // Lead Sync
            Mage::helper('tnw_salesforce')->log("---------- Start: Lead Sync ----------");
            $this->_dumpObjectToLog($this->_cache['leadsToUpsert']['Id'], 'Lead');

            $assignmentRule = Mage::helper('tnw_salesforce')->isLeadRule();
            if (!empty($assignmentRule) && $assignmentRule != "" && $assignmentRule != 0) {
                Mage::helper('tnw_salesforce')->log("Assignment Rule used: " . $assignmentRule);
                $header = new Salesforce_AssignmentRuleHeader($assignmentRule, false);
                $this->_mySforceConnection->setAssignmentRuleHeader($header);
                unset($assignmentRule, $header);
            }

            try {
                Mage::dispatchEvent("tnw_salesforce_lead_send_before", array("data" => $this->_cache['leadsToUpsert']['Id']));
                $_results = $this->_mySforceConnection->upsert('Id', array_values($this->_cache['leadsToUpsert']['Id']), 'Lead');
                Mage::dispatchEvent("tnw_salesforce_lead_send_after", array(
                    "data" => $this->_cache['leadsToUpsert']['Id'],
                    "result" => $_results
                ));
            } catch (Exception $e) {
                $_response = $this->_buildErrorResponse($e->getMessage());
                foreach ($_contactIds as $_id) {
                    $this->_cache['responses']['leads'][$_id] = $_response;
                }
                $_results = array();
                Mage::helper('tnw_salesforce')->log('CRITICAL: Push of contact to SalesForce failed' . $e->getMessage());
            }

            $_entitites = array();
            $_contactIds = array_keys($this->_cache['leadsToUpsert']['Id']);
            foreach ($_results as $_key => $_result) {
                //Report Transaction
                $this->_cache['responses']['leads'][$_contactIds[$_key]] = $_result;
                $_websiteId = $this->_getWebsiteIdByCustomerId($_contactIds[$_key]);

                if (property_exists($_result, 'success') && $_result->success) {
                    $_entitites[] = $_result->id;
                    $_email = $this->_cache['entitiesUpdating'][$_contactIds[$_key]];
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadId = $_result->id;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 1;

                    if (array_key_exists('guest_0', $this->_cache['guestsFromOrder'])) {
                        $this->_cache['guestsFromOrder']['guest_0']->setSalesforceLeadId($_result->id);
                        unset($this->_cache['toSaveInMagento'][$_websiteId][$_email]); // Skip save in Magento for a guest
                    }
                } else {
                    $this->_processErrors($_result, 'lead', $this->_cache['leadsToUpsert'][$_contactIds[$_key]]);
                }
            }
            Mage::helper('tnw_salesforce')->log("Leads: " . implode(',', $_entitites) . " upserted!");
            Mage::helper('tnw_salesforce')->log("---------- End: Lead Sync ----------");
        }
    }

    protected function _prepareLeads()
    {
        // Existing Leads
        if (!empty($this->_cache['leadLookup'])) {
            foreach ($this->_cache['leadLookup'] as $_salesforceWebsiteId => $websiteLeads) {
                $_websiteId = array_search($_salesforceWebsiteId, $this->_websiteSfIds);
                foreach ($websiteLeads as $_email => $_info) {
                    // Just in case Salesforce did not save Magento ID for some reason
                    if (
                        !$_info->MagentoId &&
                        is_array($this->_cache['toSaveInMagento']) &&
                        array_key_exists($_websiteId, $this->_cache['toSaveInMagento']) &&
                        array_key_exists($_email, $this->_cache['toSaveInMagento'][$_websiteId])
                    ) {
                        $_info->MagentoId = $this->_cache['toSaveInMagento'][$_websiteId][$_email]->MagentoId;
                    }
                    if (Mage::helper('tnw_salesforce')->isCustomerAsLead() && !$_info->IsConverted) {
                        $this->_addToQueue($_info->MagentoId, "Lead");
                    } else {
                        if ($_info->IsConverted) {
                            // TODO: Delete Lead?!
                        }
                        // Changed order so that we can capture account owner: Account then Contact
                        $this->_addToQueue($_info->MagentoId, "Account");
                        $this->_addToQueue($_info->MagentoId, "Contact");
                    }
                }
            }
        }
    }

    /**
     *
     */
    protected function _prepareLeadConversionObject($leadData)
    {
        $leadData->convertedStatus = Mage::helper("tnw_salesforce")->getLeadConvertedStatus();
        $leadData->leadId= $leadData->Id;
        $leadData->doNotCreateOpportunity = 'true';
        $leadData->overwriteLeadSource = 'false';
        $leadData->sendNotificationEmail = 'false';

        return $leadData;
    }

    protected function _prepareNew()
    {

        $leadsToConvert = $this->_cache['leadsToConvert'];

        $leadsToConvertChunks = array_chunk($leadsToConvert, TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);

        foreach ($leadsToConvertChunks as $leadsToConvertChunk) {

            $_results = $this->_mySforceConnection->convertLead(array_values($leadsToConvertChunk));
            foreach ($_results as $_key => $_resultsArray) {
                foreach ($_resultsArray as $_result) {
                    if (!property_exists($_result, 'success') || !(int)$_result->success ) {
                        $this->_processErrors($_result, 'lead');
                    } else {
                        $_customerId = $leadsToConvertChunk[$_result->leadId]->MagentoId;
                        $_customerEmail = $leadsToConvertChunk[$_result->leadId]->Email;

                        $_websiteId = $this->_getWebsiteIdByCustomerId($_customerId);

                        unset($this->_cache['toSaveInMagento'][$_websiteId][$_customerEmail]);

                        // Update Salesforce Id
                        Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, $_result->contactId, 'salesforce_id');
                        // Update Account Id
                        Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, $_result->accountId, 'salesforce_account_id');
                        // Update Lead
                        Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, $_result->leadId, 'salesforce_lead_id');
                        // Update Sync Status
                        Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, 1, 'sf_insync', 'customer_entity_int');


                    }
                }
            }
        }


        if (!empty($this->_cache['notFoundCustomers'])) {
            foreach ($this->_cache['notFoundCustomers'] as $_id => $_email) {
                // Check if new customers need to be added as a Lead or Contact
                if (
                    Mage::helper('tnw_salesforce')->isCustomerAsLead()
                    && (!array_keys($this->_customerAccounts, $_email) || !$this->_customerAccounts[$_email])
                ) {
                    $this->_addToQueue($_id, "Lead");
                } else {

                        // Changed order so that we can capture account owner: Account then Contact
                        $this->_addToQueue($_id, "Account");
                        $this->_addToQueue($_id, "Contact");
                }
            }
        }
    }

    protected function _prepareContacts()
    {
        if (!empty($this->_cache['contactsLookup'])) {
            foreach ($this->_cache['contactsLookup'] as $_salesforceWebsiteId => $_accounts) {
                $_websiteId = array_search($_salesforceWebsiteId, $this->_websiteSfIds);
                foreach ($_accounts as $_email => $_info) {
                    if (
                        !$_info->MagentoId &&
                        is_array($this->_cache['toSaveInMagento']) &&
                        array_key_exists($_websiteId, $this->_cache['toSaveInMagento']) &&
                        array_key_exists($_email, $this->_cache['toSaveInMagento'][$_websiteId])
                    ) {
                        $_info->MagentoId = $this->_cache['toSaveInMagento'][$_websiteId][$_email]->MagentoId;
                    }
                    if (
                        array_key_exists($_websiteId, $this->_cache['toSaveInMagento'])
                        && array_key_exists($_email, $this->_cache['toSaveInMagento'][$_websiteId])
                        && $this->_cache['toSaveInMagento'][$_websiteId][$_email]->MagentoId
                        && $this->_cache['toSaveInMagento'][$_websiteId][$_email]->MagentoId != $_info->MagentoId
                    ) {
                        $_info->MagentoId = $this->_cache['toSaveInMagento'][$_websiteId][$_email]->MagentoId;
                    }

                    // Changed order so that we can capture account owner: Account then Contact
                    $this->_addToQueue($_info->MagentoId, "Account");
                    $this->_addToQueue($_info->MagentoId, "Contact");
                }
            }
        }
    }

    protected function _addToQueue($_id, $type = "Lead")
    {
        $_customer = NULL;

        if ($_id) {
            if (strpos($_id, 'guest_') === 0) {
                $_upsertOn = 'Id';
                $_customer = $this->_cache['guestsFromOrder'][$_id];
            } else {
                $_upsertOn = $this->_magentoId;
                $_customer = Mage::registry('customer_cached_' . $_id);
            }
        } else {
            return;
        }
        if (!$_customer) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: Could not add customer to Lead queue, could not load customer by ID (' . $_id . ') from Magento.');
            }
            Mage::helper('tnw_salesforce')->log("Could not add customer to Lead queue, could not load customer by ID (" . $_id . ") from Magento.", 1, "sf-errors");
            return;
        }
        $this->_obj = new stdClass();
        //Resetting all values first
        $_customer->setSalesforceId(NULL);
        $_customer->setSalesforceAccountId(NULL);
        $_customer->setSalesforceLeadId(NULL);
        $_customer->setSalesforceIsPerson(NULL);

        // Get Customer Website Id
        $_websiteId = ($_customer->getData('website_id') != NULL) ? $_customer->getData('website_id') : NULL;

        //If Lookup returned values add them
        $_email = strtolower($_customer->getEmail());
        if (array_key_exists($_websiteId, $this->_websiteSfIds)) {
            $_sfWebsite = $this->_websiteSfIds[$_websiteId];
        } else {
            $_sfWebsite = 0;
        }

        if ($this->_cache['contactsLookup'] && array_key_exists($_sfWebsite, $this->_cache['contactsLookup']) && array_key_exists($_email, $this->_cache['contactsLookup'][$_sfWebsite])) {
            $_customer->setSalesforceId($this->_cache['contactsLookup'][$_sfWebsite][$_email]->Id);
            $_customer->setSalesforceAccountId($this->_cache['contactsLookup'][$_sfWebsite][$_email]->AccountId);
            if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                if (!property_exists($this->_cache['contactsLookup'][$_sfWebsite][$_email], 'IsPersonAccount')) {
                    $_customer->setSalesforceIsPerson(false);
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount = false;
                } else {
                    $_customer->setSalesforceIsPerson($this->_cache['contactsLookup'][$_sfWebsite][$_email]->IsPersonAccount);
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount = $this->_cache['contactsLookup'][$_sfWebsite][$_email]->IsPersonAccount;
                }
            }
        }
        if ($this->_cache['leadLookup'] && array_key_exists($_sfWebsite, $this->_cache['leadLookup']) && array_key_exists($_email, $this->_cache['leadLookup'])) {
            $_customer->setSalesforceLeadId($this->_cache['leadLookup'][$_sfWebsite][$_email]->Id);
        }

        $_ownerID = NULL;
        if (Mage::helper('tnw_salesforce')->getDefaultOwner()) {
            $this->_obj->OwnerId = Mage::helper('tnw_salesforce')->getDefaultOwner();
        }

        if ($type == "Contact" || $type == "Account") {
            $_ownerID = (
                is_array($this->_cache['contactsLookup'])
                && array_key_exists($_sfWebsite, $this->_cache['contactsLookup'])
                && array_key_exists($_email, $this->_cache['contactsLookup'][$_sfWebsite])
                && property_exists($this->_cache['contactsLookup'][$_sfWebsite][$_email], 'OwnerId')
            ) ? $this->_cache['contactsLookup'][$_sfWebsite][$_email]->OwnerId : NULL;
            if ($_ownerID) {
                Mage::helper('tnw_salesforce')->log($type . " record already assigned to " . $_ownerID);
            } else {
                $_ownerID = (
                    is_array($this->_cache['contactsLookup'])
                    && array_key_exists($_sfWebsite, $this->_cache['contactsLookup'])
                    && array_key_exists($_email, $this->_cache['contactsLookup'][$_sfWebsite])
                    && property_exists($this->_cache['contactsLookup'][$_sfWebsite][$_email], 'Account')
                    && property_exists($this->_cache['contactsLookup'][$_sfWebsite][$_email]->Account, 'OwnerId')
                ) ? $this->_cache['contactsLookup'][$_sfWebsite][$_email]->Account->OwnerId : NULL;
                if ($_ownerID) {
                    Mage::helper('tnw_salesforce')->log($type . " record already assigned to " . $_ownerID . ' - taken from the account');
                }
            }
        } else if ($type == "Lead") {
            $_ownerID = (
                !empty($this->_cache['leadLookup'])
                && array_key_exists($_sfWebsite, $this->_cache['leadLookup'])
                && array_key_exists($_email, $this->_cache['leadLookup'][$_sfWebsite])
                && is_object($this->_cache['leadLookup'][$_sfWebsite][$_email])
                && property_exists($this->_cache['leadLookup'][$_sfWebsite][$_email], 'OwnerId')
            ) ? $this->_cache['leadLookup'][$_sfWebsite][$_email]->OwnerId : NULL;
            if ($_ownerID) {
                Mage::helper('tnw_salesforce')->log($type . " record already assigned to " . $_ownerID);
            }
        }
        if ($_ownerID) {
            if (!$this->_isUserActive($_ownerID)) {
                $_ownerID = Mage::helper('tnw_salesforce')->getDefaultOwner();
                Mage::helper('tnw_salesforce')->log("Owner record is inactive - reverting back to default.");
            } else {
                Mage::helper('tnw_salesforce')->log("Owner record is active - good to go ...");
            }

            $this->_obj->OwnerId = $_ownerID;
        }

        // Process custom mappings
        $this->_processMapping($_customer, $type);

        // Link to a Website
        if (
            $_websiteId !== NULL
            && array_key_exists($_websiteId, $this->_websiteSfIds)
            && $this->_websiteSfIds[$_websiteId]
        ) {
            $this->_obj->{$this->_prefix . 'Website__c'} = $this->_websiteSfIds[$_websiteId];
        }

        // Add to queue
        if ($type == "Lead") {
            if ($this->_cache['leadLookup']
                && array_key_exists($_sfWebsite, $this->_cache['leadLookup'])
                && array_key_exists($_email, $this->_cache['leadLookup'][$_sfWebsite])
            ) {
                $this->_obj->Id = $this->_cache['leadLookup'][$_sfWebsite][$_email]->Id;
                $_upsertOn = 'Id';
            }

            $this->_cache['leadsToUpsert'][$_upsertOn][$_id] = $this->_obj;
        } else if ($type == "Contact") {
            // Set Contact AccountId as suggested by Advanced Lookup
            if (
                array_key_exists($_email, $this->_customerAccounts)
                && !$_customer->getSalesforceIsPerson()
            ) {
                $this->_obj->AccountId = $this->_customerAccounts[$_email];
                $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = $this->_obj->AccountId;
            }
            if (
                $this->_cache['contactsLookup']
                && array_key_exists($_sfWebsite, $this->_cache['contactsLookup'])
                && array_key_exists($_email, $this->_cache['contactsLookup'][$_sfWebsite])
                && property_exists($this->_cache['contactsLookup'][$_sfWebsite][$_email], 'Id')
            ) {
                $this->_obj->Id = $this->_cache['contactsLookup'][$_sfWebsite][$_email]->Id;
                $_upsertOn = 'Id';
            }

            $this->_cache['contactsToUpsert'][$_upsertOn][$_id] = $this->_obj;
        } else if ($type == "Account") {
            if (
                property_exists($this->_obj, 'RecordTypeId')
                && $this->_obj->RecordTypeId == Mage::helper('tnw_salesforce')->getPersonAccountRecordType()
            ) {
                // This is Person Account

                // Move the prepared Contact data to Person Account
                if (
                    array_key_exists('Id', $this->_cache['contactsToUpsert'])
                    && array_key_exists($_id, $this->_cache['contactsToUpsert']['Id'])
                ) {
                    foreach ($this->_cache['contactsToUpsert']['Id'][$_id] as $_key => $_value) {
                        if (!property_exists($this->_obj, $_key)) {
                            $this->_obj->{$_key} = $_value;
                        }
                    }
                }

                $this->_fixPersonAccountFields();
                if (
                    array_key_exists('Id', $this->_cache['contactsToUpsert'])
                    && array_key_exists($_id, $this->_cache['contactsToUpsert']['Id'])
                ) {
                    unset($this->_cache['contactsToUpsert']['Id'][$_id]);
                }
                if (
                    array_key_exists($this->_magentoId, $this->_cache['contactsToUpsert'])
                    && array_key_exists($_id, $this->_cache['contactsToUpsert'][$this->_magentoId])
                ) {
                    unset($this->_cache['contactsToUpsert'][$this->_magentoId][$_id]);
                }
            } else {
                // This is a B2B Account
                unset($this->_obj->{$this->_prefix . 'Website__c'});
                unset($this->_obj->{$this->_prefix . 'Magento_ID__c'});

                // Remove subscription flag from B2B Account
                if (property_exists($this->_obj, 'HasOptedOutOfEmail')) {
                    unset($this->_obj->HasOptedOutOfEmail);
                }
            }

            // Check if duplicate exists
            $_found = false;
            foreach ($this->_cache['accountsToUpsert']['Id'] as $_account) {
                if (
                    property_exists($_account, 'Id')
                    && property_exists($this->_obj, 'Id')
                    && $_account->Id == $this->_obj->Id
                ) {
                    $_found = true;
                    // Need to update 'accountsToContactLink'
                }
            }

            // Skip Account upsert if Advanced lookup is suggesting to use existing account
            if (array_key_exists($_email, $this->_customerAccounts)) {
                $_found = true;
            }

            // Skip duplicate account for one of the contacts
            if (!$_found) {
                // Make sure Name of the Account is taken from Salesforce, if found
                // only if configuration tells us NOT to overwrite the Account Name
                if (
                    !Mage::helper('tnw_salesforce')->canRenameAccount()
                    && property_exists($this->_obj, 'Name')
                ) {
                    $this->_obj->Name = $this->_getAccountName($this->_obj->Name, $_email, $_sfWebsite);
                }
                $this->_cache['accountsToUpsert']['Id'][$_id] = $this->_obj;
            }
            if (property_exists($this->_obj, 'Id')) {
                $this->_cache['accountsToContactLink'][$_id] = $this->_obj->Id;
                //$this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = $this->_obj->Id;
            }

            // Check if Account Name is empty
            if (
                property_exists($this->_obj, 'Name')
                && empty($this->_obj->Name)
            ) {
                if ($_customer->getData('company')) {
                    $this->_obj->Name = $_customer->getData('company');
                } else if ($_customer->getFirstname() && $_customer->getLastname()) {
                    $this->_obj->Name = $_customer->getFirstname() . ' ' . $_customer->getLastname();
                }
                if (!empty($this->_obj->Name)) {
                    $this->_cache['accountsToUpsert']['Id'][$_id] = $this->_obj;
                }
            }
        }
    }

    protected function _getAccountName($_name, $_email = NULL, $_sfWebsite = NULL)
    {
        if ($_email) {
            if ($_sfWebsite) {
                if (
                    is_array($this->_cache['contactsLookup'])
                    && array_key_exists($_sfWebsite, $this->_cache['contactsLookup'])
                    && array_key_exists($_email, $this->_cache['contactsLookup'][$_sfWebsite])
                    && !empty($this->_cache['contactsLookup'][$_sfWebsite][$_email]->AccountName)
                ) {
                    $_name = $this->_cache['contactsLookup'][$_sfWebsite][$_email]->AccountName;
                }
            }
        }
        return $_name;
    }

    public function forceAdd($_customer)
    {
        try {
            $_emailsArray = array();
            $tmp = new stdClass();
            $_email = strtolower($_customer->getEmail());

            if (!$_customer->getId()) {
                $_customerId = 'guest_0';
                $this->_cache['guestsFromOrder']['guest_0'] = $_customer;
                $_emailsArray['guest_0'] = $_email;
                $tmp->MagentoId = 0;
            } else {
                $_customerId = $_customer->getId();
                $this->_forcedCustomerId = $_customer->getId();
                $tmp->MagentoId = $_customer->getId();
                $_emailsArray[$_customer->getId()] = $_email;
                if (!Mage::registry('customer_cached_' . $_customer->getId())) {
                    Mage::register('customer_cached_' . $_customer->getId(), $_customer);
                }
            }

            $_websiteId = ($_customer->getData('website_id') != NULL) ? $_customer->getData('website_id') : Mage::app()->getWebsite()->getId();

            // Lookup existing Contacts & Accounts
            $tmp->Email = $_email;
            $tmp->SfInSync = 0;
            $this->_cache['toSaveInMagento'][$_websiteId][$_email] = $tmp;

            $this->_cache['entitiesUpdating'] = $_emailsArray;
            $this->_customerAccounts = Mage::helper('tnw_salesforce/salesforce_data')->accountLookupByEmailDomain($_emailsArray);
            $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emailsArray, array($_customerId => $this->_websiteSfIds[$_websiteId]));

            foreach ($_emailsArray as $_key => $_email) {
                if (
                    $this->_cache['contactsLookup'] &&
                    array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['contactsLookup']) &&
                    array_key_exists($_email, $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]]) &&
                    ($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId == $_key || !$this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId)
                ) {
                    unset($_emailsArray[$_key]);
                }
            }
            // Lookup existing Leads
            if (!empty($_emailsArray)) {
                $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($_emailsArray, array($_customerId => $this->_websiteSfIds[$_websiteId]));
            }

            foreach ($_emailsArray as $_key => $_email) {
                if (
                    $this->_cache['leadLookup'] &&
                    array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['leadLookup']) &&
                    array_key_exists(strtolower($_email), $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]]) &&
                    ($this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId == $_key || !$this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId) &&
                    !$this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->IsConverted
                ) {
                    unset($_emailsArray[$_key]);
                }
            }

            $this->_cache['notFoundCustomers'] = $_emailsArray;
        } catch (Eception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log("CRITICAL: " . $e->getMessage());
        }
    }

    /**
     * @param array $ids
     */
    public function massAdd($ids = array())
    {
        try {
            // Lookup existing Contacts & Accounts
            $_emailsArray = array();
            $_companies = array();

            $_collection = Mage::getModel('customer/customer')
                ->getCollection()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('entity_id', array('in' => $ids))
                ->load();

            $_websites = array();

            foreach ($_collection as $_customer) {
                if (!$_customer->getEmail() || !$_customer->getId()) {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Sync for customer #' . $_customer->getId() . ', customer could not be loaded!');
                    }
                    Mage::helper("tnw_salesforce")->log("SKIPPING: Sync for customer #" . $_customer->getId() . ", customer could not be loaded!");
                    continue;
                }
                if (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($_customer->getGroupId())) {
                    Mage::helper("tnw_salesforce")->log("SKIPPING: Sync for customer group #" . $_customer->getGroupId() . " is disabled!");
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for customer #' . $_customer->getId() . ', sync for customer group #' . $_customer->getGroupId() . ' is disabled!');
                    }
                    continue;
                }

                if (!Mage::registry('customer_cached_' . $_customer->getId())) {
                    Mage::register('customer_cached_' . $_customer->getId(), $_customer);
                }

                $_email = strtolower($_customer->getEmail());
                $_emailsArray[$_customer->getId()] = $_email;

                /**
                 * @comment try to find customer company name
                 */
                $_companyName = $_customer->getCompany();

                if (!$_companyName) {
                    $_companyName = (
                        $_customer->getDefaultBillingAddress() &&
                        $_customer->getDefaultBillingAddress()->getCompany() &&
                        strlen($_customer->getDefaultBillingAddress()->getCompany())
                    ) ? $_customer->getDefaultBillingAddress()->getCompany() : NULL;
                }
                /* Check if Person Accounts are enabled, if not default the Company name to first and last name */
                if (!Mage::helper("tnw_salesforce")->createPersonAccount() && !$_companyName) {
                    $_companyName = $_customer->getFirstname() . " " . $_customer->getLastname();
                }
                $_companies[$_email] = $_companyName;

                if ($_customer->getData('website_id') != NULL) {
                    $_websites[$_customer->getId()] = $this->_websiteSfIds[$_customer->getData('website_id')];
                }

                $tmp = new stdClass();
                $tmp->Email = $_email;
                $tmp->MagentoId = $_customer->getId();
                $tmp->SfInSync = 0;
                $this->_cache['toSaveInMagento'][$_customer->getData('website_id')][$_email] = $tmp;
            }
            $this->_cache['entitiesUpdating'] = $_emailsArray;
            $this->_customerAccounts = Mage::helper('tnw_salesforce/salesforce_data')->accountLookupByEmailDomain($_emailsArray);

            $_salesforceDataAccount = Mage::helper('tnw_salesforce/salesforce_data_account');
            $_companies = $_salesforceDataAccount->lookupByCompanies($_companies, 'CustomIndex');

//            $this->_customerAccounts = Mage::helper('tnw_salesforce/salesforce_data')->accountLookupByEmailDomain($_emailsArray);

            $foundCustomers = array();

            if (!empty($_emailsArray)) {
                $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($this->_cache['entitiesUpdating'], $_websites);
            }

            foreach ($_emailsArray as $_key => $_email) {
                if (
                    $this->_cache['contactsLookup']
                    && array_key_exists($_websites[$_key], $this->_cache['contactsLookup'])
                    && (
                        array_key_exists($_email, $this->_cache['contactsLookup'][$_websites[$_key]])
                        || array_key_exists($_key, $this->_cache['contactsLookup'][$_websites[$_key]])
                    )
                    // && $this->_cache['contactsLookup'][[$_websites[$_key]]][$_email]->MagentoId == $_key
                ) {
                    $foundCustomers[$_key] = array(
                        'contactId' => $this->_cache['contactsLookup'][$_websites[$_key]][$_email]->Id
                    );

                    $foundCustomers[$_key]['email'] = $_email;

                    if ($this->_cache['contactsLookup'][$_websites[$_key]][$_email]->AccountId) {
                        $foundCustomers[$_key]['AccountId'] = $this->_cache['contactsLookup'][$_websites[$_key]][$_email]->AccountId;
                    }

                    if (array_key_exists($_key, $this->_cache['contactsLookup'][$_websites[$_key]])) {
                        $this->_cache['contactsLookup'][$_websites[$_key]][$_email] = $this->_cache['contactsLookup'][$_websites[$_key]][$_key];
                        unset($this->_cache['contactsLookup'][$_websites[$_key]][$_key]);
                    }

                    unset($_emailsArray[$_key]);
                    unset($_websites[$_key]);
                }
            }
            // Lookup existing Leads
            if (!empty($_emailsArray) || !empty(!empty($foundCustomers))) {
                $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($this->_cache['entitiesUpdating'], $_websites);
                foreach ($this->_cache['leadLookup'] as $_websiteId => $leads) {
                    foreach ($leads as $email => $lead) {
                        if (!$this->_cache['leadLookup'][$_websiteId][$email]->MagentoId) {

                            foreach ($this->_cache['entitiesUpdating'] as $customerId => $customerEmail) {
                                if ($customerEmail == $email) {
                                    $this->_cache['leadLookup'][$_websiteId][$email]->MagentoId = $customerId;
                                }
                            }
                        }
                    }

                }
            }

            if (Mage::helper('tnw_salesforce')->isCustomerAsLead()) {
                foreach ($_emailsArray as $_key => $_email) {
                    if (
                        $this->_cache['leadLookup']
                        && array_key_exists($_websites[$_key], $this->_cache['leadLookup'])
                        && array_key_exists(strtolower($_email), $this->_cache['leadLookup'][$_websites[$_key]])
                        //&& $this->_cache['leadLookup'][$_email]->MagentoId == $_key
                        && !$this->_cache['leadLookup'][$_websites[$_key]][$_email]->IsConverted
                    ) {
                        unset($_emailsArray[$_key]);

                    }
                }
            } else {

                foreach ($foundCustomers as $_key => $data) {
                    $leadData = $this->_cache['leadLookup'][$_websites[$_key]][$data['email']];

                    if ($leadData->IsConverted) {
                        continue;
                    }

                    if (!empty($data['AccountId'])) {
                        $leadData->accountId = $data['AccountId'];
                    }

                    if (!empty($_companies[$data['email']]) && empty($leadData->accountId)) {
                        $leadData->accountId = $_companies[$data['email']]->Id;

                        $leadData->OwnerId = $_companies[$data['email']]->OwnerId;
                        // Check if user is inactive, then overwrite from configuration
                        if (!$this->_isUserActive($leadData->OwnerId)) {
                            $this->_obj->OwnerId = Mage::helper('tnw_salesforce')->getDefaultOwner();
                        }
                    }

                    if ($data['contactId'] && (!empty($leadData->accountId))) {
                        if ($data['contactId']) {
                            $leadData->contactId = $data['contactId'];
                        }
                    }

                    $leadData = $this->_prepareLeadConversionObject($leadData);

                    $this->_cache['leadsToConvert'][$leadData->Id] = $leadData;
                }
            }


            $this->_cache['notFoundCustomers'] = $_emailsArray;
        } catch (Eception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log("CRITICAL: " . $e->getMessage());
        }
    }

    /**
     * @param null $_customer
     * @param string $type
     */
    protected function _processMapping($_customer = NULL, $type = 'Lead')
    {
        // Used only during order placement for guest customers
        $getDefaultBillingAddress = $getDefaultShippingAddress = NULL;
        if (!$_customer->getId()) {
            $getDefaultBillingAddress = ($_customer->getBillingAddress()) ? $_customer->getBillingAddress() : NULL;
            $getDefaultShippingAddress = ($_customer->getShippingAddress()) ? $_customer->getShippingAddress() : NULL;
        }
        $_email = strtolower($_customer->getEmail());
        $_websiteId = $_customer->getData('website_id');
        if ($_customer->getGroupId()) {
            $this->_customerGroupModel->load($_customer->getGroupId());
        }

        // Process the mapping
        $collection = Mage::getModel('tnw_salesforce/mapping')->getCollection()->addObjectToFilter($type);
        foreach ($collection as $_map) {
            $_doSkip = $value = false;
            $conf = explode(" : ", $_map->local_field);
            $sf_field = $_map->sf_field;

            switch ($conf[0]) {
                case "Customer":
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                    $_attr = $_customer->getAttribute($conf[1]);
                    if (
                        is_object($_attr) && $_attr->getFrontendInput() == "select"
                    ) {
                        $newAttribute = $_customer->getResource()->getAttribute($conf[1])->getSource()->getOptionText($_customer->$attr());
                    } elseif (is_object($_attr) && $_attr->getFrontendInput() == "multiselect") {
                        $values = explode(",", $_customer->$attr());
                        $newValues = array();
                        foreach ($values as $_val) {
                            $newValues[] = $_customer->getResource()->getAttribute($conf[1])->getSource()->getOptionText($_val);
                        }
                        $newAttribute = join(";", $newValues);
                    } else {
                        $newAttribute = $_customer->$attr();
                    }
                    // Reformat date fields
                    if ($_map->getBackendType() == "datetime" || $conf[1] == 'created_at' || $_map->getBackendType() == "date") {
                        if ($_customer->$attr()) {
                            $timestamp = Mage::getModel('core/date')->timestamp(strtotime($_customer->$attr()));
                            if ($conf[1] == 'created_at') {
                                $newAttribute = gmdate(DATE_ATOM, $timestamp);
                            } else {
                                $newAttribute = date("Y-m-d", $timestamp);
                            }
                        } else {
                            $_doSkip = true; //Skip this filed if empty
                        }
                    }
                    if (!$_doSkip) {
                        $value = $newAttribute;
                    }
                    break;
                case "Customer Group":
                    //Common attributes
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                    $value = $this->_customerGroupModel->$attr();
                    break;
                case "Billing":
                case "Shipping":
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                    $var = 'getDefault' . $conf[0] . 'Address';
                    /* only push default address if set */
                    $address = ($$var) ? $$var : $_customer->$var();
                    if ($address) {
                        $value = $address->$attr();
                        if (is_array($value)) {
                            $value = implode(", ", $value);
                        } else {
                            $value = ($value && !empty($value)) ? $value : NULL;
                        }
                    }
                    $value = $this->_customizeAddressValue($conf[1], $value);
                    break;
                case "Aitoc":
                    $modules = Mage::getConfig()->getNode('modules')->children();
                    $value = NULL;
                    if (property_exists($modules, 'Aitoc_Aitcheckoutfields')) {
                        $aCustomAtrrList = Mage::getModel('aitcheckoutfields/transport')->loadByCustomerId($_customer->getId());
                        foreach ($aCustomAtrrList->getData() as $_key => $_data) {
                            if ($_data['code'] == $conf[1]) {
                                $value = $_data['value'];
                                if ($_data['type'] == "date") {
                                    $value = date("Y-m-d", strtotime($value));
                                }
                                break;
                            }
                        }
                        unset($aCustomAtrrList);
                    }
                    break;
                case "Custom":
                    $store = ($_customer->getStoreId()) ? Mage::getModel('core/store')->load($_customer->getStoreId()) : NULL;
                    if ($conf[1] == "current_url") {
                        $value = Mage::helper('core/url')->getCurrentUrl();
                    } elseif ($conf[1] == "todays_date") {
                        $value = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
                    } elseif ($conf[1] == "todays_timestamp") {
                        $value = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(time()));
                    } elseif ($conf[1] == "end_of_month") {
                        $lastday = mktime(0, 0, 0, date("n") + 1, 0, date("Y"));
                        $value = date("Y-m-d", Mage::getModel('core/date')->timestamp($lastday));
                    } elseif ($conf[1] == "store_view_name") {
                        $value = (is_object($store)) ? $store->getName() : NULL;
                    } elseif ($conf[1] == "store_group_name") {
                        $value = (
                            is_object($store)
                            && is_object($store->getGroup())
                        ) ? $store->getGroup()->getName() : NULL;
                    } elseif ($conf[1] == "website_name") {
                        $value = (
                            is_object($store)
                            && is_object($store->getWebsite())
                        ) ? $store->getWebsite()->getName() : NULL;
                    } else {
                        $value = $_map->default_value;
                        if ($value == "{{url}}") {
                            $value = Mage::helper('core/url')->getCurrentUrl();
                        } elseif ($value == "{{today}}") {
                            $value = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
                        } elseif ($value == "{{end of month}}") {
                            $lastday = mktime(0, 0, 0, date("n") + 1, 0, date("Y"));
                            $value = date("Y-m-d", $lastday);
                        } elseif ($value == "{{contact id}}") {
                            $value = $this->_contactId;
                        } elseif ($value == "{{store view name}}") {
                            $value = Mage::app()->getStore()->getName();
                        } elseif ($value == "{{store group name}}") {
                            $value = Mage::app()->getStore()->getGroup()->getName();
                        } elseif ($value == "{{website name}}") {
                            $value = Mage::app()->getWebsite()->getName();
                        }
                    }
                    break;
                default:
                    break;
            }
            if ($value) {
                $this->_obj->$sf_field = trim($value);
            } else {
                Mage::helper('tnw_salesforce')->log(strtoupper($type) . ' MAPPING: attribute ' . $sf_field . ' does not have a value in Magento, SKIPPING!');
            }
        }
        unset($collection, $_map, $group);

        $syncParam = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix() . "disableMagentoSync__c";
        $this->_obj->$syncParam = true;

        if ($_customer->getId()) {
            $this->_obj->{$this->_magentoId} = $_customer->getId();
        }

        if (Mage::helper('tnw_salesforce')->getCustomerNewsletterSync()) {
            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($_email);
            $this->_obj->HasOptedOutOfEmail = (!is_object($subscriber) || !$subscriber->isSubscribed()) ? 1 : 0;
        }

        if ($type == "Contact") {
            //Use data in Salesforce if Magento data is blank for the First and Last name
            if (!property_exists($this->_obj, 'FirstName') || !$this->_obj->FirstName) {
                // Check if lookup has the data
                if (
                    array_key_exists($_email, $this->_cache['contactsLookup'])
                    && property_exists($this->_cache['contactsLookup'][$_email], 'FirstName')
                    && $this->_cache['contactsLookup'][$_email]->FirstName
                ) {
                    $this->_obj->FirstName = $this->_cache['contactsLookup'][$_email]->FirstName;
                    $_customer->setFirstname($this->_obj->FirstName);
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->FirstName = $this->_obj->FirstName;
                }
            }
            if (!property_exists($this->_obj, 'LastName') || !$this->_obj->LastName) {
                // Check if lookup has the data
                if (
                    array_key_exists($_email, $this->_cache['contactsLookup'])
                    && property_exists($this->_cache['contactsLookup'][$_email], 'LastName')
                    && $this->_cache['contactsLookup'][$_email]->LastName
                ) {
                    $this->_obj->LastName = $this->_cache['contactsLookup'][$_email]->LastName;
                    $_customer->setLastname($this->_obj->LastName);
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LastName = $this->_obj->LastName;
                }
            }
            //Account
            $this->_obj->AccountId = $_customer->getSalesforceAccountId();

            if (!$this->_obj->AccountId && is_array($this->_customerAccountId) && array_key_exists($_email, $this->_customerAccountId)) {
                $this->_obj->AccountId = $this->_customerAccountId[$_email];
            } elseif (!$this->_obj->AccountId && !is_array($this->_customerAccountId) && $this->_customerAccountId) {
                $this->_obj->AccountId = $this->_customerAccountId;
            }

            if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                if (
                    array_key_exists($_websiteId, $this->_cache['toSaveInMagento'])
                    && array_key_exists($_email, $this->_cache['toSaveInMagento'])
                    && is_object($this->_cache['toSaveInMagento'][$_email][$_websiteId])
                    && property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'IsPersonAccount')
                    && $this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount
                ) {
                    unset($this->_obj->AccountId);
                }
                if (
                    !Mage::helper('tnw_salesforce')->isCustomerAsLead()
                    && array_key_exists($_customer->getId(), $this->_cache['notFoundCustomers'])
                ) {
                    unset($this->_obj->AccountId);
                }
                if (Mage::helper('tnw_salesforce')->isCustomerSingleRecordType() == 2) {
                    // B2C only
                    unset($this->_obj->AccountId);
                }
            }
            // Overwrite Owner ID if assigned value does not match Account Owner Id
            if (
                property_exists($this->_obj, 'OwnerId')
                && $this->_obj->OwnerId
                && $this->_customerOwnerId
                && $this->_customerOwnerId != $this->_obj->OwnerId
            ) {
                $this->_obj->OwnerId = $this->_customerOwnerId;
            }
            $this->_customerAccountId = NULL;
            $this->_customerOwnerId = NULL;
        }

        if ($type == "Lead") {
            if ($_customer->getData('company')) {
                $this->_obj->Company = $_customer->getData('company');
            }
            if (
                !Mage::helper('tnw_salesforce')->usePersonAccount()
                && (!isset($this->_obj->Company) || !$this->_obj->Company)
            ) {
                $this->_obj->Company = $_customer->getFirstname() . ' ' . $_customer->getLastname();
            }
            if (Mage::helper('tnw_salesforce')->isCustomerSingleRecordType() == 2 && property_exists($this->_obj, 'Company')) {
                // B2C only
                unset($this->_obj->Company);
            }
        }

        if ($type == "Account") {
            if (
                Mage::helper('tnw_salesforce')->getBusinessAccountRecordType()
                && Mage::helper('tnw_salesforce')->getBusinessAccountRecordType() != ''
            ) {
                $this->_obj->RecordTypeId = Mage::helper('tnw_salesforce')->getBusinessAccountRecordType();
            }

            if ($_customer->getSalesforceAccountId()) {
                $this->_obj->Id = $_customer->getSalesforceAccountId();
            }
            $_accountName = $_customer->getFirstname() . ' ' . $_customer->getLastname();
            $store = ($_customer->getStoreId() !== NULL) ? Mage::getModel('core/store')->load($_customer->getStoreId()) : NULL;

            if ($_customer->getWebsiteId()) {
                $sfWebsite = $this->_websiteSfIds[$_customer->getWebsiteId()];
            } else if ($store && !empty($this->_websiteSfIds[$_customer->getStoreId()])) {
                $sfWebsite = $this->_websiteSfIds[$_customer->getStoreId()];
            } else {
                $sfWebsite = 0;
            }
            if (
                !Mage::helper('tnw_salesforce')->usePersonAccount()
                || (Mage::helper('tnw_salesforce')->usePersonAccount() && Mage::helper('tnw_salesforce')->isCustomerSingleRecordType() == TNW_Salesforce_Model_Config_Account_Recordtypes::B2B_ACCOUNT)
            ) {
                // This is a potential B2B Account
                if (!property_exists($this->_obj, 'Name')) {
                    if (
                        !Mage::helper('tnw_salesforce')->canRenameAccount()
                        && $this->_cache['contactsLookup']
                        && array_key_exists($sfWebsite, $this->_cache['contactsLookup'])
                        && array_key_exists($_customer->getEmail(), $this->_cache['contactsLookup'][$sfWebsite])
                        && property_exists($this->_cache['contactsLookup'][$sfWebsite][$_customer->getEmail()], 'AccountName')
                        && $this->_cache['contactsLookup'][$sfWebsite][$_customer->getEmail()]->AccountName
                    ) {
                        $_accountName = $this->_cache['contactsLookup'][$sfWebsite][$_customer->getEmail()]->AccountName;
                    }
                    if (!empty($_accountName)) {
                        $this->_obj->Name = $_accountName;
                    }
                }
            } else if (Mage::helper('tnw_salesforce')->getPersonAccountRecordType()) {
                // Configuration is set
                if (
                    $this->_cache['contactsLookup']
                    && array_key_exists($sfWebsite, $this->_cache['contactsLookup'])
                    && array_key_exists($_email, $this->_cache['contactsLookup'][$sfWebsite])
                    && property_exists($this->_obj, 'RecordTypeId')
                ) {
                    /* Lookup found a match */
                    if (
                        property_exists($this->_cache['contactsLookup'][$sfWebsite][$_email], 'IsPersonAccount')
                        && $this->_cache['contactsLookup'][$sfWebsite][$_email]->IsPersonAccount
                    ) {
                        // This is a potential B2C Account
                        $this->_obj->RecordTypeId = Mage::helper('tnw_salesforce')->getPersonAccountRecordType();
                        $this->_addAccountRequiredFields($_customer);
                    } else {
                        // This is a potential B2B Account
                        $_accountName = (
                            property_exists($this->_cache['contactsLookup'][$sfWebsite][$_email], 'AccountName')
                            && $this->_cache['contactsLookup'][$sfWebsite][$_email]->AccountName
                            && !Mage::helper('tnw_salesforce')->canRenameAccount()
                        ) ? $this->_cache['contactsLookup'][$sfWebsite][$_email]->AccountName : $_accountName;

                        if (!empty($_accountName)) {
                            $this->_obj->Name = $_accountName;
                        }
                    }
                } else if (!property_exists($this->_obj, 'Name')) {
                    /* New customer, where account Name is not set */
                    // This is a potential B2C Account
                    $this->_obj->RecordTypeId = Mage::helper('tnw_salesforce')->getPersonAccountRecordType();
                    $this->_addAccountRequiredFields($_customer);
                }
            }
            // Overwrite RecordTypeId from existing account
            if (
                $this->_cache['contactsLookup']
                && array_key_exists($sfWebsite, $this->_cache['contactsLookup'])
                && array_key_exists($_email, $this->_cache['contactsLookup'][$sfWebsite])
                && property_exists($this->_cache['contactsLookup'][$sfWebsite][$_email], 'Account')
                && property_exists($this->_cache['contactsLookup'][$sfWebsite][$_email]->Account, 'RecordTypeId')
            ) {
                $this->_obj->RecordTypeId = $this->_cache['contactsLookup'][$sfWebsite][$_email]->Account->RecordTypeId;
            }

            // Assign OwnerId based on Company name match
            if (empty($this->_customerAccountId)) {
                if (!property_exists($this->_obj, 'Id')) {
                    if (property_exists($this->_obj, 'Name') && $this->_obj->Name) {
                        $_salesforceData = Mage::helper('tnw_salesforce/salesforce_data_account');
                        $_salesforceData->setCompany($this->_obj->Name);
                        $_companies = $_salesforceData->lookupByCompany();
                        if (!empty($_companies)) {
                            foreach ($_companies as $_account) {
                                // Grab first found account
                                if ($_account->Id) {
                                    $this->_obj->Id = $_account->Id;
                                    if ($_account->OwnerId) {
                                        $this->_obj->OwnerId = $_account->OwnerId;
                                        // Check if user is inactive, then overwrite from configuration
                                        if (!$this->_isUserActive($this->_obj->OwnerId)) {
                                            $this->_obj->OwnerId = Mage::helper('tnw_salesforce')->getDefaultOwner();
                                        }
                                        $this->_customerOwnerId = $this->_obj->OwnerId;
                                    }
                                    break;
                                }
                            }
                        }
                    }
                    if (is_array($this->_customerAccountId) && !empty($this->_customerAccountId) && array_key_exists($_email, $this->_customerAccountId)) {
                        $this->_obj->Id = $this->_customerAccountId[$_email];
                        if (!Mage::helper('tnw_salesforce')->canRenameAccount()) {
                            unset($this->_obj->Name);
                        }
                        unset($this->_obj->RecordTypeId);
                    }
                }
            } else {
                if (array_key_exists($_email, $this->_customerAccountId)) {
                    $this->_obj->Id = $this->_customerAccountId[$_email];
                    if (!Mage::helper('tnw_salesforce')->canRenameAccount()) {
                        unset($this->_obj->Name);
                    }
                    unset($this->_obj->RecordTypeId);
                }
            }
        }
    }

    /**
     * @param $_customer
     */
    protected function _addAccountRequiredFields($_customer)
    {
        $this->_obj->PersonEmail = strtolower($_customer->getEmail());
        $this->_obj->FirstName = strtolower($_customer->getFirstname());
        $this->_obj->LastName = strtolower($_customer->getLastname());
        if (property_exists($this->_obj, 'Name')) {
            unset($this->_obj->Name);
        }
    }

    public function reset()
    {
        parent::reset();

        if (is_array($this->_cache['entitiesUpdating'])) {
            foreach ($this->_cache['entitiesUpdating'] as $_id => $_email) {
                if (Mage::registry('customer_cached_' . $_id)) {
                    Mage::unregister('customer_cached_' . $_id);
                }
            }
        }

        $this->_customerObjects = array();
        $this->_currentCustomer = NULL;

        $this->_cache = array(
            'contactsLookup' => array(),
            'leadLookup' => array(),
            'notFoundCustomers' => array(),
            'leadsToUpsert' => array(),
            'contactsToUpsert' => array(),
            'campaignsToUpsert' => array(),
            'accountsToUpsert' => array('Id' => array()),
            'accountsToContactLink' => array(),
            'entitiesUpdating' => array(),
            'toSaveInMagento' => array(),
            'guestsFromOrder' => array(),
            'responses' => array(
                'leads' => array(),
                'contacts' => array(),
                'accounts' => array()
            ),
        );

        if (empty($this->_attributes)) {
            $resource = Mage::getResourceModel('eav/entity_attribute');
            $this->_attributes['salesforce_id'] = $resource->getIdByCode('customer', 'salesforce_id');
            $this->_attributes['salesforce_account_id'] = $resource->getIdByCode('customer', 'salesforce_account_id');
            $this->_attributes['salesforce_lead_id'] = $resource->getIdByCode('customer', 'salesforce_lead_id');
            $this->_attributes['salesforce_is_person'] = $resource->getIdByCode('customer', 'salesforce_is_person');
            $this->_attributes['sf_insync'] = $resource->getIdByCode('customer', 'sf_insync');
            $this->_attributes['firstname'] = $resource->getIdByCode('customer', 'firstname');
            $this->_attributes['lastname'] = $resource->getIdByCode('customer', 'lastname');
        }

        return $this->check();
    }

    /**
     * @param null $_field
     * @param null $_value
     * @return null
     */
    protected function _customizeAddressValue($_field = NULL, $_value = NULL)
    {
        return $_value;
    }

    public function getCustomerAccounts()
    {
        return $this->_customerAccounts;
    }

    /**
     * @param array $emails
     * @return array
     */
    public function findCustomerAccountsForGuests($emails = array())
    {
        $this->_customerAccounts = Mage::helper('tnw_salesforce/salesforce_data')->accountLookupByEmailDomain($emails);
        return $this->getCustomerAccounts();
    }

    /**
     * @param $_customerId
     * @return mixed
     * Extract Website ID from customer by customer ID (including guest)
     */
    protected function _getWebsiteIdByCustomerId($_customerId)
    {
        $_isGuest = (strpos($_customerId, 'guest_') === 0) ? true : false;
        if ($_isGuest) {
            $_websiteId = $this->_cache['guestsFromOrder'][$_customerId]->getWebsiteId();
        } else {
            $customer = Mage::registry('customer_cached_' . $_customerId);
            if (!$customer) {
                $customer = Mage::getModel('customer/customer')->load($_customerId);
            }
            $_websiteId = $customer->getWebsiteId();
        }
        return $_websiteId;
    }

    protected function _fixPersonAccountFields()
    {
        // Rename Magento ID field name
        if (property_exists($this->_obj, $this->_prefix . 'Magento_ID__c')) {
            $_pcMagentoIdFieldName = str_replace('__c', '__pc', $this->_prefix . 'Magento_ID__c');
            $this->_obj->{$_pcMagentoIdFieldName} = $this->_obj->{$this->_prefix . 'Magento_ID__c'};
            if (property_exists($this->_obj, $this->_prefix . 'Magento_ID__c')) {
                unset($this->_obj->{$this->_prefix . 'Magento_ID__c'});
            }
        } else {
            unset($this->_obj->{$this->_prefix . 'Magento_ID__c'});
        }
        // Rename Website API field name
        if (property_exists($this->_obj, $this->_prefix . 'Website__c')) {
            $_pcMagentoIdFieldName = str_replace('__c', '__pc', $this->_prefix . 'Website__c');
            $this->_obj->{$_pcMagentoIdFieldName} = $this->_obj->{$this->_prefix . 'Website__c'};
            if (property_exists($this->_obj, $this->_prefix . 'Website__c')) {
                unset($this->_obj->{$this->_prefix . 'Website__c'});
            }
        } else {
            unset($this->_obj->{$this->_prefix . 'Website__c'});
        }

        // Rename Disable Magento Sync API field name
        if (property_exists($this->_obj, $this->_prefix . 'disableMagentoSync__c')) {
            $_pcMagentoIdFieldName = str_replace('__c', '__pc', $this->_prefix . 'disableMagentoSync__c');
            $this->_obj->{$_pcMagentoIdFieldName} = $this->_obj->{$this->_prefix . 'disableMagentoSync__c'};
            if (property_exists($this->_obj, $this->_prefix . 'disableMagentoSync__c')) {
                unset($this->_obj->{$this->_prefix . 'disableMagentoSync__c'});
            }
        } else {
            unset($this->_obj->{$this->_prefix . 'disableMagentoSync__c'});
        }

        $_standardFields = array(
            'Birthdate', 'AssistantPhone', 'AssistantName', 'Department', 'DoNotCall', 'Email', 'HasOptedOutOfEmail',
            'HasOptedOutOfFax', 'LastCURequestDate', 'LastCUUpdateDate', 'LeadSource', 'MobilePhone', 'OtherPhone',
            'Title'
        );
        foreach ($_standardFields as $_field) {
            $this->_replacePersonField($_field);
        }

        // Rename Billing Street API field name
        if (property_exists($this->_obj, 'OtherStreet')) {
            $this->_obj->BillingStreet = $this->_obj->OtherStreet;
            unset($this->_obj->OtherStreet);
        }
        // Rename Billing City API field name
        if (property_exists($this->_obj, 'OtherCity')) {
            $this->_obj->BillingCity = $this->_obj->OtherCity;
            unset($this->_obj->OtherCity);
        }
        // Rename Billing State API field name
        if (property_exists($this->_obj, 'OtherState')) {
            $this->_obj->BillingState = $this->_obj->OtherState;
            unset($this->_obj->OtherState);
        }
        // Rename Billing Postal Code API field name
        if (property_exists($this->_obj, 'OtherPostalCode')) {
            $this->_obj->BillingPostalCode = $this->_obj->OtherPostalCode;
            unset($this->_obj->OtherPostalCode);
        }
        // Rename Billing Country API field name
        if (property_exists($this->_obj, 'OtherCountry')) {
            $this->_obj->BillingCountry = $this->_obj->OtherCountry;
            unset($this->_obj->OtherCountry);
        }
        // Rename Shipping Street API field name
        if (property_exists($this->_obj, 'MailingStreet')) {
            $this->_obj->ShippingStreet = $this->_obj->MailingStreet;
            unset($this->_obj->MailingStreet);
        }
        // Rename Shipping City API field name
        if (property_exists($this->_obj, 'MailingCity')) {
            $this->_obj->ShippingCity = $this->_obj->MailingCity;
            unset($this->_obj->MailingCity);
        }
        // Rename Shipping State API field name
        if (property_exists($this->_obj, 'MailingState')) {
            $this->_obj->ShippingState = $this->_obj->MailingState;
            unset($this->_obj->MailingState);
        }
        // Rename Shipping Postal Code API field name
        if (property_exists($this->_obj, 'MailingPostalCode')) {
            $this->_obj->ShippingPostalCode = $this->_obj->MailingPostalCode;
            unset($this->_obj->MailingPostalCode);
        }
        // Rename Shipping Country API field name
        if (property_exists($this->_obj, 'MailingCountry')) {
            $this->_obj->ShippingCountry = $this->_obj->MailingCountry;
            unset($this->_obj->MailingCountry);
        }

        // Rename Phone API field name
        if (property_exists($this->_obj, 'Phone')) {
            $this->_obj->PersonHomePhone = $this->_obj->Phone;
            unset($this->_obj->Phone);
        }

        if (property_exists($this->_obj, 'AccountId')) {
            unset($this->_obj->AccountId);
        }
    }

    /**
     * @param $_field
     * Replace standard field with Person Account equivalent
     */
    protected function _replacePersonField($_field)
    {
        if (property_exists($this->_obj, $_field)) {
            $_newKey = 'Person' . $_field;
            $this->_obj->{$_newKey} = $this->_obj->{$_field};
            unset($this->_obj->{$_field});
        }
    }
}