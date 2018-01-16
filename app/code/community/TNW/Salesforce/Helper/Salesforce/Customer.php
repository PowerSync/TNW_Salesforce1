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

    /** @var array */
    protected $_statisticFields = array(
        'last_purchase',
        'last_login',
        'last_transaction_id',
        'total_order_count',
        'total_order_amount',
        'first_purchase',
        'first_transaction_id',
    );

    /**
     * @return array
     */
    public function getStatisticFields()
    {
        return $this->_statisticFields;
    }

    /**
     * @param array $statisticFields
     */
    public function setStatisticFields($statisticFields)
    {
        $this->_statisticFields = $statisticFields;
    }

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

        $this->_cache = array(
            'contactsLookup' => array(),
            'leadLookup' => array(),
            'accountLookup' => array(),
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
     * @return Mage_Customer_Model_Customer
     */
    public function generateFakeCustomer($_formData)
    {
        $_email = strtolower($_formData['email']);
        $_websiteId = Mage::app()->getWebsite()->getId();
        $_storeId = Mage::app()->getStore()->getStoreId();

        /**
         * prepare fake customer object to use it in lookup
         * @var Mage_Customer_Model_Customer $fakeCustomer
         */
        $fakeCustomer = Mage::getModel('customer/customer');
        $fakeCustomer->setGroupId(0); // NOT LOGGED IN
        $fakeCustomer->setStoreId($_storeId);
        /**
         * set _tnw_order flag to use the "guest" case
         */
        $fakeCustomer->setData('_tnw_order', 1);

        if (isset($_websiteId)) {
            $fakeCustomer->setWebsiteId($_websiteId);
        }
        $fakeCustomer->addData($_formData);

        $_fullName = explode(' ', strip_tags(trim($_formData['name'])), 2);
        if (count($_fullName) > 1) {
            list($firstName, $lastName) = $_fullName;
        } else {
            $firstName = '';
            $lastName = $_fullName[0];
        }

        $company = (array_key_exists('company', $_formData))
            ? strip_tags($_formData['company'])
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
            ->setTelephone(strip_tags($_formData['telephone']));

        $_shippingAddress = Mage::getModel('customer/address');
        $_shippingAddress->setCustomerId(0)
            ->setId('2')
            ->setIsDefaultShipping('1')
            ->setSaveInAddressBook('0')
            ->setTelephone(strip_tags($_formData['telephone']));

        $_billingAddress->setCompany($company);
        $fakeCustomer->addAddress($_billingAddress);
        $fakeCustomer->addAddress($_shippingAddress);
        $_billingAddress->setCustomer($fakeCustomer);
        $_shippingAddress->setCustomer($fakeCustomer);
        $fakeCustomer->setDefaultBilling(1);
        $fakeCustomer->setDefaultShipping(2);

        return $fakeCustomer;
    }

    /**
     * @param Mage_Customer_Model_Customer[] $fakeCustomer
     * @return array
     */
    protected function _pushFakeCustomers(array $fakeCustomer)
    {
        if ($this->reset() && $this->forceAdd($fakeCustomer)) {
            $this->setForceLeadConvertaton(false);
            $this->process();
        }
        return $fakeCustomer;
    }

    /**
     * @param Mage_Customer_Model_Customer $fakeCustomer
     * @return null|Varien_Object
     */
    protected function _pushFakeCustomer($fakeCustomer)
    {
        if (!is_array($fakeCustomer)) {
            $fakeCustomer = array($fakeCustomer);
        }

        $this->_pushFakeCustomers($fakeCustomer);

        $fakeCustomer = reset($fakeCustomer);

        return $fakeCustomer;
    }

    /**
     * Create Salesforce task
     * @param $_id
     * @param $_data
     */
    protected function _createTask($_id, $_data)
    {
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
        }
    }

    /**
     * @param $_formData
     * @return bool
     */
    public function pushContactUs($_formData)
    {
        if (Mage::helper('tnw_salesforce')->getType() != "PRO") {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("IMPORTANT: Skipping form synchronization, please upgrade to Enterprise version!");
            return false;
        }
        if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
            return false;
        }

        /** @var Mage_Customer_Model_Customer $fakeCustomer */
        $fakeCustomer = $this->generateFakeCustomer($_formData);

        $_websiteId = Mage::app()->getWebsite()->getId();

        $leadSource = (Mage::helper('tnw_salesforce')->useLeadSourceFilter())
            ? Mage::helper('tnw_salesforce')->getLeadSource() : null;

        // Check for Contact and Account
        $contactsLookup = Mage::helper('tnw_salesforce/salesforce_data_contact')
            ->lookup(array($fakeCustomer));

        $_email = $fakeCustomer->getEmail();

        $_id = NULL;
        if (!empty($contactsLookup[$this->_websiteSfIds[$_websiteId]][$_email])) {
            // Existing Contact
            $_id = $contactsLookup[$this->_websiteSfIds[$_websiteId]][$_email]->Id;
        } else {
            $leadLookup = Mage::helper('tnw_salesforce/salesforce_data_lead')
                ->lookup(array($fakeCustomer), $leadSource);

            if (!empty($leadLookup[$this->_websiteSfIds[$_websiteId]][$_email])) {
                // Existing Lead
                $_id = $leadLookup[$this->_websiteSfIds[$_websiteId]][$_email]->Id;
                if ($leadLookup[$this->_websiteSfIds[$_websiteId]][$_email]->IsConverted) {
                    $_id = $leadLookup[$this->_websiteSfIds[$_websiteId]][$_email]->ConvertedContactId;
                }
            } else {
                Mage::register('customer_event_type', 'contact_us');

                $fakeCustomer = $this->_pushFakeCustomer($fakeCustomer);

                Mage::unregister('customer_event_type');


                foreach (array('salesforce_id', 'salesforce_lead_id') as $sfKey) {
                    $_id = $fakeCustomer->getData($sfKey);
                    if (!empty($_id)) {
                        break;
                    }
                }
            }
        }

        $this->_createTask($_id, $_formData);
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

        /** @var TNW_Salesforce_Helper_Salesforce_Campaign_Member $campaignMember */
        $campaignMember = Mage::helper('tnw_salesforce/salesforce_campaign_member');
        if ($campaignMember->reset() && $campaignMember->memberAdd($this->_cache['subscriberToUpsert'])) {
            $campaignMember->process();
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

        //If Lookup returned values add them
        $_email = strtolower($_customer->getEmail());
        $this->_obj = new stdClass();

        $salesforceId = $this->searchSalesforceIdInLookup($_customer, $type);
        if (!empty($salesforceId)) {
            $this->_obj->Id = $salesforceId;
            $_upsertOn = 'Id';
        }

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
            $this->_obj->{$_mapping->getSfField()} = $_mapping->getValue(array_filter($_objectMappings), $this->_obj);
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
        } else if ($type == "Contact") {
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
            } else {
                // Move the prepared Contact data to Person Account
                if (isset($this->_cache['accountsToUpsert']['Id'][$_id])) {
                    foreach ($this->_obj as $_key => $_value) {
                        if (property_exists($this->_cache['accountsToUpsert']['Id'][$_id], $_key) && $_key != 'OwnerId') {
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
        } else if ($type == "Account") {

            $_personType = null;
            if (!empty($this->_obj->RecordTypeId)) {
                $_personType = $this->_obj->RecordTypeId;
            } elseif (!empty($this->_cache['accountLookup'][0][$_email]->RecordTypeId)) {
                $_personType = $this->_cache['accountLookup'][0][$_email]->RecordTypeId;
            }

            $this->_isPerson = (!empty($_personType) && $_personType == Mage::helper('tnw_salesforce')->getPersonAccountRecordType());

            if (property_exists($this->_obj, 'Id')) {
                $this->_cache['accountsToContactLink'][$_id] = $this->_obj->Id;
            }

            $nameCompare = !empty($this->_cache['accountLookup'][0][$_email]->Name)
                && strcasecmp(TNW_Salesforce_Model_Mapping_Type_Customer::generateCompanyByCustomer($_customer), trim($this->_cache['accountLookup'][0][$_email]->Name)) == 0;

            $company = TNW_Salesforce_Model_Mapping_Type_Customer::getCompanyByCustomer($_customer);
            if ($nameCompare && !empty($company)) {
                $this->_obj->Name = $company;
            }

            $this->_cache['accountsToUpsert']['Id'][$_id] = $this->_obj;
        }
    }

    /**
     * @param Mage_Customer_Model_Customer $customer
     * @param string $type
     * @return string
     */
    protected function searchSalesforceIdInLookup($customer, $type)
    {
        // Get Customer Website Id
        $_websiteId = Mage::getSingleton('tnw_salesforce/mapping_type_customer')
            ->getWebsiteId($customer);

        //If Lookup returned values add them
        $_email = strtolower($customer->getEmail());
        $_sfWebsite = strcasecmp($type, 'Account') !== 0
            ? $this->_websiteSfIds[$_websiteId] : 0;

        $_cacheLookup = array(
            'Lead' => 'leadLookup',
            'Contact' => 'contactsLookup',
            'Account' => 'accountLookup',
        );

        $_lookupKey = $_cacheLookup[$type];
        if (!empty($this->_cache[$_lookupKey][$_sfWebsite][$_email])) {
            return $this->_cache[$_lookupKey][$_sfWebsite][$_email]->Id;
        }

        return null;
    }

    /**
     * @param Mage_Customer_Model_Customer $_entity
     * @param $type string
     * @return mixed
     */
    protected function _getObjectByEntityType($_entity, $type)
    {
        switch ($type) {
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

    protected function _fixPersonAccountFields($object)
    {
        $_renameFields = array(
            'Birthdate' => 'PersonBirthdate',
            'AssistantPhone' => 'PersonAssistantPhone',
            'AssistantName' => 'PersonAssistantName',
            'Department' => 'PersonDepartment',
            'DoNotCall' => 'PersonDoNotCall',
            'Email' => 'PersonEmail',
            'HasOptedOutOfEmail' => 'PersonHasOptedOutOfEmail',
            'HasOptedOutOfFax' => 'PersonHasOptedOutOfFax',
            'LastCURequestDate' => 'PersonLastCURequestDate',
            'LastCUUpdateDate' => 'PersonLastCUUpdateDate',
            'LeadSource' => 'PersonLeadSource',
            'MobilePhone' => 'PersonMobilePhone',
            'OtherPhone' => 'PersonOtherPhone',
            'Title' => 'PersonTitle',
            'Phone' => 'PersonHomePhone',

            'OtherStreet' => 'BillingStreet',
            'OtherCity' => 'BillingCity',
            'OtherState' => 'BillingState',
            'OtherStateCode' => 'BillingStateCode',
            'OtherPostalCode' => 'BillingPostalCode',
            'OtherCountry' => 'BillingCountry',
            'OtherCountryCode' => 'BillingCountryCode',

            'MailingStreet' => 'ShippingStreet',
            'MailingCity' => 'ShippingCity',
            'MailingState' => 'ShippingState',
            'MailingStateCode' => 'ShippingStateCode',
            'MailingPostalCode' => 'ShippingPostalCode',
            'MailingCountry' => 'ShippingCountry',
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

    /**
     * check, shall we push lead or not
     * @param $email
     * @return bool|mixed|null|string
     */
    protected function _leadShouldBePushed($id)
    {
        $email = $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING][$id];

        $websiteId = Mage::app()->getWebsite()->getId();
        $_salesforceWebsiteId = $this->_websiteSfIds[$websiteId];

        /**
         * check config flag first
         */
        $_leadShouldBePushed = Mage::helper('tnw_salesforce')->isCustomerAsLead();

        /**
         * if accountLookup has matched record - don't create lead because it's converted already
         */
        $_leadShouldBePushed = $_leadShouldBePushed && !isset($this->_cache['accountLookup'][0][$email]);

        /**
         * if matched lead found - we should update only non-converted Leads
         */
        if (isset($this->_cache['leadLookup'][$_salesforceWebsiteId][$email])) {
            $_info = $this->_cache['leadLookup'][$_salesforceWebsiteId][$email];

            $_leadShouldBePushed = !(bool)$_info->IsConverted;
        }

        return $_leadShouldBePushed;
    }

    /**
     * check, shall we push contact or not
     * @param $email
     * @return bool|mixed|null|string
     */
    protected function _contactShouldBePushed($id)
    {
        $email = $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING][$id];

        $websiteId = Mage::app()->getWebsite()->getId();
        $_salesforceWebsiteId = $this->_websiteSfIds[$websiteId];

        $_contactShouldBePushed = false;
        /**
         * Sync as Account/Contact if leads disabled or lead convertation ebabled
         */
        if (!Mage::helper('tnw_salesforce')->isCustomerAsLead()
            || $this->isForceLeadConvertation()
            || isset($this->_cache['accountLookup'][0][$email])
            || isset($this->_cache['contactLookup'][$_salesforceWebsiteId][$email])
        ) {
            $_contactShouldBePushed = true;
        }

        return $_contactShouldBePushed;
    }
    /**
     * check, shall we push account or not
     * @param $email
     * @return bool|mixed|null|string
     */
    protected function _accountShouldBePushed($id)
    {
        /**
         * we create account by the same reasons as Contact by default
         */
        return $this-> _contactShouldBePushed($id);
    }

    /**
     * add Customer to sync as Lead, Contact, Account
     */
    protected function _prepareCustomer()
    {
        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $_id => $_email) {

            if ($this->_leadShouldBePushed($_id)) {
                $this->_addToQueue($_id, "Lead");
            }

            if ($this->_accountShouldBePushed($_id)) {
                $this->_addToQueue($_id, "Account");
            }

            if ($this->_contactShouldBePushed($_id)) {
                $this->_addToQueue($_id, "Contact");
            }
        }

        /**
         * delete converted leads
         */
            $this->_deleteLeads();

    }

    /**
     * Create Lead object for sync
     * @deprecated, see _prepareCustomer
     */
    protected function _prepareLeads()
    {

    }

    /**
     * @deprecated, see _prepareCustomer
     */
    protected function _prepareContacts()
    {
        $this->_prepareCustomer();
    }

    /**
     * @deprecated, see _prepareCustomer
     */
    protected function _prepareNew()
    {

    }

    protected function _deleteLeads()
    {

        $websiteId = Mage::app()->getWebsite()->getId();
        $_salesforceWebsiteId = $this->_websiteSfIds[$websiteId];

        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $_id => $email) {
            /**
             * if matched lead found - we should update only non-converted Leads
             */
            if (isset($this->_cache['leadLookup'][$_salesforceWebsiteId][$email])) {
                $_info = $this->_cache['leadLookup'][$_salesforceWebsiteId][$email];

                if ($_info->IsConverted) {
                    $this->_toDelete[] = $_info->Id;
                    unset($this->_cache['leadLookup'][$_salesforceWebsiteId][$email]);
                }
            }
        }

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

        $this->_skippedEntity = array();
        try {
            $_existIds = array_filter(array_map(function (Mage_Customer_Model_Customer $_customer) {
                return is_numeric($_customer->getId()) ? $_customer->getId() : null;
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
     * @param $_entity
     * @return string
     */
    public function getEntityId($_entity)
    {
        return $this->_getEntityId($_entity);
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
                                (property_exists($_lead, 'Id') ? " Lead is: " . Mage::helper('tnw_salesforce/salesforce_abstract')->generateLinkToSalesforce($_lead->Id) : "");

                            Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice(Mage::helper('tnw_salesforce')->__($noticeMessage));
                            return $this;
                        }
                    }

                    if (!isset($this->_cache['accountLookup'][0][$_email]) && !isset($this->_cache['contactsLookup'][$_websiteId][$_email])) {
                        continue;
                    }

                    $leadConvert = $this->_prepareLeadConversionObject($_lead, $leadConvert);

                    $this->_cache['leadsToConvert'][$_lead->MagentoId] = $leadConvert;
                }
            }
        }

        return $this;
    }

    /**
     * collect Sales statistic for customers
     * @param $ids
     * @return $this
     */
    protected function _updateCustomerSalesStatistic($ids)
    {

        // 1. Save sales info
        /**
         * prepare query for sales statistic calculation
         */
        /** @var Mage_Sales_Model_Resource_Order_Collection $salesCollection */
        $salesCollection = Mage::getModel('sales/order')->getCollection();
        $salesCollection->removeAllFieldsFromSelect();
        $salesCollection->removeFieldFromSelect($salesCollection->getResource()->getIdFieldName());

        /**
         * add customer_id in result
         */
        $salesCollection->addFieldToSelect('customer_id');

        $salesCollection
            ->addExpressionFieldToSelect('last_purchase', 'MAX(main_table.created_at)', array())
            ->addExpressionFieldToSelect('first_purchase', 'MIN(main_table.created_at)', array());

        /**
         * The "last sales" statistic
         */
        /** @var Mage_Sales_Model_Resource_Order_Collection $subselect */
        $subselect = Mage::getModel('sales/order')->getCollection();

        $subselect->removeAllFieldsFromSelect();
        $subselect->removeFieldFromSelect($salesCollection->getResource()->getIdFieldName());

        $subselect->getSelect()->reset(Zend_Db_Select::FROM);
        $subselect->getSelect()->from(array('subselect' => $salesCollection->getMainTable()), array('increment_id'));

        $subselect->getSelect()->where('subselect.customer_id = main_table.customer_id');
        $subselect->getSelect()->order('created_at ' . Varien_Data_Collection_Db::SORT_ORDER_DESC);

        $subselect->getSelect()->limit(1);

        $salesCollection
            ->getSelect()
            ->columns(array('last_transaction_id' => new Zend_Db_Expr(sprintf('(%s)', $subselect->getSelect()->__toString()))));

        /**
         * The "last sales" statistic
         */
        /** @var Mage_Sales_Model_Resource_Order_Collection $subselect */
        $subselect = Mage::getModel('sales/order')->getCollection();

        $subselect->removeAllFieldsFromSelect();
        $subselect->removeFieldFromSelect($salesCollection->getResource()->getIdFieldName());

        $subselect->getSelect()->reset(Zend_Db_Select::FROM);
        $subselect->getSelect()->from(array('subselect' => $salesCollection->getMainTable()), array('increment_id'));

        $subselect->getSelect()->where('subselect.customer_id = main_table.customer_id');
        $subselect->getSelect()->order('created_at ' . Varien_Data_Collection_Db::SORT_ORDER_ASC);
        $subselect->getSelect()->limit(1);

        $salesCollection
            ->getSelect()
            ->columns(array('first_transaction_id' => new Zend_Db_Expr(sprintf('(%s)', $subselect->getSelect()->__toString()))));

        /**
         * select total_order_count value
         */
        $salesCollection
            ->addExpressionFieldToSelect('total_order_count', "COUNT(*)", array());

        /**
         * select total_order_amount value
         */
        $salesCollection
            ->addExpressionFieldToSelect('total_order_amount', 'SUM(base_grand_total)', array());

        $salesCollection->addFieldToFilter('main_table.customer_id', array('in' => $ids));

        $salesCollection->getSelect()->group('main_table.customer_id');

        return $this->_updateCustomerCacheFromSelect($salesCollection->getSelect());
    }

    /**
     * @param $ids
     *  @return $this
     */
    protected function _updateCustomerLoginStatistic($ids)
    {
        // 2. Save login date

        /**
         * prepare last login date
         */
        $logCustomerResource = Mage::getModel('log/customer')->getResource();
        $select = $logCustomerResource->getReadConnection()->select();

        $select
            ->from(
                $logCustomerResource->getMainTable(),
                array(
                    'customer_id',
                    'login_at AS last_login'
                ));
        $select->where('customer_id IN (?)', $ids);

        return $this->_updateCustomerCacheFromSelect($select);

    }

    /**
     * update cached data
     * @param $select
     * @return $this
     */
    protected function _updateCustomerCacheFromSelect($select)
    {
        foreach (Mage::getModel('customer/customer')->getResource()->getReadConnection()->fetchAssoc($select) as $item) {
            $customer = $this->getEntityCache($item['customer_id']);
            if ($customer) {
                $customer->addData($item);
            }
        }

        return $this;
    }

    /**
     * Update customer statistic data for mapping use
     * @param $ids
     * @return $this
     */
    protected function _updateCustomerStatistic($customerData)
    {

        $ids = array_keys($customerData);

        $this->_updateCustomerSalesStatistic($ids);
        $this->_updateCustomerLoginStatistic($ids);

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
                ->saveNotice("SKIPPING: Sync for customer group #{$_entity->getGroupId()} is disabled!");
            return false;
        }

        $_websiteId = Mage::getSingleton('tnw_salesforce/mapping_type_customer')
            ->getWebsiteId($_entity);

        $this->_websites[$this->_getEntityId($_entity)] = $this->_websiteSfIds[$_websiteId];

        $_email = strtolower($_entity->getData('email'));

        $tmp = new stdClass();
        $tmp->Email = $_email;
        $tmp->MagentoId = $this->_getEntityId($_entity);
        $tmp->SfInSync = 0;

        $this->_cache['toSaveInMagento'][$_websiteId][$_email] = $tmp;
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

        $this->_cache['leadLookup'] = Mage::helper('tnw_salesforce/salesforce_data_lead')
            ->lookup($customers, $leadSource);
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')
            ->lookup($customers);
        $this->_cache['accountLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')
            ->lookup($customers);

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

        if (!empty($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING])) {
            $this->_updateCustomerStatistic($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);
        }

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
                'salesforce_id' => null,
                'salesforce_account_id' => null,
                'salesforce_lead_id' => null,
                'salesforce_is_person' => null,
                'sf_insync' => 0
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
                    Mage::helper('tnw_salesforce')->usePersonAccount()
                    && Mage::helper('tnw_salesforce')->getPersonAccountRecordType()
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

    /**
     *
     */
    protected function _updateMagento()
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- Start: Magento Update ----------");

        foreach ($this->_cache['toSaveInMagento'] as $_websiteId => $_websiteCustomers) {
            foreach ($_websiteCustomers as $_data) {
                if (!is_object($_data) || !property_exists($_data, 'MagentoId') || !$_data->MagentoId) {
                    continue;
                }

                $_saveAttributes = array_filter(array(
                    'salesforce_id' => (property_exists($_data, 'SalesforceId')) ? $_data->SalesforceId : null,
                    'salesforce_account_id' => (property_exists($_data, 'AccountId')) ? $_data->AccountId : null,
                    'salesforce_lead_id' => (property_exists($_data, 'LeadId')) ? $_data->LeadId : null,
                    'salesforce_is_person' => (property_exists($_data, 'IsPersonAccount')) ? $_data->IsPersonAccount : null,
                    'sf_insync' => (property_exists($_data, 'SfInSync')) ? $_data->SfInSync : null,
                    'salesforce_contact_owner_id' => (property_exists($_data, 'ContactOwnerId')) ? $_data->ContactOwnerId : null,
                    'salesforce_account_owner_id' => (property_exists($_data, 'AccountOwnerId')) ? $_data->AccountOwnerId : null,
                    'salesforce_lead_owner_id' => (property_exists($_data, 'LeadOwnerId')) ? $_data->LeadOwnerId : null,
                ));

                $_customer = $this->getEntityCache($_data->MagentoId)
                    ->addData($_saveAttributes);

                /**
                 * skip fake customer, these customer don't exist in magento and use email instead Id
                 */
                $_magentoId = $this->_getEntityId($_customer);
                if (!is_numeric($_magentoId)) {
                    continue;
                }

                foreach (array_keys($_saveAttributes) as $_code) {
                    $_customer->getResource()->saveAttribute($_customer, $_code);
                }

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf("Save attribute (customer: %s)\n%s", $_customer->getEmail(), print_r(array_intersect_key($_customer->getData(), $_saveAttributes), true)));
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Updated: " . count($_websiteCustomers) . " customers!");
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("---------- End: Magento Update ----------");
    }

    /**
     * @param null $_customerId
     * @param int $_value
     * @param null $_attributeName
     * @param string $_tableName
     */
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
         * prefix for special events: ContactUs and soon
         */
        $eventType = null;
        if (Mage::registry('customer_event_type')) {
            $eventType = Mage::registry('customer_event_type');
        }

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
                    "data" => $this->_cache['accountsToUpsert']['Id'],
                    'eventType' => $eventType
                ));

                $_results = $this->getClient()->upsert('Id', array_values($this->_cache['accountsToUpsert']['Id']), 'Account');
                Mage::dispatchEvent("tnw_salesforce_account_send_after", array(
                    "data" => $this->_cache['accountsToUpsert']['Id'],
                    "result" => $_results,
                    'eventType' => $eventType
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
                    if (property_exists($this->_cache['accountsToUpsert']['Id'][$_contactIds[$_key]], 'OwnerId')) {
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->AccountOwnerId
                            = $this->_prepareOwnerId($this->_cache['accountsToUpsert']['Id'][$_contactIds[$_key]]->OwnerId);
                    }

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
                } else {
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
                Mage::dispatchEvent("tnw_salesforce_contact_send_before", array(
                    "data" => $this->_cache['contactsToUpsert']['Id'],
                    'eventType' => $eventType
                ));
                $_results = $this->getClient()->upsert('Id', array_values($this->_cache['contactsToUpsert']['Id']), 'Contact');
                Mage::dispatchEvent("tnw_salesforce_contact_send_after", array(
                    "data" => $this->_cache['contactsToUpsert']['Id'],
                    "result" => $_results,
                    'eventType' => $eventType
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
                    if (property_exists($this->_cache['contactsToUpsert']['Id'][$_contactIds[$_key]], 'OwnerId')) {
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->ContactOwnerId
                            = $this->_prepareOwnerId($this->_cache['contactsToUpsert']['Id'][$_contactIds[$_key]]->OwnerId);
                    }

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
                Mage::dispatchEvent("tnw_salesforce_contact_send_before", array(
                    "data" => $this->_cache['contactsToUpsert'][$this->_magentoId],
                    'eventType' => $eventType
                ));
                $_results = $this->getClient()->upsert($this->_magentoId, array_values($this->_cache['contactsToUpsert'][$this->_magentoId]), 'Contact');
                Mage::dispatchEvent("tnw_salesforce_contact_send_after", array(
                    "data" => $this->_cache['contactsToUpsert'][$this->_magentoId],
                    "result" => $_results,
                    'eventType' => $eventType
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

                    if (!empty($this->_cache['contactsToUpsert'][$this->_magentoId][$_contactIds[$_key]]->OwnerId)) {
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->ContactOwnerId
                            = $this->_prepareOwnerId($this->_cache['contactsToUpsert'][$this->_magentoId][$_contactIds[$_key]]->OwnerId);
                    }

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
                Mage::dispatchEvent("tnw_salesforce_lead_send_before", array(
                    "data" => $this->_cache['leadsToUpsert'][$this->_magentoId],
                    'eventType' => $eventType
                ));
                $_results = $this->getClient()->upsert($this->_magentoId, array_values($this->_cache['leadsToUpsert'][$this->_magentoId]), 'Lead');
                Mage::dispatchEvent("tnw_salesforce_lead_send_after", array(
                    "data" => $this->_cache['leadsToUpsert'][$this->_magentoId],
                    "result" => $_results,
                    'eventType' => $eventType
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

                    if (!empty($this->_cache['leadsToUpsert'][$this->_magentoId][$_contactIds[$_key]]->OwnerId)) {
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadOwnerId
                            = $this->_prepareOwnerId($this->_cache['leadsToUpsert'][$this->_magentoId][$_contactIds[$_key]]->OwnerId);
                    }

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
                Mage::dispatchEvent("tnw_salesforce_lead_send_before", array(
                    "data" => $this->_cache['leadsToUpsert']['Id'],
                    'eventType' => $eventType
                ));
                $_results = $this->getClient()->upsert('Id', array_values($this->_cache['leadsToUpsert']['Id']), 'Lead');
                Mage::dispatchEvent("tnw_salesforce_lead_send_after", array(
                    "data" => $this->_cache['leadsToUpsert']['Id'],
                    "result" => $_results,
                    'eventType' => $eventType
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

                    if (!empty($this->_cache['leadsToUpsert']['Id'][$_contactIds[$_key]]->OwnerId)) {
                        $this->_cache['toSaveInMagento'][$_websiteId][$_email]->LeadOwnerId
                            = $this->_prepareOwnerId($this->_cache['leadsToUpsert']['Id'][$_contactIds[$_key]]->OwnerId);
                    }

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
        return Mage::getSingleton('tnw_salesforce/mapping_type_customer')
            ->getWebsiteId($this->getEntityCache($_customerId));
    }

    /**
     *
     */
    protected function _convertLeads()
    {
        Mage::helper('tnw_salesforce/salesforce_data_lead')->setParent($this)->convertLeadsSimple();
    }

    /**
     * @param $ownerId
     * @return string
     */
    protected function _prepareOwnerId($ownerId)
    {
        return $ownerId;
    }
}