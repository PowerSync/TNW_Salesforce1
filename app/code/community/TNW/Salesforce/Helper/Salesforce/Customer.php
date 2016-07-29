<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Customer
 *
 * @method Mage_Customer_Model_Customer _loadEntityByCache($_key, $cachePrefix = null)
 * @method Mage_Customer_Model_Customer getEntityCache($cachePrefix)
 */
class TNW_Salesforce_Helper_Salesforce_Customer extends TNW_Salesforce_Helper_Salesforce_Abstract_Base
{
    /**
     * @comment magento entity alias "convert from"
     * @var string
     */
    protected $_magentoEntityName = 'customer';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityModel = 'customer/customer';

    /**
     * @var null
     */
    protected $_currentCustomer = NULL;

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

    /**
     * If true - found lead will be converted
     * @var bool
     */
    protected $_forceLeadConvertation = false;

    /**
     * @var array
     */
    protected $_customerGroups = array();

    /**
     * @var array
     */
    protected $_websites = array();

    /**
     * @return boolean
     */
    public function isForceLeadConvertation()
    {
        return $this->_forceLeadConvertation;
    }

    /**
     * alias for isForceLeadConvertation method
     * @return boolean
     */
    public function getForceLeadConvertatoin()
    {
        return $this->isForceLeadConvertation();
    }

