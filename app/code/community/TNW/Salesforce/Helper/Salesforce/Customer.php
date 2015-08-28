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

    /*
     * Is Person Account
     */
    protected $_isPerson = NULL;

    /**
     * Lead Id's to delete
     * @var array
     */
    protected $_toDelete = array();


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

        $this->_preloadAttributes();

        return $this->check();
    }

    protected function _preloadAttributes()
    {
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

        if (!$this->_customerEntityTypeCode) {
            $sql = "SELECT * FROM `" . Mage::helper('tnw_salesforce')->getTable('eav_entity_type') . "` WHERE entity_type_code = 'customer'";
            $row = Mage::helper('tnw_salesforce')->getDbConnection('read')->query($sql)->fetch();
            $this->_customerEntityTypeCode = ($row) ? (int)$row['entity_type_id'] : NULL;
        }
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

        $_data = $_formData;
        $_email = strtolower($_data['email']);
        $_websiteId = Mage::app()->getWebsite()->getId();

        $_fullName = explode(' ', strip_tags($_data['name']));
        if (count($_fullName) == 1) {
            $_lastName = NULL;
        } else if (count($_fullName) == 2) {
            $_lastName = $_fullName[1];
        } else {
            unset($_fullName[0]);
            $_lastName = join(' ', $_fullName);
        }

        /**
         * prepare fake customer object to use it in lookup
         */
        $fakeCustomer = new Varien_Object();
        $fakeCustomer->setData($_formData);

        $firstName = ($_lastName) ? $_fullName[0] : '';
        $lastName = ($_lastName) ? $_lastName : $_fullName[0];
        $company = (array_key_exists('company', $_data)) ? strip_tags($_data['company']) : NULL;

        $fakeCustomer->setFirstname($firstName);
        $fakeCustomer->setLastname($lastName);
        $fakeCustomer->setCompany($company);

        $fakeCustomer->setEmail($_email);

        $billingAddress = new Varien_Object();
        $billingAddress->setTelephone(strip_tags($_data['telephone']));
        $fakeCustomer->setBillingAddress($billingAddress);

        $customerId = (int)$fakeCustomer->getId();
        if (Mage::registry('customer_cached_' . $customerId)) {
            Mage::unregister('customer_cached_' . $customerId);
        }

        Mage::register('customer_cached_' . $customerId, $fakeCustomer);


        // Check for Contact and Account
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup(array($customerId => $_email), array($customerId => $this->_websiteSfIds[$_websiteId]));
        $this->_cache['accountLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup(array($customerId => $_email), array($customerId => $this->_websiteSfIds[$_websiteId]));
        $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup(array($customerId => $_email), array($customerId => $this->_websiteSfIds[$_websiteId]), Mage::helper('tnw_salesforce/data')->getLeadSource());

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
            if ($this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->IsConverted) {
                $_id = $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->ConvertedContactId;
            }
        } else {

            $this->_assignOwner($fakeCustomer, 'Lead', $this->_websiteSfIds[$_websiteId]);

            //Process mapping
            Mage::getSingleton('tnw_salesforce/sync_mapping_customer_lead')
                ->setSync($this)
                ->processMapping($fakeCustomer);

            // Link to a Website
            if (
                $_websiteId !== NULL
                && array_key_exists($_websiteId, $this->_websiteSfIds)
                && $this->_websiteSfIds[$_websiteId]
            ) {
                $this->_obj->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()} = $this->_websiteSfIds[$_websiteId];
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
        try {

            if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
                throw new Exception('could not establish Salesforce connection.');
            }

            Mage::helper('tnw_salesforce')->log("================ MASS SYNC: START ================");
            if (!is_array($this->_cache) || empty($this->_cache['entitiesUpdating'])) {
                throw new Exception('Sync customers, cache is empty!');
            }

            // Prepare Data
            $this->_prepareLeads();
            $this->_prepareContacts();
            $this->_prepareNew();

            if ($this instanceof TNW_Salesforce_Helper_Bulk_Customer) {
                // Clean up the data we are going to be pushing in (for guest orders if multiple orders placed by the same person and they happen to end up in the same batch)
                $this->_deDupeCustomers();
            }

            $this->clearMemory();

            // Push Data
            $this->_pushToSalesforce($_return);
            $this->clearMemory();

            //Deal with Person Accounts
            $this->_personAccountUpdate();

            // Update Magento
            if ($this->_customerEntityTypeCode) {
                $this->_updateMagento();
            } else {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Failed to update Magento with Salesforce Ids');
                }
                Mage::helper('tnw_salesforce')->log("WARNING: Failed to update Magento with Salesforce Ids. Try manual synchronization.", 2);
            }

            /**
             * send data to Salesforce
             */
            $this->_updateCampaings();

            if ($_return) {
                if ($this instanceof TNW_Salesforce_Helper_Bulk_Customer && $_return == 'bulk') {
                    // Update guest data from de duped records before passing it back to Order object
                    $this->_updateGuestCachedData();

                    $_i = 0;
                    foreach ($this->_toSyncOrderCustomers as $_orderNum => $_customer) {
                        if ($_customer->getId()) {
                            $this->_orderCustomers[$_orderNum] = Mage::registry('customer_cached_' . $_customer->getId());
                        } else {
                            $this->_orderCustomers[$_orderNum] = $this->_cache['guestsFromOrder']['guest_' . $_orderNum];
                        }
                        $_i++;
                    }
                } else {
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
            }
            $this->_onComplete();

            Mage::helper('tnw_salesforce')->log("================= MASS SYNC: END =================");
            if ($_return) {
                if ($this instanceof TNW_Salesforce_Helper_Bulk_Customer && $_return == 'bulk') {
                    return $this->_orderCustomers;
                } else {
                    return $currentCustomer;
                }
            }
        } catch (Exception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log("CRITICAL: " . $e->getMessage());

            return false;
        }
    }


    /**
     * push data to Salesforce
     */
    protected function _updateCampaings()
    {
        Mage::helper('tnw_salesforce/salesforce_newslettersubscriber')->updateCampaings();
    }

    /**
     * Create Lead object for sync
     */
    protected function _prepareLeads()
    {
        // Existing Leads
        if (!empty($this->_cache['leadLookup'])) {
            foreach ($this->_cache['leadLookup'] as $_salesforceWebsiteId => $websiteLeads) {
                $_websiteId = array_search($_salesforceWebsiteId, $this->_websiteSfIds);
                foreach ($websiteLeads as $_email => $_info) {
                    $this->_isPerson = NULL;
                    // Just in case Salesforce did not save Magento ID for some reason
                    if (
                        !$_info->MagentoId &&
                        is_array($this->_cache['toSaveInMagento']) &&
                        array_key_exists($_websiteId, $this->_cache['toSaveInMagento']) &&
                        array_key_exists($_email, $this->_cache['toSaveInMagento'][$_websiteId])
                    ) {
                        $_info->MagentoId = $this->_cache['toSaveInMagento'][$_websiteId][$_email]->MagentoId;
                    }

                    if (!$_info->IsConverted) {
                        $this->_addToQueue($_info->MagentoId, "Lead");
                    } else {
                        $this->_toDelete[] = $_info->Id;
                        unset($this->_cache['leadLookup'][$_salesforceWebsiteId][$_email]);
                    }
                }
            }
        }
    }

    /**
     * @comment create stdClass object for synchronization, see the "_obj" property
     * @param $_id
     * @param string $type
     */
    protected function _addToQueue($_id, $type = "Lead")
    {
        /**
         * @var NULL|Mage_Customer_Model_Customer
         */
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
            if (Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_PERSON_ACCOUNT)) {
                if (!property_exists($this->_cache['contactsLookup'][$_sfWebsite][$_email], 'IsPersonAccount')) {
                    $_customer->setSalesforceIsPerson(false);
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount = false;
                } else {
                    $_customer->setSalesforceIsPerson($this->_cache['contactsLookup'][$_sfWebsite][$_email]->IsPersonAccount);
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount = $this->_cache['contactsLookup'][$_sfWebsite][$_email]->IsPersonAccount;
                }
            }
        }
        if ($this->_cache['leadLookup'] && array_key_exists($_sfWebsite, $this->_cache['leadLookup']) && array_key_exists($_email, $this->_cache['leadLookup'][$_sfWebsite])) {
            $_customer->setSalesforceLeadId($this->_cache['leadLookup'][$_sfWebsite][$_email]->Id);
        }

        $this->_assignOwner($_customer, $type, $_sfWebsite);

        //Process mapping
        Mage::getSingleton('tnw_salesforce/sync_mapping_customer_' . strtolower($type))
            ->setSync($this)
            ->processMapping($_customer);

        // Link to a Website
        if (
            $_websiteId !== NULL
            && array_key_exists($_websiteId, $this->_websiteSfIds)
            && $this->_websiteSfIds[$_websiteId]
        ) {
            $this->_obj->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()} = $this->_websiteSfIds[$_websiteId];
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
            !$this->_isPerson
            ) {
                // Set Conjoint AccountId
                if (Mage::getStoreConfig('salesforce_customer/sync/single_account_sync_customer')
                    && Mage::getStoreConfig('salesforce_customer/sync/single_account_select') !== 'null'
                ) {
                    $this->_obj->AccountId = Mage::getStoreConfig('salesforce_customer/sync/single_account_select');
                } else {
                    $this->_obj->AccountId = $this->getCustomerAccount($_email);
                }

                if (!$this->_obj->AccountId && !empty($this->_cache['accountLookup'])) {
                    $accountKeys = array_keys($this->_cache['accountLookup']);

                    if (array_key_exists($_email, $this->_cache['accountLookup'][$accountKeys[0]])) {
                        $this->_obj->AccountId = $this->_cache['accountLookup'][$accountKeys[0]][$_email]->Id;
                    }
                }

                if (!is_array($this->_cache['toSaveInMagento'][$_websiteId])) {
                    $this->_cache['toSaveInMagento'][$_websiteId] = array();
                }
                if (!array_key_exists($_email, $this->_cache['toSaveInMagento'][$_websiteId])) {
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email] = new stdClass();
                }
                $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = $this->_obj->AccountId;
            } else {
                if ($this->_isPerson && property_exists($this->_obj, 'AccountId')) {
                    unset($this->_obj->AccountId);
                }
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

            if ($this->_isPerson) {
                // Move the prepared Contact data to Person Account
                if (
                    array_key_exists('Id', $this->_cache['accountsToUpsert'])
                    && array_key_exists($_id, $this->_cache['accountsToUpsert']['Id'])
                ) {
                    foreach ($this->_cache['contactsToUpsert'][$_upsertOn][$_id] as $_key => $_value) {
                        if (!property_exists($this->_cache['accountsToUpsert']['Id'][$_id], $_key)) {

                            /**
                             * the PersonAccount field names have "__pc" postfix, but Contact field names have the "__c" postfix
                             */
                            if (preg_match('/^.*__c$/', $_key)) {
                                $_key = preg_replace('/__c$/', '__pc', $_key);
                            }
                            $this->_cache['accountsToUpsert']['Id'][$_id]->{$_key} = $_value;
                        }
                    }
                }

                $this->_fixPersonAccountFields($this->_cache['accountsToUpsert']['Id'][$_id]);

                /**
                 * remove contact upsert if it is person account
                 */
                unset($this->_cache['contactsToUpsert'][$_upsertOn][$_id]);
            }

        } else if ($type == "Account") {

            if (isset($this->_cache['accountLookup'][0][$_email])) {
                $this->_obj->Id = $this->_cache['accountLookup'][0][$_email]->Id;
            }

            // If Guest, the key for the lookup is cusotmer Id instead fo an email
            $lookupGuestMatch = $this->getCustomerAccount($_id);

            if (
                (
                    property_exists($this->_obj, 'RecordTypeId')
                    && $this->_obj->RecordTypeId == Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::PERSON_RECORD_TYPE)
                    // Following logic checks if an existing Account is found and it happends to be B2B
                    && !(
                        $lookupGuestMatch
                        && array_key_exists($lookupGuestMatch, Mage::helper('tnw_salesforce/config')->getSalesforceAccounts())
                    )
                ) || (
                    property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'IsPersonAccount')
                    && $this->_cache['toSaveInMagento'][$_websiteId][$_email]->IsPersonAccount
                )
            ) {
                // This is Person Account
                if (!$this->_isPerson) {
                    $this->_isPerson = true;
                }

                if (property_exists($this->_obj, 'Name')) {
                    unset($this->_obj->Name);
                }

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
            } elseif (Mage::getStoreConfig('salesforce_customer/sync/single_account_sync_customer')
                && Mage::getStoreConfig('salesforce_customer/sync/single_account_select') !== 'null'
                && !$this->_isPerson
            ) {
                // Skip Account sync
                return;
            } else {
                // Reset Record Type Id (incase if it was set to B2C
                $this->_obj->RecordTypeId = Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::BUSINESS_RECORD_TYPE);

                // Reset pre-defined fields for B2C
                // Reason we do this here is because of the advanced lookup key for guests is unique and not available in the mapping model
                if (property_exists($this->_obj, 'PersonEmail')) {
                    unset($this->_obj->PersonEmail);
                }
                if (property_exists($this->_obj, 'FirstName')) {
                    unset($this->_obj->FirstName);
                }
                if (property_exists($this->_obj, 'LastName')) {
                    unset($this->_obj->LastName);
                }
                // This is a B2B Account
                unset($this->_obj->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()});
                unset($this->_obj->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c'});

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

            if (
                $this->getCustomerAccount($_email)
                || $lookupGuestMatch
            ) {
                $_found = true;
                if ($lookupGuestMatch) {
                    $this->_obj->Id = $lookupGuestMatch;
                    if (property_exists($this->_obj, 'Name')) {
                        // Remove Name since Account exists in Salesforce and we should not rename it
                        unset($this->_obj->Name);
                    }
                }
            }

            //Unset Record Type if blank
            if (property_exists($this->_obj, 'RecordTypeId') && empty($this->_obj->RecordTypeId)) {
                unset($this->_obj->RecordTypeId);
            }

            // Skip duplicate account for one of the contacts
            if (!$_found) {
                // Make sure Name of the Account is taken from Salesforce, if found
                // only if configuration tells us NOT to overwrite the Account Name
                if (
                    !Mage::helper('tnw_salesforce')->canRenameAccount()
                    && property_exists($this->_obj, 'Name')
                    && !$this->_isPerson
                ) {
                    $this->_obj->Name = $this->_getAccountName($this->_obj->Name, $_email, $_sfWebsite);
                }
                $this->_cache['accountsToUpsert']['Id'][$_id] = $this->_obj;
            }
            if (property_exists($this->_obj, 'Id')) {
                $this->_cache['accountsToContactLink'][$_id] = $this->_obj->Id;
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
            } else if (
                property_exists($this->_obj, 'Id')
                && empty($this->_obj->Name)
            ) {
                $this->_cache['accountsToUpsert']['Id'][$_id] = $this->_obj;
            }
        }
    }

    /**
     * @comment define Lead/Account/Contact owner
     * @param $_customer
     * @param $type
     * @param $_sfWebsite
     */
    protected function _assignOwner($_customer, $type, $_sfWebsite)
    {
        $_email = strtolower($_customer->getEmail());

        $_ownerID = NULL;
        if (Mage::helper('tnw_salesforce')->getDefaultOwner()) {
            $this->_obj->OwnerId = Mage::helper('tnw_salesforce')->getDefaultOwner();
        }

        $cacheType = strtolower($type);

        /**
         * @comment hack, contact has the "contactsLookup" cache name
         */
        if ($type == 'Contact') {
            $cacheType = 'contacts';
        } elseif ($type == 'Account') {
            /**
             * @comment accounts are not splitted by websites, so, we define 0 for cache array compatibility
             */
            $_sfWebsite = 0;
        }

        /**
         * @comment contactsLookup|leadLookup
         */
        $cacheKey = $cacheType . 'Lookup';
        if (
            is_array($this->_cache[$cacheKey])
            && array_key_exists($_sfWebsite, $this->_cache[$cacheKey])
            && (
                array_key_exists($_email, $this->_cache[$cacheKey][$_sfWebsite])
                || array_key_exists($_customer->getId(), $this->_cache[$cacheKey][$_sfWebsite])
            )
        ) {
            /**
             * @comment get Contact|Account|Lead object
             */
            if (array_key_exists($_email, $this->_cache[$cacheKey][$_sfWebsite])) {
                $entity = $this->_cache[$cacheKey][$_sfWebsite][$_email];
            } elseif (array_key_exists($_customer->getId(), $this->_cache[$cacheKey][$_sfWebsite])) {
                $entity = $this->_cache[$cacheKey][$_sfWebsite][$_customer->getId()];
            }

            if (is_object($entity)) {
                $_ownerID = property_exists($entity, 'OwnerId') ? $entity->OwnerId : null;

                if (
                    $cacheType == 'contacts'
                    && property_exists($entity, 'Account')
                    && !$_ownerID
                ) {
                    /**
                     * @comment get account object
                     */
                    $entity = $entity->Account;
                    $_ownerID = (property_exists($entity, 'OwnerId') && !Mage::helper('tnw_salesforce/config_customer')->useDefaultOwner()) ?
                        $entity->OwnerId :
                        null;
                }
            }

            if ($_ownerID && $this->_isUserActive($_ownerID)) {
                Mage::helper('tnw_salesforce')->log($type . " record already assigned to " . $_ownerID);

            } else {
                $_ownerID = Mage::helper('tnw_salesforce')->getDefaultOwner();
            }

            $this->_obj->OwnerId = $_ownerID;
        }

    }

    public function getCustomerAccount($email)
    {
        if (isset($this->_customerAccounts[$email]) && $this->_customerAccounts[$email]) {
            return $this->_customerAccounts[$email];
        } else {
            return false;
        }
    }

    protected function _fixPersonAccountFields(&$object)
    {
        // Rename Magento ID field name
        if (property_exists($object, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c')) {
            $_pcMagentoIdFieldName = str_replace('__c', '__pc', Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c');
            $object->{$_pcMagentoIdFieldName} = $object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c'};
            if (property_exists($object, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c')) {
                unset($object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c'});
            }
        } else {
            unset($object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c'});
        }
        // Rename Website API field name
        if (property_exists($object, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject())) {
            $_pcMagentoIdFieldName = str_replace('__c', '__pc', Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject());
            $object->{$_pcMagentoIdFieldName} = $object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()};
            if (property_exists($object, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject())) {
                unset($object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()});
            }
        } else {
            unset($object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()});
        }

        // Rename Disable Magento Sync API field name
        if (property_exists($object, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . 'disableMagentoSync__c')) {
            $_pcMagentoIdFieldName = str_replace('__c', '__pc', Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . 'disableMagentoSync__c');
            $object->{$_pcMagentoIdFieldName} = $object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . 'disableMagentoSync__c'};
            if (property_exists($object, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . 'disableMagentoSync__c')) {
                unset($object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . 'disableMagentoSync__c'});
            }
        } else {
            unset($object->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . 'disableMagentoSync__c'});
        }

        $_standardFields = array(
            'Birthdate', 'AssistantPhone', 'AssistantName', 'Department', 'DoNotCall', 'Email', 'HasOptedOutOfEmail',
            'HasOptedOutOfFax', 'LastCURequestDate', 'LastCUUpdateDate', 'LeadSource', 'MobilePhone', 'OtherPhone',
            'Title'
        );
        foreach ($_standardFields as $_field) {
            $this->_replacePersonField($_field, $object);
        }

        // Rename Billing Street API field name
        if (property_exists($object, 'OtherStreet')) {
            $object->BillingStreet = $object->OtherStreet;
            unset($object->OtherStreet);
        }
        // Rename Billing City API field name
        if (property_exists($object, 'OtherCity')) {
            $object->BillingCity = $object->OtherCity;
            unset($object->OtherCity);
        }
        // Rename Billing State API field name
        if (property_exists($object, 'OtherState')) {
            $object->BillingState = $object->OtherState;
            unset($object->OtherState);
        }
        // Rename Billing Postal Code API field name
        if (property_exists($object, 'OtherPostalCode')) {
            $object->BillingPostalCode = $object->OtherPostalCode;
            unset($object->OtherPostalCode);
        }
        // Rename Billing Country API field name
        if (property_exists($object, 'OtherCountry')) {
            $object->BillingCountry = $object->OtherCountry;
            unset($object->OtherCountry);
        }
        // Rename Shipping Street API field name
        if (property_exists($object, 'MailingStreet')) {
            $object->ShippingStreet = $object->MailingStreet;
            unset($object->MailingStreet);
        }
        // Rename Shipping City API field name
        if (property_exists($object, 'MailingCity')) {
            $object->ShippingCity = $object->MailingCity;
            unset($object->MailingCity);
        }
        // Rename Shipping State API field name
        if (property_exists($object, 'MailingState')) {
            $object->ShippingState = $object->MailingState;
            unset($object->MailingState);
        }
        // Rename Shipping Postal Code API field name
        if (property_exists($object, 'MailingPostalCode')) {
            $object->ShippingPostalCode = $object->MailingPostalCode;
            unset($object->MailingPostalCode);
        }
        // Rename Shipping Country API field name
        if (property_exists($object, 'MailingCountry')) {
            $object->ShippingCountry = $object->MailingCountry;
            unset($object->MailingCountry);
        }

        // Rename Phone API field name
        if (property_exists($object, 'Phone')) {
            $object->PersonHomePhone = $object->Phone;
            unset($object->Phone);
        }

        if (property_exists($object, 'AccountId')) {
            unset($object->AccountId);
        }

    }

    /**
     * @param $_field
     * Replace standard field with Person Account equivalent
     */
    protected function _replacePersonField($_field, &$object)
    {
        if (property_exists($object, $_field)) {
            $_newKey = 'Person' . $_field;
            $object->{$_newKey} = $object->{$_field};
            unset($object->{$_field});
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

    protected function _prepareContacts()
    {
        if (!empty($this->_cache['contactsLookup'])) {
            foreach ($this->_cache['contactsLookup'] as $_salesforceWebsiteId => $_accounts) {
                $_websiteId = array_search($_salesforceWebsiteId, $this->_websiteSfIds);
                if ($_websiteId === false) {
                    $_websiteId = '';
                }
                foreach ($_accounts as $_email => $_info) {
                    $this->_isPerson = NULL;
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

    protected function _prepareNew()
    {
        if (!empty($this->_toDelete)) {
            $this->_deleteLeads();
        }

        if (!empty($this->_cache['notFoundCustomers'])) {
            foreach ($this->_cache['notFoundCustomers'] as $_id => $_email) {
                $this->_isPerson = NULL;
                // Check if new customers need to be added as a Lead or Contact
                if (
                    (
                        Mage::helper('tnw_salesforce')->isCustomerAsLead()
                        && !$this->getCustomerAccount($_email)
                    ) || (
                        is_array($this->_cache['leadLookup'])
                        && array_key_exists($this->_cache['customerToWebsite'][$_id], $this->_cache['leadLookup'])
                        && array_key_exists($_email, $this->_cache['leadLookup'][$this->_cache['customerToWebsite'][$_id]])
                    )
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

    protected function _deleteLeads()
    {
        $_ids = array_chunk($this->_toDelete, TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT);
        foreach ($_ids as $_recordIds) {
            $this->_mySforceConnection->delete($_recordIds);
        }
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
                $tmp->MagentoId = 'guest_0';
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

            $this->_cache['customerToWebsite'] = array($_customerId => $this->_websiteSfIds[$_websiteId]);

            // Lookup existing Contacts & Accounts
            $tmp->Email = $_email;
            $tmp->SfInSync = 0;
            $this->_cache['toSaveInMagento'][$_websiteId][$_email] = $tmp;

            $this->_cache['entitiesUpdating'] = $_emailsArray;
            $this->findCustomerAccounts($_emailsArray);
            $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($_emailsArray, array($_customerId => $this->_websiteSfIds[$_websiteId]));
            $this->_cache['accountLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($_emailsArray, array($_customerId => $this->_websiteSfIds[$_websiteId]));
            $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($_emailsArray, array($_customerId => $this->_websiteSfIds[$_websiteId]), Mage::helper('tnw_salesforce/data')->getLeadSource());

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

            foreach ($_emailsArray as $_key => $_email) {
                if (
                    $this->_cache['leadLookup'] &&
                    array_key_exists($this->_websiteSfIds[$_websiteId], $this->_cache['leadLookup']) &&
                    array_key_exists(strtolower($_email), $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]]) &&
                    ($this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId == $_key || !$this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId)
                ) {
                    /**
                     * @comment remove from array if customer found as lead or lead is converted but no related account+contact
                     */
                    if (
                        !$this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->IsConverted
                        || !isset($this->_cache['accountLookup'][0][$_email])
                        || !isset($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email])

                    ) {
                        unset($_emailsArray[$_key]);
                    }
                }
            }

            $this->_cache['notFoundCustomers'] = $_emailsArray;
        } catch (Exception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log("CRITICAL: " . $e->getMessage());
        }
    }

    /**
     * @param array $emails
     * @return array
     */
    public function findCustomerAccounts($emails = array())
    {
        $this->_customerAccounts = Mage::helper('tnw_salesforce/salesforce_data')->accountLookupByEmailDomain($emails);
        return $this->getCustomerAccounts();
    }

    public function getCustomerAccounts()
    {
        return $this->_customerAccounts;
    }

    /**
     * Update customer statistic data for using in mapping
     * @param $ids
     */
    protected function _updateCustomerStatistic($ids)
    {

        /**
         * field names are necessary for customer table updating
         */
        $fields = array();

        // 1. Save sales info
        /**
         * prepare query for sales statistic calculation
         */
        $salesCollection = Mage::getModel('sales/order')->getCollection();
        $salesCollection->removeAllFieldsFromSelect();
        $salesCollection->removeFieldFromSelect($salesCollection->getResource()->getIdFieldName());

        /**
         * add customer_id in result
         */
        $fields[] = 'entity_id';
        $salesCollection->addFieldToSelect('customer_id', 'entity_id');

        /**
         * select last_purchase value
         */
        $fields[] = 'last_purchase';
        //$salesCollection->addFieldToSelect('created_at', 'last_purchase');
        $salesCollection->addExpressionFieldToSelect('last_purchase', 'MAX(created_at)', array());

        /**
         * salect last_transaction_id
         */
        $fields[] = 'last_transaction_id';
        //$salesCollection->addFieldToSelect('increment_id', 'last_transaction_id');
        $salesCollection->addExpressionFieldToSelect('last_transaction_id', 'MAX(increment_id)', array());


        /**
         * select total_order_count value
         */
        $fields[] = 'total_order_count';
        $salesCollection
            ->addExpressionFieldToSelect('total_order_count', "COUNT(*)", array());

        /**
         * select total_order_amount value
         */
        $fields[] = 'total_order_amount';
        $salesCollection
            ->addExpressionFieldToSelect('total_order_amount', 'SUM(base_grand_total)', array());

        $salesCollection->addFieldToFilter('customer_id', array('in' => $ids));

        $salesCollection->getSelect()->group('customer_id');

        /**
         * save sales statistic in customer table
         */
        $query = $salesCollection->getSelect()->insertFromSelect(
            Mage::getModel('customer/customer')->getResource()->getEntityTable(),
            $fields,
            true
        );
        $result = Mage::getModel('customer/customer')->getResource()->getWriteConnection()->query($query);

        $fields = array();

        // 2. Save login date
        /**
         * prepare last login date
         */
        $logCustomerResource = Mage::getModel('log/customer')->getResource();
        $select = $logCustomerResource->getReadConnection()->select();


        $fields[] = 'entity_id';
        $fields[] = 'last_login';

        $select
            ->from(
                $logCustomerResource->getMainTable(),
                array(
                    'customer_id AS entity_id',
                    'login_at AS last_login'
                ));

        $select->where('customer_id IN (?)', $ids);

        $query = $select->insertFromSelect(
            Mage::getModel('customer/customer')->getResource()->getEntityTable(),
            $fields,
            true
        );

        $result = Mage::getModel('customer/customer')->getResource()->getWriteConnection()->query($query);

        return $this;
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

            $this->_updateCustomerStatistic($ids);

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

                $_companyName = Mage::helper('tnw_salesforce/salesforce_data_lead')->getCompanyByCustomer($_customer);
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
            $this->findCustomerAccounts($_emailsArray);

            $_salesforceDataAccount = Mage::helper('tnw_salesforce/salesforce_data_account');

            $foundCustomers = array();

            if (!empty($_emailsArray)) {
                $this->_cache['customerToWebsite'] = $_websites;
                $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')->lookup($this->_cache['entitiesUpdating'], $_websites, Mage::helper('tnw_salesforce/data')->getLeadSource());
                $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')->lookup($this->_cache['entitiesUpdating'], $_websites);
                $this->_cache['accountLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup($this->_cache['entitiesUpdating'], $_websites);
            }

            $_converted = array();
            foreach ($_emailsArray as $_key => $_email) {
                if (
                    $this->_cache['contactsLookup']
                    && array_key_exists($_websites[$_key], $this->_cache['contactsLookup'])
                    && (
                        array_key_exists($_email, $this->_cache['contactsLookup'][$_websites[$_key]])
                        || array_key_exists($_key, $this->_cache['contactsLookup'][$_websites[$_key]])
                    )
                ) {
                    $foundCustomers[$_key] = array(
                        'contactId' => $this->_cache['contactsLookup'][$_websites[$_key]][$_email]->Id
                    );

                    $foundCustomers[$_key]['email'] = $_email;

                    if ($this->_cache['contactsLookup'][$_websites[$_key]][$_email]->AccountId) {
                        $foundCustomers[$_key]['accountId'] = $this->_cache['contactsLookup'][$_websites[$_key]][$_email]->AccountId;
                    }

                    if (array_key_exists($_key, $this->_cache['contactsLookup'][$_websites[$_key]])) {
                        $this->_cache['contactsLookup'][$_websites[$_key]][$_email] = $this->_cache['contactsLookup'][$_websites[$_key]][$_key];
                        unset($this->_cache['contactsLookup'][$_websites[$_key]][$_key]);
                    }

                    if (!array_key_exists('contactId', $foundCustomers[$_key])) {
                        $_converted[$_key] = $foundCustomers[$_key];
                    }

                    unset($_emailsArray[$_key]);
                    unset($_websites[$_key]);
                }
            }

            // Lookup existing Leads
            if (!empty($_emailsArray) || !empty($_converted)) {
                if (!empty($this->_cache['leadLookup'])) {
                    foreach ($this->_cache['leadLookup'] as $_websiteId => $leads) {
                        foreach ($leads as $email => $lead) {
                            if (!$this->_cache['leadLookup'][$_websiteId][$email]->MagentoId) {
                                foreach ($this->_cache['entitiesUpdating'] as $customerId => $customerEmail) {
                                    if ($customerEmail == $email) {
                                        $this->_cache['leadLookup'][$_websiteId][$email]->MagentoId = $customerId;
                                        $foundCustomers[$customerId] = (array)$this->_cache['leadLookup'][$_websiteId][$email];
                                        $foundCustomers[$customerId]['email'] = $email;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            foreach ($_emailsArray as $_key => $_email) {
                if (
                    !Mage::helper('tnw_salesforce')->isCustomerAsLead()
                    && !empty($this->_cache['leadLookup'])
                    && array_key_exists($_websites[$_key], $this->_cache['leadLookup'])
                    && array_key_exists($_email, $this->_cache['leadLookup'][$_websites[$_key]])
                ) {
                    $foundCustomers[$_key]['email'] = $_email;
                }
            }

            if (Mage::helper('tnw_salesforce')->isCustomerAsLead()) {
                foreach ($_emailsArray as $_key => $_email) {
                    if (
                        $this->_cache['leadLookup']
                        && array_key_exists($_websites[$_key], $this->_cache['leadLookup'])
                        && array_key_exists(strtolower($_email), $this->_cache['leadLookup'][$_websites[$_key]])

                    ) {
                        /**
                         * @comment remove from array if customer found as lead or lead is converted but no related account+contact
                         */
                        if (
                            !$this->_cache['leadLookup'][$_websites[$_key]][$_email]->IsConverted
                            || !isset($this->_cache['accountLookup'][0][$_email])
                            || !isset($this->_cache['contactsLookup'][$_websites[$_key]][$_email])

                        ) {
                            unset($_emailsArray[$_key]);
                        }

                    }
                }
            } else {

                /**
                 * @comment try to find lead
                 */
                if (!empty($this->_cache['leadLookup'])) {
                    foreach ($this->_cache['leadLookup'] as $_websiteId => $_leads) {
                        foreach ($_leads as $_email => $_lead) {
                            if ($_lead->IsConverted) {
                                // TODO: if no contacts found, confirm that new contact and account should be created.
                                continue;
                            }

                            if (!$_lead->Id) {
                                // Skip if there is no Lead ID
                                continue;
                            }

                            $leadConvert = new stdClass();

                            if (isset($this->_cache['accountLookup'][0][$_email])) {
                                $leadConvert->accountId = $this->_cache['accountLookup'][0][$_email]->Id;
                            }

                            if (
                                isset($this->_cache['contactsLookup'][$_websiteId][$_email])
                                && !$this->_cache['contactsLookup'][$_websiteId][$_email]->IsPersonAccount
                            ) {
                                $leadConvert->contactId = $this->_cache['contactsLookup'][$_websiteId][$_email]->Id;
                            }

                            $leadConvert = $this->_prepareLeadConversionObject($_lead, $leadConvert);

                            $_productId = array_search($_email, $_emailsArray);
                            $this->_cache['leadsToConvert'][$_productId] = $leadConvert;

                            unset($_emailsArray[$_productId]);

                        }
                    }
                }
            }

            $this->_cache['notFoundCustomers'] = $_emailsArray;
        } catch (Exception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log("CRITICAL: " . $e->getMessage());
        }
    }

    /**
     * @param null $lead
     * @param null $leadConvert
     * @return mixed
     */
    protected function _prepareLeadConversionObject($lead = NULL, $leadConvert = NULL)
    {
        return Mage::helper('tnw_salesforce/salesforce_data_lead')->prepareLeadConversionObjectSimple($lead, $leadConvert);
    }

    /**
     * @deprecated Use "findCustomerAccounts" instead
     * @param array $emails
     * @return array
     */
    public function findCustomerAccountsForGuests($emails = array())
    {
        return $this->findCustomerAccounts($emails);
    }

    /**
     * @return null
     */
    public function getCustomerAccountId($email = null)
    {
        if (isset($this->_customerAccountId[$email])) {
            return $this->_customerAccountId[$email];
        }

        return $this->_customerAccountId;
    }

    /**
     * @param null $customerAccountId
     * @return $this
     */
    public function setCustomerAccountId($customerAccountId)
    {
        $this->_customerAccountId = $customerAccountId;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getCustomerOwnerId()
    {
        return $this->_customerOwnerId;
    }

    /**
     * @param $customerOwnerId
     * @return $this
     */
    public function setCustomerOwnerId($customerOwnerId = null)
    {
        $this->_customerOwnerId = $customerOwnerId;

        return $this;
    }

    protected function _personAccountUpdate()
    {
        $accountsToFind = array();

        foreach ($this->_cache['toSaveInMagento'] as $_websiteId => $_websiteCustomers) {
            foreach ($_websiteCustomers as $_email => $_customer) {
                $_customer->SalesforceId = (property_exists($_customer, 'SalesforceId')) ? $_customer->SalesforceId : NULL;
                $_customer->AccountId = (property_exists($_customer, 'AccountId')) ? $_customer->AccountId : NULL;

                if (
                    Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_PERSON_ACCOUNT)
                    && Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::PERSON_RECORD_TYPE)
                    && $_customer->AccountId != NULL && $_customer->AccountId == $_customer->SalesforceId
                ) {
                    // Lookup needed
                    $accountsToFind[$_customer->AccountId] = $_customer->AccountId;
                }
            }
        }

        if (!empty($accountsToFind)) {
            $lookedupAccounts = Mage::helper('tnw_salesforce/salesforce_data_account')->lookupContactIds($accountsToFind);
            foreach ($lookedupAccounts as $account) {
                if (array_key_exists($account['Id'], $accountsToFind)) {
                    $accountsToFind[$account['Id']] = $account['PersonContactId'];
                }
            }

            foreach ($this->_cache['toSaveInMagento'] as $_websiteId => &$_websiteCustomers) {
                foreach ($_websiteCustomers as $_email => $_customer) {
                    $_customer->AccountId = (property_exists($_customer, 'AccountId')) ? $_customer->AccountId : NULL;
                    if ($_customer->AccountId != NULL && array_key_exists($_customer->AccountId, $accountsToFind)) {
                        $_websiteCustomers[$_email]->SalesforceId = $accountsToFind[$_customer->AccountId];
                    }
                }
            }
        }
    }

    protected function _updateMagento()
    {
        Mage::helper('tnw_salesforce')->log("---------- Start: Magento Update ----------");

        $customerIds = array();
        foreach ($this->_cache['toSaveInMagento'] as $_websiteId => $_websiteCustomers) {
            foreach ($_websiteCustomers as $_customer) {
                if (!is_object($_customer) || !property_exists($_customer, 'MagentoId') || !$_customer->MagentoId || strpos($_customer->MagentoId, 'guest_') === 0) {
                    continue;
                }

                $customerIds[] = $_customer->MagentoId;
                $_customer->SalesforceId = (property_exists($_customer, 'SalesforceId')) ? $_customer->SalesforceId : NULL;
                $_customer->AccountId = (property_exists($_customer, 'AccountId')) ? $_customer->AccountId : NULL;
                $_customer->LeadId = (property_exists($_customer, 'LeadId')) ? $_customer->LeadId : NULL;
                $_customer->IsPersonAccount = (property_exists($_customer, 'IsPersonAccount')) ? $_customer->IsPersonAccount : NULL;
                $_customer->SfInSync = (property_exists($_customer, 'SfInSync')) ? $_customer->SfInSync : 0;
                $_customer->FirstName = (property_exists($_customer, 'FirstName')) ? $_customer->FirstName : NULL;
                $_customer->LastName = (property_exists($_customer, 'LastName')) ? $_customer->LastName : NULL;

                $this->updateMagentoEntityValue($_customer->MagentoId, $_customer->SalesforceId, 'salesforce_id');
                $this->updateMagentoEntityValue($_customer->MagentoId, $_customer->AccountId, 'salesforce_account_id');
                if (!$_customer->SalesforceId || !$_customer->AccountId) {
                    $this->updateMagentoEntityValue($_customer->MagentoId, $_customer->LeadId, 'salesforce_lead_id');
                }

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

        $this->_prepareCampaignMembers($customerIds);

        Mage::helper('tnw_salesforce')->log("---------- End: Magento Update ----------");
    }

    /**
     * prepare campaign members data fo SF
     */
    protected function _prepareCampaignMembers($customerIds)
    {
        $_ids = array_chunk($customerIds, TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT);
        foreach ($_ids as $_recordIds) {

            $subscribers = Mage::getModel('newsletter/subscriber')->getCollection()->addFieldToFilter('customer_id', $_recordIds);
            $subscribers->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);

            foreach ($subscribers as $subscriber) {

                if (!$subscriber->getCustomerId()) {
                    continue;
                }

                $_updatedCustomer = Mage::registry('customer_cached_' . $subscriber->getCustomerId());
                $campaignMemberType = 'LeadId';
                $campaignMemberId = $_updatedCustomer->getSalesforceLeadId();
                if ($_updatedCustomer->getSalesforceId()) {
                    $campaignMemberType = 'ContactId';
                    $campaignMemberId = $_updatedCustomer->getSalesforceId();
                }

                if ($campaignMemberId) {
                    /**
                     * prepare subscriber data to add new members
                     */
                    Mage::helper('tnw_salesforce/salesforce_newslettersubscriber')
                        ->prepareCampaignMember($campaignMemberType, $campaignMemberId, $subscriber, $_updatedCustomer->getEmail());
                }
            }
        }
    }

    public function updateMagentoEntityValue($_customerId = NULL, $_value = 0, $_attributeName = NULL, $_tableName = 'customer_entity_varchar')
    {
        if (empty($_customerId)) {
            Mage::helper('tnw_salesforce')->log("No magento customer id while updating from salesforce");
            return;
        }
        $_table = Mage::helper('tnw_salesforce')->getTable($_tableName);

        if (!$_attributeName) {
            Mage::helper('tnw_salesforce')->log('Could not update Magento customer values: attribute name is not specified', 1, "sf-errors");
            return false;
        }

        $this->_preloadAttributes();

        $sql = '';
        $sqlDelete = '';
        if ($_value || $_value === 0) {
            // Update Account Id
            $sqlCheck = "SELECT value_id FROM `" . $_table . "` WHERE attribute_id = " . $this->_attributes[$_attributeName] . " AND entity_id = " . $_customerId;
            Mage::helper('tnw_salesforce')->log("UPDATE SQL CHECK (attr '" . $_attributeName . "'): " . $sqlCheck);
            $row = Mage::helper('tnw_salesforce')->getDbConnection('read')->query($sqlCheck)->fetch();
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
            Mage::helper('tnw_salesforce')->log("RESET SQL CHECK (attr '" . $_attributeName . "'): " . $sqlCheck);
            $row = Mage::helper('tnw_salesforce')->getDbConnection('read')->query($sqlCheck)->fetch();
            if ($row && array_key_exists('value_id', $row)) {
                //Update
                $sqlDelete .= "DELETE FROM `" . $_table . "` WHERE value_id = " . $row['value_id'] . ";";
            }
        }
        if (!empty($sql)) {
            Mage::helper('tnw_salesforce')->log("SQL: " . $sql);
            Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
        }
        if (!empty($sqlDelete)) {
            Mage::helper('tnw_salesforce')->log("SQL: " . $sqlDelete);
            Mage::helper('tnw_salesforce')->getDbConnection('delete')->query($sqlDelete);
        }
    }

    protected function _pushToSalesforce($_isOrder)
    {
        /**
         * Upsert Accounts
         */
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
                        if (!is_object($this->_cache['toSaveInMagento'][$_websiteId][$_email])) {
                            $this->_cache['toSaveInMagento'][$_websiteId][$_email] = new stdClass();
                        }
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

        /**
         * Upsert Contacts
         */
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
                    $contactId = $_result->id;
                    // Fix Contact Id for PersonAccount, update returns Person Account Id instead of a contact Id
                    if (
                        property_exists($this->_cache['contactsToUpsert']['Id'][$_contactIds[$_key]], 'Id')
                        && $this->_cache['contactsToUpsert']['Id'][$_contactIds[$_key]]->Id != $contactId
                    ) {
                        $contactId = $this->_cache['contactsToUpsert']['Id'][$_contactIds[$_key]]->Id;
                    }

                    $_entitites[] = $contactId;
                    if (array_key_exists('guest_0', $this->_cache['guestsFromOrder'])) {
                        $this->_cache['guestsFromOrder']['guest_0']->setSalesforceId($contactId);
                    }
                    $_email = $this->_cache['entitiesUpdating'][$_contactIds[$_key]];
                    $_websiteId = $this->_getWebsiteIdByCustomerId($_contactIds[$_key]);
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 1;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = $contactId;

                    if (
                        !property_exists($this->_cache['toSaveInMagento'][$_websiteId][$_email], 'AccountId') ||
                        !$this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId
                    ) {
                        if (!property_exists($_result, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject())) {
                            $websiteKey = $_result->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()};
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

        /**
         * Upsert Contacts more
         */
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
                $customerId = $_contactIds[$_key];

                if (property_exists($_result, 'success') && $_result->success) {
                    $_entitites[] = $_result->id;
                    if (array_key_exists('guest_0', $this->_cache['guestsFromOrder'])) {
                        $this->_cache['guestsFromOrder']['guest_0']->setSalesforceId($_result->id);
                    }
                    $_email = $this->_cache['entitiesUpdating'][$customerId];
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = $_result->id;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 1;
                    // Skip Magento update if guest
                    if (array_key_exists('guest_0', $this->_cache['guestsFromOrder'])) {
                        unset($this->_cache['toSaveInMagento'][$_websiteId][$_email]);
                    }
                } else {
                    $this->_processErrors($_result, 'contact', $this->_cache['contactsToUpsert'][$this->_magentoId][$customerId]);
                }
            }

            Mage::helper('tnw_salesforce')->log("Contacts: " . implode(',', $_entitites) . " upserted!");
            Mage::helper('tnw_salesforce')->log("---------- End: Contact Sync ----------");
        }

        /**
         * Upsert Leads
         */
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
                    $this->_processErrors($_result, 'lead', $this->_cache['leadsToUpsert']['Id'][$_contactIds[$_key]]);
                }
            }
            Mage::helper('tnw_salesforce')->log("Leads: " . implode(',', $_entitites) . " upserted!");
            Mage::helper('tnw_salesforce')->log("---------- End: Lead Sync ----------");
        }

        /**
         * Upsert Leads more
         */
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
                    $this->_processErrors($_result, 'lead', $this->_cache['leadsToUpsert']['Id'][$_contactIds[$_key]]);
                }
            }
            Mage::helper('tnw_salesforce')->log("Leads: " . implode(',', $_entitites) . " upserted!");
            Mage::helper('tnw_salesforce')->log("---------- End: Lead Sync ----------");
        }

        $this->_convertLeads();
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

    /**
     *
     */
    protected function _convertLeads()
    {
        return Mage::helper('tnw_salesforce/salesforce_data_lead')->setParent($this)->convertLeadsSimple();
    }

}