    /**
     * @param boolean $forceLeadConvertation
     * @return $this
     */
    public function setForceLeadConvertaton($forceLeadConvertation)
    {
        $this->_forceLeadConvertation = $forceLeadConvertation;
        return $this;
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

    public function reset()
    {
        $this->setForceLeadConvertaton(false);
        parent::reset();

        if (is_array($this->_cache['entitiesUpdating'])) {
            foreach ($this->_cache['entitiesUpdating'] as $_id => $_email) {
                $this->unsetEntityCache($_id);
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
            'subscriberToUpsert' => array(),
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
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("IMPORTANT: Skipping form synchronization, please upgrade to Enterprise version!");
            return false;
        }
        if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
            return false;
        }

        $logger = Mage::helper('tnw_salesforce/report');
        $logger->reset();

        $_data = $_formData;
        $_email = strtolower($_data['email']);
        $_websiteId = Mage::app()->getWebsite()->getId();
        $_storeId = Mage::app()->getStore()->getStoreId();

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
         * @var Mage_Customer_Model_Customer $fakeCustomer
         */
        $fakeCustomer = Mage::getModel('customer/customer');
        $fakeCustomer->setGroupId(0); // NOT LOGGED IN
        $fakeCustomer->setStoreId($_storeId);
        if (isset($_websiteId)) {
            $fakeCustomer->setWebsiteId($_websiteId);
        }
        $fakeCustomer->addData($_data);


        $firstName = ($_lastName) ? $_fullName[0] : '';
        $lastName = ($_lastName) ? $_lastName : $_fullName[0];
        $company = (array_key_exists('company', $_data))
            ? strip_tags($_data['company'])
            : implode(' ', $_fullName);

        $fakeCustomer->setFirstname($firstName);
        $fakeCustomer->setLastname($lastName);
        $fakeCustomer->setCompany($company);

        $fakeCustomer->setEmail($_email);

        $_billingAddress = Mage::getModel('customer/address');
        $_billingAddress->setCustomerId(0)
            ->setId('1')
            ->setIsDefaultBilling('1')
            ->setSaveInAddressBook('0')
            ->setTelephone(strip_tags($_data['telephone']));
        $_billingAddress->setCompany($company);
        $fakeCustomer->addAddress($_billingAddress);
        $_billingAddress->setCustomer($fakeCustomer);
        $fakeCustomer->setDefaultBilling(1);

        $customerId = (int)$fakeCustomer->getId();
        if (Mage::registry('customer_cached_' . $customerId)) {
            Mage::unregister('customer_cached_' . $customerId);
        }

        Mage::register('customer_cached_' . $customerId, $fakeCustomer);


        $leadSource = (Mage::helper('tnw_salesforce')->useLeadSourceFilter())
            ? Mage::helper('tnw_salesforce')->getLeadSource() : null;

        // Check for Contact and Account
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')
            ->lookup(array($fakeCustomer));
        $this->_cache['accountLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')
            ->lookup(array($fakeCustomer));
        $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')
            ->lookup(array($fakeCustomer), $leadSource);

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

            /** @var tnw_salesforce_model_mysql4_mapping_collection $_mappingCollection */
            $_mappingCollection = Mage::getResourceModel('tnw_salesforce/mapping_collection')
                ->addObjectToFilter('Lead')
                ->addFilterTypeMS(false)
                ->firstSystem();

            $_objectMappings = array();
            foreach (array_unique($_mappingCollection->walk('getLocalFieldType')) as $_type) {
                $_objectMappings[$_type] = $this->_getObjectByEntityType($fakeCustomer, $_type);
            }

            /** @var tnw_salesforce_model_mapping $_mapping */
            foreach ($_mappingCollection as $_mapping) {
                $this->_obj->{$_mapping->getSfField()} = $_mapping->getValue(array_filter($_objectMappings));
            }

            // Unset attribute
            foreach ($this->_obj as $_key => $_value) {
                if (null !== $_value) {
                    continue;
                }

                unset($this->_obj->{$_key});
            }

            $this->_cache['leadsToUpsert']['contactUs'] = $this->_obj;

            $assignmentRule = Mage::helper('tnw_salesforce')->isLeadRule();
            if (!empty($assignmentRule) && $assignmentRule != "" && $assignmentRule != 0) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Assignment Rule used: " . $assignmentRule);
                $header = new Salesforce_AssignmentRuleHeader($assignmentRule, false);
                $this->getClient()->setAssignmentRuleHeader($header);
                unset($assignmentRule, $header);
            }

            $_keys = array_keys($this->_cache['leadsToUpsert']);
            Mage::dispatchEvent("tnw_salesforce_lead_send_before", array("data" => $this->_cache['leadsToUpsert']));
            $_results = $this->getClient()->upsert('Id', array_values($this->_cache['leadsToUpsert']), 'Lead');
            Mage::dispatchEvent("tnw_salesforce_lead_send_after", array(
                "data" => $this->_cache['leadsToUpsert'],
                "result" => $_results
            ));
            foreach ($_results as $_key => $_result) {
                $this->_cache['responses']['leads']['contactUs'] = $_result;
                if (property_exists($_result, 'success') && $_result->success) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SUCCESS: Lead upserted (id: ' . $_result->id . ')');
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
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Task Object: " . $key . " = '" . $value . "'");
            }

            Mage::dispatchEvent("tnw_salesforce_task_send_before", array("data" => array($this->_obj)));
            $_results = $this->getClient()->upsert('Id', array($this->_obj), 'Task');
            Mage::dispatchEvent("tnw_salesforce_task_send_after", array(
                "data" => array($this->_obj),
                "result" => $_results
            ));
            $_sfResult = array();
            foreach ($_results as $_key => $_result) {
                $_sfResult['note'] = $_result;
                if (property_exists($_result, 'success') && $_result->success) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SUCCESS: Task created (id: ' . $_result->id . ')');
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
     * @param string $type
     * @return bool|mixed
     */
    public function process($type = 'soft')
    {
        try {

            if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
                throw new Exception('could not establish Salesforce connection.');
            }

            $_syncType = stripos(get_class($this), '_bulk_') !== false ? 'MASS' : 'REALTIME';
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf("================ %s SYNC: START ================", $_syncType));

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
            $this->_pushToSalesforce();
            $this->clearMemory();

            //Deal with Person Accounts
            $this->_personAccountUpdate();

            // Update Magento
            if ($this->_customerEntityTypeCode) {
                $this->_updateMagento();
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("WARNING: Failed to update Magento with Salesforce Ids. Try manual synchronization.");
            }

            /**
             * send data to Salesforce
             */
            $this->_prepareCampaignMembers();
            $this->_updateCampaings();
            $this->_onComplete();

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf("================= %s SYNC: END =================", $_syncType));
            return true;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: " . $e->getMessage());

            return false;
        }
    }

    /**
     * prepare campaign members data fo SF
     */
    protected function _prepareCampaignMembers()
    {
        if (!Mage::helper('tnw_salesforce/salesforce_newslettersubscriber')->validateSync()) {
            return;
        }

        $campaignId = strval(Mage::helper('tnw_salesforce')->getCutomerCampaignId());
        if (empty($campaignId)) {
            return;
        }

        $customers = array();
        $chunks = array_chunk($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING], TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);
        foreach ($chunks as $chunk) {

            /** @var Mage_Newsletter_Model_Resource_Subscriber_Collection $subscribers */
            $subscribers = Mage::getModel('newsletter/subscriber')->getCollection();
            $subscribers
                ->addFieldToFilter('subscriber_email', array_values($chunk))
                ->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);

            /** @var Mage_Newsletter_Model_Subscriber $subscriber */
            foreach ($subscribers as $subscriber) {
                $customers[] = $this->getEntityCache(array_search(strtolower($subscriber->getEmail()), $chunk));
            }
        }

        if (empty($customers)) {
            return;
        }

        $this->_cache['subscriberToUpsert'] = array($campaignId => $customers);
    }

    /**
     * push data to Salesforce
     */
    protected function _updateCampaings()
    {
        if (empty($this->_cache['subscriberToUpsert'])) {
            return;
        }

        $campaignMember = Mage::helper('tnw_salesforce/salesforce_campaign_member');
        if ($campaignMember->reset() && $campaignMember->memberAdd($this->_cache['subscriberToUpsert'])) {
            $campaignMember->process();
        }
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
        if (!in_array($type, array('Lead', 'Contact', 'Account'))) {
            return;
        }

        /** @var Mage_Customer_Model_Customer $_customer */
        $_customer = $this->getEntityCache($_id);
        $_upsertOn = (strpos($_id, 'guest_') === 0)
            ? 'Id' : $this->_magentoId;

        if (!$_customer) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError("Could not add customer to Lead queue, could not load customer by ID (" . $_id . ") from Magento.");

            return;
        }

        // Get Customer Website Id
        $_websiteId = $_customer->getData('website_id');

        //If Lookup returned values add them
        $_email = strtolower($_customer->getEmail());
        $_sfWebsite = (isset($this->_websiteSfIds[$_websiteId]) && ($type != 'Account'))
            ? $this->_websiteSfIds[$_websiteId] : 0;

        $this->_obj = new stdClass();

        $_cacheLookup = array(
            'Lead'    => 'leadLookup',
            'Contact' => 'contactsLookup',
            'Account' => 'accountLookup',
        );

        $_lookupKey = $_cacheLookup[$type];
        if (isset($this->_cache[$_lookupKey][$_sfWebsite])
            && array_key_exists($_email, $this->_cache[$_lookupKey][$_sfWebsite])
        ) {
            $this->_obj->Id = $this->_cache[$_lookupKey][$_sfWebsite][$_email]->Id;
            $_upsertOn = 'Id';
        }

        $this->_assignOwner($_customer, $type, $_sfWebsite);

        /** @var tnw_salesforce_model_mysql4_mapping_collection $_mappingCollection */
        $_mappingCollection = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addObjectToFilter($type)
            ->addFilterTypeMS(property_exists($this->_obj, 'Id') && $this->_obj->Id)
            ->firstSystem();

        $_objectMappings = array();
        foreach (array_unique($_mappingCollection->walk('getLocalFieldType')) as $_type) {
            $_objectMappings[$_type] = $this->_getObjectByEntityType($_customer, $_type);
        }

        /** @var tnw_salesforce_model_mapping $_mapping */
        foreach ($_mappingCollection as $_mapping) {
            $this->_obj->{$_mapping->getSfField()} = $_mapping->getValue(array_filter($_objectMappings));
        }

        // Unset attribute
        foreach ($this->_obj as $_key => $_value) {
            if (null !== $_value) {
                continue;
            }

            unset($this->_obj->{$_key});
        }

        // Add to queue
        if ($type == "Lead") {
            $this->_cache['leadsToUpsert'][$_upsertOn][$_id] = $this->_obj;
        }
        else if ($type == "Contact") {
            if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
                $syncParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . "disableMagentoSync__c";
                $this->_obj->$syncParam = true;
            }

            // Set Contact AccountId as suggested by Advanced Lookup
            if (!$this->_isPerson) {
                // Set Conjoint AccountId
                if (isset($this->_cache['accountLookup'][0][$_email])) {
                    $this->_obj->AccountId = $this->_cache['accountLookup'][0][$_email]->Id;
                }

                $this->_cache['contactsToUpsert'][$_upsertOn][$_id] = $this->_obj;
            }
            else {
                // Move the prepared Contact data to Person Account
                if (
                    array_key_exists('Id', $this->_cache['accountsToUpsert'])
                    && array_key_exists($_id, $this->_cache['accountsToUpsert']['Id'])
                ) {
                    foreach ($this->_obj as $_key => $_value) {
                        if (property_exists($this->_cache['accountsToUpsert']['Id'][$_id], $_key)) {
                            continue;
                        }

                        /**
                         * the PersonAccount field names have "__pc" postfix, but Contact field names have the "__c" postfix
                         */
                        if (preg_match('/^.*__c$/', $_key)) {
                            $_key = preg_replace('/__c$/', '__pc', $_key);
                        }

                        $this->_cache['accountsToUpsert']['Id'][$_id]->{$_key} = $_value;
                    }
                }

                $this->_fixPersonAccountFields($this->_cache['accountsToUpsert']['Id'][$_id]);
            }
        }
        else if ($type == "Account") {
            /**
             * At the present time not possible change RecordType if some other date defined for account sync
             */
            if (property_exists($this->_obj, 'Id') /*&& !property_exists($this->_obj, 'RecordTypeId')*/) {
                $this->_obj->RecordTypeId = ($this->_cache['accountLookup'][0][$_email]->RecordTypeId)
                    ? $this->_cache['accountLookup'][0][$_email]->RecordTypeId
                    : Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::BUSINESS_RECORD_TYPE);
            }
            elseif (isset($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email])) {
                $this->_obj->RecordTypeId
                    = Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::BUSINESS_RECORD_TYPE);
            }

            $_personType = Mage::app()->getWebsite($_websiteId)->getConfig(TNW_Salesforce_Helper_Data::PERSON_RECORD_TYPE);
            $this->_isPerson = (property_exists($this->_obj, 'RecordTypeId') && !empty($this->_obj->RecordTypeId) && $this->_obj->RecordTypeId == $_personType);

            if (property_exists($this->_obj, 'Id')) {
                $this->_cache['accountsToContactLink'][$_id] = $this->_obj->Id;
            }

            //Unset Record Type if blank
            if (property_exists($this->_obj, 'RecordTypeId') && empty($this->_obj->RecordTypeId)) {
                unset($this->_obj->RecordTypeId);
            }

            $this->_cache['accountsToUpsert']['Id'][$_id] = $this->_obj;
        }
    }

    /**
     * @param Mage_Customer_Model_Customer $_entity
     * @param $type string
     * @return mixed
     */
    protected function _getObjectByEntityType($_entity, $type)
    {
        switch($type) {
            case 'Customer':
                $_object = $_entity;
                break;

            case 'Customer Group':
                if (!isset($this->_customerGroups[$_entity->getGroupId()])) {
                    $this->_customerGroups[$_entity->getGroupId()] = Mage::getModel('customer/group')
                        ->load($_entity->getGroupId());
                }

                $_object = $this->_customerGroups[$_entity->getGroupId()];
                break;

            case 'Billing':
                $_object = $_entity->getDefaultBillingAddress();
                break;

            case 'Shipping':
                $_object = $_entity->getDefaultShippingAddress();
                break;

            case 'Custom':
                $_object = $_entity->getStore();
                break;

            default:
                $_object = null;
                break;
        }

        return $_object;
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
        switch ($type) {
            case 'Lead':
                $defaultOwner = Mage::helper('tnw_salesforce')->getLeadDefaultOwner();
                break;

            default:
                $defaultOwner = Mage::helper('tnw_salesforce')->getDefaultOwner();
                break;
        }

        if ($defaultOwner) {
            $this->_obj->OwnerId = $defaultOwner;
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
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($type . " record already assigned to " . $_ownerID);

            } else {
                $_ownerID = $defaultOwner;
            }

            $this->_obj->OwnerId = $_ownerID;
        }

    }

    protected function _fixPersonAccountFields($object)
    {
        $_renameFields = array(
            'Birthdate'          => 'PersonBirthdate',
            'AssistantPhone'     => 'PersonAssistantPhone',
            'AssistantName'      => 'PersonAssistantName',
            'Department'         => 'PersonDepartment',
            'DoNotCall'          => 'PersonDoNotCall',
            'Email'              => 'PersonEmail',
            'HasOptedOutOfEmail' => 'PersonHasOptedOutOfEmail',
            'HasOptedOutOfFax'   => 'PersonHasOptedOutOfFax',
            'LastCURequestDate'  => 'PersonLastCURequestDate',
            'LastCUUpdateDate'   => 'PersonLastCUUpdateDate',
            'LeadSource'         => 'PersonLeadSource',
            'MobilePhone'        => 'PersonMobilePhone',
            'OtherPhone'         => 'PersonOtherPhone',
            'Title'              => 'PersonTitle',
            'Phone'              => 'PersonHomePhone',

            'OtherStreet'        => 'BillingStreet',
            'OtherCity'          => 'BillingCity',
            'OtherState'         => 'BillingState',
            'OtherStateCode'     => 'BillingStateCode',
            'OtherPostalCode'    => 'BillingPostalCode',
            'OtherCountry'       => 'BillingCountry',
            'OtherCountryCode'   => 'BillingCountryCode',

            'MailingStreet'      => 'ShippingStreet',
            'MailingCity'        => 'ShippingCity',
            'MailingState'       => 'ShippingState',
            'MailingStateCode'   => 'ShippingStateCode',
            'MailingPostalCode'  => 'ShippingPostalCode',
            'MailingCountry'     => 'ShippingCountry',
            'MailingCountryCode' => 'ShippingCountryCode',
        );

        foreach ($_renameFields as $oField => $rField) {
            if (!property_exists($object, $oField)) {
                continue;
            }

            $object->{$rField} = $object->{$oField};
            unset($object->{$oField});
        }

        // Unset AccountId
        if (property_exists($object, 'AccountId')) {
            unset($object->AccountId);
        }

        // Unset Name
        if (property_exists($object, 'Name')) {
            unset($object->Name);
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
                    Mage::helper('tnw_salesforce')->isCustomerAsLead()
                    && !isset($this->_cache['accountLookup'][0][$_email])
                    && !isset($this->_cache['leadLookup'][$this->_cache['customerToWebsite'][$_id]][$_email])
                ) {
                    $this->_addToQueue($_id, "Lead");
                }

                /**
                 * Sync as Account/Contact if leads disabled or lead convertation ebabled
                 */
                if (!Mage::helper('tnw_salesforce')->isCustomerAsLead() || $this->isForceLeadConvertation() || isset($this->_cache['accountLookup'][0][$_email])) {
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
            $this->getClient()->delete($_recordIds);
        }
    }

    /**
     * @param Mage_Customer_Model_Customer[] $_customers
     * @return bool
     */
    public function forceAdd(array $_customers)
    {
        /**
         * forceAdd method used for order sync process
         * if lead sync enabled and order placed - we should convert lead to account + contact
         */
        $this->setForceLeadConvertaton(true);

        // test sf api connection
        /** @var TNW_Salesforce_Model_Connection $_client */
        $_client = Mage::getSingleton('tnw_salesforce/connection');
        if (!$_client->initConnection()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR on sync entity, sf api connection failed");

            return false;
        }

        $this->_skippedEntity = array();
        try {
            $_existIds = array_filter(array_map(function(Mage_Customer_Model_Customer $_customer){
                return is_numeric($_customer->getId())? $_customer->getId(): null;
            }, $_customers));

            $this->_massAddBefore($_existIds);
            foreach ($_customers as $_orderNum => $_customer) {
                $_customer->setData('_tnw_order', $_orderNum);

                $this->setEntityCache($_customer);
                $entityId = $this->_getEntityId($_customer);

                if (!$this->_checkMassAddEntity($_customer)) {
                    $this->_skippedEntity[$entityId] = $entityId;
                    continue;
                }

                // Associate order ID with order Number
                $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING][$entityId] = strtolower($_customer->getData('email'));
            }

            if (empty($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING])) {
                return false;
            }

            $this->_massAddAfter();
            $this->resetEntity(array_diff($_existIds, $this->_skippedEntity));

            return !empty($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: " . $e->getMessage());
            return false;
        }
    }

    /**
     * @param $_entity Mage_Customer_Model_Customer
     * @return string
     */
    protected function _getEntityId($_entity)
    {
        if (!$_entity->getId() && $_entity->hasData('_tnw_order')) {
            return 'guest_' . $_entity->getData('_tnw_order');
        }

        return $_entity->getId();
    }

    /**
     * find leads from lookup and generate convertation object
     * update initial emails array: remove found emails
     * @return $this
     */
    public function findLeadsForConversion()
    {
        /**
         * @comment try to find lead
         */
        if (!empty($this->_cache['leadLookup'])) {
            foreach ($this->_cache['leadLookup'] as $_websiteId => $_leads) {
                foreach ($_leads as $_email => $_lead) {
                    if (property_exists($_lead, 'IsConverted') && $_lead->IsConverted) {
                        // TODO: if no contacts found, confirm that new contact and account should be created.
                        continue;
                    }

                    if (!$_lead->Id) {
                        // Skip if there is no Lead ID
                        continue;
                    }

                    $leadConvert = new stdClass();

                    $force = isset($this->_cache['accountLookup'][0][$_email]) || $this->isForceLeadConvertation();
                    if (!$force
                        && Mage::helper('tnw_salesforce')->isCustomerAsLead()
                    ) {
                        continue;
                    }

                    if (isset($this->_cache['accountLookup'][0][$_email])) {
                        $leadConvert->accountId = $this->_cache['accountLookup'][0][$_email]->Id;
                    }

                    if (
                        isset($this->_cache['contactsLookup'][$_websiteId][$_email])
                        && (!property_exists($this->_cache['contactsLookup'][$_websiteId][$_email], 'IsPersonAccount')
                            || !$this->_cache['contactsLookup'][$_websiteId][$_email]->IsPersonAccount)
                    ) {
                        $leadConvert->contactId = $this->_cache['contactsLookup'][$_websiteId][$_email]->Id;

                    } elseif (isset($this->_cache['contactsLookup'][$_websiteId][$_email])
                        && property_exists($this->_cache['contactsLookup'][$_websiteId][$_email], 'IsPersonAccount')
                        && $this->_cache['contactsLookup'][$_websiteId][$_email]->IsPersonAccount
                    ) {

                        /**
                         * We cannot convert Lead with company name to Person Account
                         */
                        if (property_exists($_lead, 'Company') && !empty($_lead->Company) && $_lead->Company != ' ') {

                            $noticeMessage = "Notice: PowerSync has tried converting a Lead for customer ($_email).
                            Company name field on the Lead has value suggesting a B2B Account,
                            however Salesforce has an existing PersonAccount for this customer.
                            Either delete a duplicate Lead and try again, or make sure Company field on a Lead is empty.
                            Other option is to remove the PersonAccount and try again" .
                            (property_exists($leadConvert, 'accountId') ? " Account is: " . Mage::helper('tnw_salesforce/salesforce_abstract')->generateLinkToSalesforce($leadConvert->accountId) : "") .
                            (property_exists($_lead, 'Id') ? " Lead is: " . Mage::helper('tnw_salesforce/salesforce_abstract')->generateLinkToSalesforce($_lead->Id) : "")
                            ;

                            Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice(Mage::helper('tnw_salesforce')->__($noticeMessage));
                            return $this;
                        }
                    }

                    $leadConvert = $this->_prepareLeadConversionObject($_lead, $leadConvert);

                    $this->_cache['leadsToConvert'][$_lead->MagentoId] = $leadConvert;
                }
            }
        }

        return $this;
    }

    /**
     * Update customer statistic data for using in mapping
     * @param $ids
     * @return $this
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
        $salesCollection->addExpressionFieldToSelect('last_purchase', 'MAX(created_at)', array());

        /**
         * salect last_transaction_id
         */
        $fields[] = 'last_transaction_id';
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

        foreach ($ids as $customerId) {

            /**
             * reload customer if it saved in cache
             */
            if ($customer = Mage::registry('customer_cached_' . $customerId)) {
                $updatedCustomer = Mage::getModel('customer/customer');
                $updatedCustomer->getResource()->load($updatedCustomer, $customerId);

                /**
                 * If customer exists - just update data, some information can be defined via order sync (order address)
                 */
                $customer->addData($updatedCustomer->getData());
            }
        }
        return $this;
    }

    /**
     * @param $_entity Mage_Customer_Model_Customer
     * @return mixed
     * @throws Exception
     */
    protected function _generateKeyPrefixEntityCache($_entity)
    {
        return $this->_getEntityId($_entity);
    }

    /**
     * @param $_entity Mage_Customer_Model_Customer
     * @return mixed
     * @throws Exception
     */
    protected function _getEntityNumber($_entity)
    {
        return strtolower($_entity->getData('email'));
    }

    /**
     * @param array $_ids
     */
    protected function _massAddBefore($_ids)
    {
        $this->_websites = array();
        $this->_updateCustomerStatistic($_ids);
    }

    /**
     * @param $_entity Mage_Customer_Model_Customer
     * @return bool
     * @throws Exception
     */
    protected function _checkMassAddEntity($_entity)
    {
        if (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($_entity->getGroupId())) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice("SKIPPING: Sync for customer group #" . $_entity->getGroupId() . " is disabled!");
            return false;
        }

        if ($_entity->getData('website_id') != NULL) {
            $this->_websites[$this->_getEntityId($_entity)] = $this->_websiteSfIds[$_entity->getData('website_id')];
        }

        $_email = strtolower($_entity->getData('email'));

        $tmp = new stdClass();
        $tmp->Email = $_email;
        $tmp->MagentoId = $this->_getEntityId($_entity);
        $tmp->SfInSync = 0;

        $this->_cache['toSaveInMagento'][$_entity->getData('website_id')][$_email] = $tmp;
        return true;
    }

    /**
     *
     */
    protected function _massAddAfter()
    {
        $customers = array();
        foreach (array_keys($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]) as $id) {
            $customers[] = $this->getEntityCache($id);
        }

        foreach (array_chunk($customers, TNW_Salesforce_Helper_Data::BASE_CONVERT_LIMIT) as $_customers) {
            Mage::helper('tnw_salesforce/salesforce_data_user')
                ->setCache($this->_cache)
                ->processDuplicates($_customers);
        }

        $this->_cache['customerToWebsite'] = $this->_websites;
        $leadSource = (Mage::helper('tnw_salesforce/data')->useLeadSourceFilter())
            ? Mage::helper('tnw_salesforce/data')->getLeadSource() : null;

        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')
            ->lookup($customers);
        $this->_cache['accountLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')
            ->lookup($customers);
        $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')
            ->lookup($customers, $leadSource);

        $_emailsArray = $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING];
        foreach ($_emailsArray as $_key => $_email) {
            if (
                isset($this->_cache['contactsLookup'][$this->_websites[$_key]])
                && (
                    array_key_exists($_email, $this->_cache['contactsLookup'][$this->_websites[$_key]])
                    || array_key_exists($_key, $this->_cache['contactsLookup'][$this->_websites[$_key]])
                )
            ) {
                if (array_key_exists($_key, $this->_cache['contactsLookup'][$this->_websites[$_key]])) {
                    $this->_cache['contactsLookup'][$this->_websites[$_key]][$_email] = $this->_cache['contactsLookup'][$this->_websites[$_key]][$_key];
                    unset($this->_cache['contactsLookup'][$this->_websites[$_key]][$_key]);
                }

                unset($_emailsArray[$_key]);
            }
        }

        if (Mage::helper('tnw_salesforce')->isCustomerAsLead() && !$this->isForceLeadConvertation()) {
            foreach ($_emailsArray as $_key => $_email) {
                if (!isset($this->_cache['leadLookup'][$this->_websites[$_key]][$_email])) {
                    continue;
                }

                /**
                 * @comment remove from array if customer found as lead or lead is converted but no related account+contact
                 */
                if (
                    !$this->_cache['leadLookup'][$this->_websites[$_key]][$_email]->IsConverted
                    || !isset($this->_cache['accountLookup'][0][$_email])
                    || !isset($this->_cache['contactsLookup'][$this->_websites[$_key]][$_email])
                ) {
                    unset($_emailsArray[$_key]);
                }
            }
        }

        $this->_cache['notFoundCustomers'] = $_emailsArray;
    }

    /**
     * @param $ids
     * Reset Salesforce ID in Magento for the order
     */
    public function resetEntity($ids)
    {
        if (empty($ids)) {
            return;
        }

        $ids = !is_array($ids)
            ? array($ids) : $ids;

        foreach ($ids as $id) {

            $resetAttribute = array(
                'salesforce_id'         => null,
                'salesforce_account_id' => null,
                'salesforce_lead_id'    => null,
                'salesforce_is_person'  => null,
                'sf_insync'             => 0
            );

            $customer = $this->getEntityCache($id)
                ->addData($resetAttribute);

            foreach (array_keys($resetAttribute) as $code) {
                $customer->getResource()->saveAttribute($customer, $code);
            }
        }

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf("%s ID and Sync Status for %s (#%s) were reset.",
                $this->_magentoEntityName, $this->_magentoEntityName, join(',', $ids)));
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

            foreach ($this->_cache['toSaveInMagento'] as $_websiteId => $_websiteCustomers) {
                foreach ($_websiteCustomers as $_email => $_customer) {
                    $_customer->AccountId = (property_exists($_customer, 'AccountId')) ? $_customer->AccountId : NULL;
                    if ($_customer->AccountId != NULL && array_key_exists($_customer->AccountId, $accountsToFind)) {
                        $_customer->SalesforceId = $accountsToFind[$_customer->AccountId];
                    }
                }
            }
        }
    }

    protected function _updateMagento()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- Start: Magento Update ----------");

        foreach ($this->_cache['toSaveInMagento'] as $_websiteId => $_websiteCustomers) {
            foreach ($_websiteCustomers as $_data) {
                if (!is_object($_data) || !property_exists($_data, 'MagentoId') || !$_data->MagentoId || strpos($_data->MagentoId, 'guest_') === 0) {
                    continue;
                }

                $_saveAttributes = array_filter(array(
                    'salesforce_id'         => (property_exists($_data, 'SalesforceId')) ? $_data->SalesforceId : null,
                    'salesforce_account_id' => (property_exists($_data, 'AccountId')) ? $_data->AccountId : null,
                    'salesforce_lead_id'    => (property_exists($_data, 'LeadId')) ? $_data->LeadId : null,
                    'salesforce_is_person'  => (property_exists($_data, 'IsPersonAccount')) ? $_data->IsPersonAccount : null,
                    'sf_insync'             => (property_exists($_data, 'SfInSync')) ? $_data->SfInSync : null
                ));

                $_customer = $this->getEntityCache($_data->MagentoId)
                    ->addData($_saveAttributes);

                if (!$_customer->getId()) {
                    continue;
                }

                foreach (array_keys($_saveAttributes) as $_code) {
                    $_customer->getResource()->saveAttribute($_customer, $_code);
                }
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Updated: " . count($_websiteCustomers) . " customers!");
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- End: Magento Update ----------");
    }

    public function updateMagentoEntityValue($_customerId = NULL, $_value = 0, $_attributeName = NULL, $_tableName = 'customer_entity_varchar')
    {
        if (empty($_customerId)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("No magento customer id while updating from salesforce");
            return;
        }
        $_table = Mage::helper('tnw_salesforce')->getTable($_tableName);

        if (!$_attributeName) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Could not update Magento customer values: attribute name is not specified');
            return;
        }

        $this->_preloadAttributes();

        $sql = '';
        $sqlDelete = '';
        if ($_value || $_value === 0) {
            // Update Account Id
            $sqlCheck = "SELECT value_id FROM `" . $_table . "` WHERE attribute_id = " . $this->_attributes[$_attributeName] . " AND entity_id = " . $_customerId;
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("UPDATE SQL CHECK (attr '" . $_attributeName . "'): " . $sqlCheck);
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
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("RESET SQL CHECK (attr '" . $_attributeName . "'): " . $sqlCheck);
            $row = Mage::helper('tnw_salesforce')->getDbConnection('read')->query($sqlCheck)->fetch();
            if ($row && array_key_exists('value_id', $row)) {
                //Update
                $sqlDelete .= "DELETE FROM `" . $_table . "` WHERE value_id = " . $row['value_id'] . ";";
            }
        }
        if (!empty($sql)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SQL: " . $sql);
            Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
        }
        if (!empty($sqlDelete)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SQL: " . $sqlDelete);
            Mage::helper('tnw_salesforce')->getDbConnection('delete')->query($sqlDelete);
        }
    }

    protected function _pushToSalesforce()
    {
        /**
         * Upsert Accounts
         */
        if (!empty($this->_cache['accountsToUpsert']['Id'])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- Start: Account Sync ----------");
            $this->_dumpObjectToLog($this->_cache['accountsToUpsert']['Id'], 'Account');

            // Accounts upsert
            $_contactIds = array_keys($this->_cache['accountsToUpsert']['Id']);
            try {
                Mage::dispatchEvent("tnw_salesforce_account_send_before", array(
                    "data" => $this->_cache['accountsToUpsert']['Id']
                ));

                $_results = $this->getClient()->upsert('Id', array_values($this->_cache['accountsToUpsert']['Id']), 'Account');
                Mage::dispatchEvent("tnw_salesforce_account_send_after", array(
                    "data" => $this->_cache['accountsToUpsert']['Id'],
                    "result" => $_results
                ));
            } catch (Exception $e) {
                $_results = array_fill(0, count($_contactIds),
                    $this->_buildErrorResponse($e->getMessage()));

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('CRITICAL: Push of accounts to SalesForce failed' . $e->getMessage());
            }

            $_entitites = array();
            foreach ($_results as $_key => $_result) {
                //Report Transaction
                $this->_cache['responses']['accounts'][$_contactIds[$_key]] = $_result;

                if (property_exists($_result, 'success') && $_result->success) {
                    $_entitites[] = $_result->id;
                    $_customer = $this->getEntityCache($_contactIds[$_key]);
                    $_customer->setSalesforceAccountId($_result->id);

                    $_email = $this->_cache['entitiesUpdating'][$_contactIds[$_key]];
                    $_websiteId = $this->_getWebsiteIdByCustomerId($_contactIds[$_key]);

                    if (
                        array_key_exists($_contactIds[$_key], $this->_cache['accountsToUpsert']['Id'])
                        && !property_exists($this->_cache['accountsToUpsert']['Id'][$_contactIds[$_key]], 'PersonEmail')
                    ) {
                        foreach ($this->_cache['contactsToUpsert'] as $_upsertOn => $_objects) {
                            if (array_key_exists($_contactIds[$_key], $_objects)) {
                                $this->_cache['contactsToUpsert'][$_upsertOn][$_contactIds[$_key]]->AccountId = $_result->id;
                            }
                        }

                        /**
                         * If lead has not Company name - set Account name for correct converting
                         */
                        foreach ($this->_cache['leadsToUpsert'] as $upsertOn => $leadsToUpsert) {
                            if (array_key_exists($_contactIds[$_key], $leadsToUpsert)) {
                                if (!property_exists($leadsToUpsert[$_contactIds[$_key]], 'Company')) {
                                    $this->_cache['leadsToUpsert'][$upsertOn][$_contactIds[$_key]]->Company = $this->_cache['accountsToUpsert']['Id'][$_contactIds[$_key]]->Name;
                                }
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

                        /**
                         * If sync PersonAccount - set empty Lead's Company name for correct converting
                         */
                        foreach ($this->_cache['leadsToUpsert'] as $upsertOn => $leadsToUpsert) {
                            if (array_key_exists($_contactIds[$_key], $leadsToUpsert)) {
                                $this->_cache['leadsToUpsert'][$upsertOn][$_contactIds[$_key]]->Company = ' ';
                            }
                        }
                    }

                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountId = $_result->id;

                    /**
                     * Update lookup for lead convertation
                     */
                    if (array_key_exists($_contactIds[$_key], $this->_cache['accountsToUpsert']['Id'])) {

                        $this->_cache['accountsToUpsert']['Id'][$_contactIds[$_key]]->Id = $_result->id;
                        $this->_cache['accountLookup'][0][$_email] = $this->_cache['accountsToUpsert']['Id'][$_contactIds[$_key]];
                        if (property_exists($this->_cache['accountLookup'][0][$_email], $this->_magentoId)) {
                            $this->_cache['accountLookup'][0][$_email]->MagentoId = $this->_cache['accountLookup'][0][$_email]->{$this->_magentoId};
                        } else {
                            $this->_cache['accountLookup'][0][$_email]->MagentoId = $_contactIds[$_key];
                        }

                        if (property_exists($this->_cache['accountsToUpsert']['Id'][$_contactIds[$_key]], 'PersonEmail')) {
                            if (!isset($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email])) {
                                $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email] = new stdClass();
                            }
                            $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->Id = (string)$_result->id;
                            $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->IsPersonAccount = true;
                        }
                    }
                }
                else {
                    $this->_processErrors($_result, 'account', $this->_cache['accountsToUpsert']['Id'][$_contactIds[$_key]]);
                    //Force Skip Contact Update
                    unset($this->_cache['contactsToUpsert']['Id'][$_contactIds[$_key]], $this->_cache['contactsToUpsert'][$this->_magentoId][$_contactIds[$_key]]);
                }
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Accounts: " . implode(',', $_entitites) . " upserted!");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- End: Account Sync ----------");
        }

        /**
         * Upsert Contacts
         */
        // On Id
        if (!empty($this->_cache['contactsToUpsert']['Id'])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- Start: Contact Sync ----------");
            $this->_dumpObjectToLog($this->_cache['contactsToUpsert']['Id'], 'Contact');
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Contacts: on Id");

            $_contactIds = array_keys($this->_cache['contactsToUpsert']['Id']);
            try {
                Mage::dispatchEvent("tnw_salesforce_contact_send_before", array("data" => $this->_cache['contactsToUpsert']['Id']));
                $_results = $this->getClient()->upsert('Id', array_values($this->_cache['contactsToUpsert']['Id']), 'Contact');
                Mage::dispatchEvent("tnw_salesforce_contact_send_after", array(
                    "data" => $this->_cache['contactsToUpsert']['Id'],
                    "result" => $_results
                ));
            } catch (Exception $e) {
                $_results = array_fill(0, count($_contactIds),
                    $this->_buildErrorResponse($e->getMessage()));

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('CRITICAL: Push of contact to SalesForce failed' . $e->getMessage());
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

                    $_customer = $this->getEntityCache($_contactIds[$_key]);
                    $_customer->setSalesforceId($contactId);

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

                    /**
                     * Update lookup for lead convertation
                     */
                    if (array_key_exists($_contactIds[$_key], $this->_cache['contactsToUpsert']['Id'])) {
                        $this->_cache['contactsToUpsert']['Id'][$_contactIds[$_key]]->Id = $contactId;
                        $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email] = $this->_cache['contactsToUpsert']['Id'][$_contactIds[$_key]];
                        if (property_exists($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email], $this->_magentoId)) {
                            $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId = $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->{$this->_magentoId};
                        } else {
                            $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId = $_contactIds[$_key];
                        }
                    }
                } else {
                    $this->_processErrors($_result, 'contact', $this->_cache['contactsToUpsert']['Id'][$_contactIds[$_key]]);
                }
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Contacts: " . implode(',', $_entitites) . " upserted!");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- End: Contact Sync ----------");
        }

        /**
         * Upsert Contacts more
         */
        // On Magento Id
        if (!empty($this->_cache['contactsToUpsert'][$this->_magentoId])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- Start: Contact Sync ----------");
            $this->_dumpObjectToLog($this->_cache['contactsToUpsert'][$this->_magentoId], 'Contact');

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Contacts: on " . $this->_magentoId);

            $_contactIds = array_keys($this->_cache['contactsToUpsert'][$this->_magentoId]);
            try {
                Mage::dispatchEvent("tnw_salesforce_contact_send_before", array("data" => $this->_cache['contactsToUpsert'][$this->_magentoId]));
                $_results = $this->getClient()->upsert($this->_magentoId, array_values($this->_cache['contactsToUpsert'][$this->_magentoId]), 'Contact');
                Mage::dispatchEvent("tnw_salesforce_contact_send_after", array(
                    "data" => $this->_cache['contactsToUpsert'][$this->_magentoId],
                    "result" => $_results
                ));
            } catch (Exception $e) {
                $_results = array_fill(0, count($_contactIds),
                    $this->_buildErrorResponse($e->getMessage()));

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('CRITICAL: Push of contact to SalesForce failed' . $e->getMessage());
            }
            $_entitites = array();

            foreach ($_results as $_key => $_result) {
                //Report Transaction
                $this->_cache['responses']['contacts'][$_contactIds[$_key]] = $_result;
                $_websiteId = $this->_getWebsiteIdByCustomerId($_contactIds[$_key]);
                $customerId = $_contactIds[$_key];

                if (property_exists($_result, 'success') && $_result->success) {
                    $_entitites[] = $_result->id;

                    $_customer = $this->getEntityCache($_contactIds[$_key]);
                    $_customer->setSalesforceId($_result->id);

                    $_email = $this->_cache['entitiesUpdating'][$customerId];
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SalesforceId = $_result->id;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_email]->SfInSync = 1;

                    /**
                     * Update lookup for lead convertation
                     */
                    if (array_key_exists($_contactIds[$_key], $this->_cache['contactsToUpsert'][$this->_magentoId])) {
                        $this->_cache['contactsToUpsert'][$this->_magentoId][$_contactIds[$_key]]->Id = $_result->id;
                        $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email] = $this->_cache['contactsToUpsert'][$this->_magentoId][$_contactIds[$_key]];
                        if (property_exists($this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email], $this->_magentoId)) {
                            $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId = $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->{$this->_magentoId};
                        } else {
                            $this->_cache['contactsLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId = $_contactIds[$_key];
                        }
                    }

                } else {
                    $this->_processErrors($_result, 'contact', $this->_cache['contactsToUpsert'][$this->_magentoId][$customerId]);
                }
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Contacts: " . implode(',', $_entitites) . " upserted!");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- End: Contact Sync ----------");
        }

        /**
         * Upsert Leads
         */
        // On Magento ID
        if (!empty($this->_cache['leadsToUpsert'][$this->_magentoId])) {
            // Lead Sync
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- Start: Lead Sync ----------");
            $this->_dumpObjectToLog($this->_cache['leadsToUpsert'][$this->_magentoId], 'Lead');

            $assignmentRule = Mage::helper('tnw_salesforce')->isLeadRule();
            if (!empty($assignmentRule) && $assignmentRule != "" && $assignmentRule != 0) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Assignment Rule used: " . $assignmentRule);
                $header = new Salesforce_AssignmentRuleHeader($assignmentRule, false);
                $this->getClient()->setAssignmentRuleHeader($header);
                unset($assignmentRule, $header);
            }

            $_contactIds = array_keys($this->_cache['leadsToUpsert'][$this->_magentoId]);
            try {
                Mage::dispatchEvent("tnw_salesforce_lead_send_before", array("data" => $this->_cache['leadsToUpsert'][$this->_magentoId]));
                $_results = $this->getClient()->upsert($this->_magentoId, array_values($this->_cache['leadsToUpsert'][$this->_magentoId]), 'Lead');
                Mage::dispatchEvent("tnw_salesforce_lead_send_after", array(
                    "data" => $this->_cache['leadsToUpsert'][$this->_magentoId],
                    "result" => $_results
                ));
            } catch (Exception $e) {
                $_results = array_fill(0, count($_contactIds),
                    $this->_buildErrorResponse($e->getMessage()));

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('CRITICAL: Push of contact to SalesForce failed' . $e->getMessage());
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

                    $_customer = $this->getEntityCache($_contactIds[$_key]);
                    $_customer->setSalesforceLeadId($_result->id);

                    /**
                     * Update lookup for lead convertation
                     */
                    if (array_key_exists($_contactIds[$_key], $this->_cache['leadsToUpsert'][$this->_magentoId])) {
                        $this->_cache['leadsToUpsert'][$this->_magentoId][$_contactIds[$_key]]->Id = $_result->id;

                        if (!empty($this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email])) {
                            foreach ($this->_cache['leadsToUpsert'][$this->_magentoId][$_contactIds[$_key]] as $field => $value) {
                                $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->$field = $value;
                            }

                            if (property_exists($this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email], $this->_magentoId)) {
                                $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId = $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->{$this->_magentoId};
                            } else {
                                $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId = $_contactIds[$_key];
                            }
                        }
                    }

                } else {
                    $this->_processErrors($_result, 'lead', $this->_cache['leadsToUpsert'][$this->_magentoId][$_contactIds[$_key]]);
                }
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Leads: " . implode(',', $_entitites) . " upserted!");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- End: Lead Sync ----------");
        }

        /**
         * Upsert Leads more
         */
        // On Id
        if (!empty($this->_cache['leadsToUpsert']['Id'])) {
            // Lead Sync
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- Start: Lead Sync ----------");
            $this->_dumpObjectToLog($this->_cache['leadsToUpsert']['Id'], 'Lead');

            $assignmentRule = Mage::helper('tnw_salesforce')->isLeadRule();
            if (!empty($assignmentRule) && $assignmentRule != "" && $assignmentRule != 0) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Assignment Rule used: " . $assignmentRule);
                $header = new Salesforce_AssignmentRuleHeader($assignmentRule, false);
                $this->getClient()->setAssignmentRuleHeader($header);
                unset($assignmentRule, $header);
            }

            $_contactIds = array_keys($this->_cache['leadsToUpsert']['Id']);
            try {
                Mage::dispatchEvent("tnw_salesforce_lead_send_before", array("data" => $this->_cache['leadsToUpsert']['Id']));
                $_results = $this->getClient()->upsert('Id', array_values($this->_cache['leadsToUpsert']['Id']), 'Lead');
                Mage::dispatchEvent("tnw_salesforce_lead_send_after", array(
                    "data" => $this->_cache['leadsToUpsert']['Id'],
                    "result" => $_results
                ));
            } catch (Exception $e) {
                $_results = array_fill(0, count($_contactIds),
                    $this->_buildErrorResponse($e->getMessage()));

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('CRITICAL: Push of contact to SalesForce failed' . $e->getMessage());
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

                    $_customer = $this->getEntityCache($_contactIds[$_key]);
                    $_customer->setSalesforceLeadId($_result->id);

                    /**
                     * Update lookup for lead convertation
                     */
                    if (array_key_exists($_contactIds[$_key], $this->_cache['leadsToUpsert']['Id'])) {
                        $this->_cache['leadsToUpsert']['Id'][$_contactIds[$_key]]->Id = $_result->id;

                        if (isset($this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email])) {
                            foreach ($this->_cache['leadsToUpsert']['Id'][$_contactIds[$_key]] as $field => $value) {
                                $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->$field = $value;
                            }
                            if (property_exists($this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email], $this->_magentoId)) {
                                $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId = $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->{$this->_magentoId};
                            } else {
                                $this->_cache['leadLookup'][$this->_websiteSfIds[$_websiteId]][$_email]->MagentoId = $_contactIds[$_key];
                            }
                        }
                    }

                } else {
                    $this->_processErrors($_result, 'lead', $this->_cache['leadsToUpsert']['Id'][$_contactIds[$_key]]);
                }
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Leads: " . implode(',', $_entitites) . " upserted!");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- End: Lead Sync ----------");
        }

        $this->findLeadsForConversion();
        $this->_convertLeads();
    }

    /**
     * @param $_customerId
     * @return mixed
     * Extract Website ID from customer by customer ID (including guest)
     */
    protected function _getWebsiteIdByCustomerId($_customerId)
    {
        return $this->getEntityCache($_customerId)->getWebsiteId();
    }

    /**
     *
     */
    protected function _convertLeads()
    {
        Mage::helper('tnw_salesforce/salesforce_data_lead')->setParent($this)->convertLeadsSimple();
    }

}