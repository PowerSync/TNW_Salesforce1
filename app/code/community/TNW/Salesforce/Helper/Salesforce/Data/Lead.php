<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Data_Contact
 */
class TNW_Salesforce_Helper_Salesforce_Data_Lead extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * @comment Contains parent object for access to _cache and _websiteSfIds
     * @var null|TNW_Salesforce_Helper_Salesforce_Abstract
     */
    protected $_parent = null;

    /**
     * @comment connect to Salesforce
     */
    public function __construct()
    {
        $this->checkConnection();
    }

    /**
     * @param null $email
     * @param array $ids
     * @return array|bool
     */
    public function lookup($email = NULL, $ids = array(), $leadSource = '', $idPrefix = '')
    {
        $_howMany = 35;
        try {
            if (!is_object($this->getClient())) {
                return false;
            }
            $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";

            $_results = array();

            $_ttl = count($email);
            if ($_ttl > $_howMany) {
                $_steps = ceil($_ttl / $_howMany);
                if ($_steps == 0) {
                    $_steps = 1;
                }
                for ($_i = 0; $_i < $_steps; $_i++) {
                    $_start = $_i * $_howMany;
                    $_emails = array_slice($email, $_start, $_howMany, true);
                    $_results[] = $this->_queryLeads($_magentoId, $_emails, $ids, $leadSource, $idPrefix);
                }
            } else {
                $_results[] = $this->_queryLeads($_magentoId, $email, $ids, $leadSource, $idPrefix);
            }

            unset($query);
            if (empty($_results) || !$_results[0] || $_results[0]->size < 1) {
                Mage::helper('tnw_salesforce')->log("Lookup returned: no results...");
                return false;
            }

            $returnArray = array();
            foreach ($_results as $result) {
                foreach ($result->records as $_item) {
                    $tmp = new stdClass();
                    $tmp->Id = $_item->Id;
                    $tmp->Email = strtolower($_item->Email);
                    $tmp->IsConverted = $_item->IsConverted;
                    $tmp->ConvertedAccountId = (property_exists($_item, 'ConvertedAccountId')) ? $_item->ConvertedAccountId : NULL;
                    $tmp->ConvertedContactId = (property_exists($_item, 'ConvertedContactId')) ? $_item->ConvertedContactId : NULL;
                    $tmp->MagentoId = (property_exists($_item, $_magentoId)) ? $_item->{$_magentoId} : NULL;
                    $tmp->OwnerId = (property_exists($_item, 'OwnerId')) ? $_item->OwnerId : NULL;
                    if (property_exists($_item, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject())) {
                        $_websiteKey = $_item->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()};
                    } else {
                        $_websiteKey = 0;
                        if ($tmp->MagentoId && array_key_exists($tmp->MagentoId, $ids)) {
                            $_websiteKey = $ids[$tmp->MagentoId];
                        }
                        if (!$_websiteKey) {
                            // Guest, grab the first record (create other records if Magento customer scope is not global)
                            if ($tmp->MagentoId && array_key_exists($tmp->MagentoId, $ids)) {
                                $_websiteKey = $ids[$tmp->MagentoId];
                            }
                            if (!$_websiteKey) {
                                // Guest, grab the first record (create other records if Magento customer scope is not global)
                                $_personEmail = (property_exists($_item, 'PersonEmail') && $_item->PersonEmail) ? $tmp->Email : $tmp->Email;
                                $_customerId = array_search($_personEmail, $email);
                                if ($_customerId !== FALSE) {
                                    $_websiteKey = $ids[$_customerId];
                                }
                            }
                        }
                    }
                    if (
                        !$tmp->IsConverted
                        || (
                            $tmp->ConvertedAccountId
                            && $tmp->ConvertedContactId
                        )
                    )
                        $returnArray[$_websiteKey][$tmp->Email] = $tmp;
                }
            }
            return $returnArray;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not find a contact by Magento Email #" . implode(",", $email));
            unset($email);
            return false;
        }
    }

    /**
     * @param $_magentoId
     * @param $emails
     * @param $_websites
     * @return mixed
     */
    protected function _queryLeads($_magentoId, $emails, $_websites, $leadSource = '', $idPrefix = '')
    {
        if (empty($emails)) {
            return array();
        }

        $query = "SELECT ID, OwnerId, Email, IsConverted, ConvertedAccountId, ConvertedContactId, " . $_magentoId . ", " . Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject() . " FROM Lead WHERE ";

        $_lookup = array();
        foreach ($emails as $_id => $_email) {
            if (empty($_email)) {
                continue;
            }
            $tmp = "((Email='" . addslashes($_email) . "'";

            if (
                !empty($_id)
                && $_id != 0
            ) {
                $tmp .= " OR " . $_magentoId . "='" . $idPrefix . $_id . "'";
            }
            $tmp .= ")";
            if (
                Mage::helper('tnw_salesforce')->getCustomerScope() == "1"
                && array_key_exists($_id, $_websites)
            ) {
                $tmp .= " AND (" . Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject() . " = '" . $_websites[$_id] . "' OR " . Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject() . " = '')";
            }
            $tmp .= ")";
            $_lookup[] = $tmp;
        }
        if (empty($_lookup)) {
            return array();
        }
        $query .= '(' . join(' OR ', $_lookup) . ')';

        if ($leadSource) {
            $query .= ' AND LeadSource = \'' . $leadSource . '\' ';
        }

        Mage::helper('tnw_salesforce')->log("QUERY: " . $query);
        try {
            $_result = $this->getClient()->query(($query));
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("ERROR: " . $e->getMessage());
            $_result = array();
        }

        return $_result;
    }

    /**
     * @comment convertation method for Customer Sync
     */
    public function convertLeadsSimple()
    {
        return $this->_convertLeadsSimple();
    }

    /**
     * @comment convertation method for Customer Sync
     */
    protected function _convertLeadsSimple()
    {
        if (!empty($this->_cache['leadsToConvert'])) {
            $leadsToConvertChunks = array_chunk($this->_cache['leadsToConvert'], TNW_Salesforce_Helper_Data::BASE_CONVERT_LIMIT, true);

            foreach ($leadsToConvertChunks as $leadsToConvertChunk) {

                foreach ($leadsToConvertChunk as $_key => $_object) {
                    foreach ($_object as $key => $value) {
                        Mage::helper('tnw_salesforce')->log("(" . $_key . ") Lead Conversion: " . $key . " = '" . $value . "'");
                    }
                }

                $_customerKeys = array_keys($leadsToConvertChunk);

                $_results = $this->_mySforceConnection->convertLead(array_values($leadsToConvertChunk));
                foreach ($_results as $_resultsArray) {
                    foreach ($_resultsArray as $_key => $_result) {
                        if (!property_exists($_result, 'success') || !(int)$_result->success) {
                            $this->_processErrors($_result, 'lead');
                        } else {
                            $_customerId = $_customerKeys[$_key];
                            $_customerEmail = $this->_cache['entitiesUpdating'][$_customerId];

                            $_websiteId = $this->_getWebsiteIdByCustomerId($_customerId);

                            unset($this->_cache['toSaveInMagento'][$_websiteId][$_customerEmail]);

                            // Update Salesforce Id
                            Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, $_result->contactId, 'salesforce_id');
                            // Update Account Id
                            Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, $_result->accountId, 'salesforce_account_id');
                            // Update Lead
                            Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, NULL, 'salesforce_lead_id');
                            // Update Sync Status
                            Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, 1, 'sf_insync', 'customer_entity_int');

                        }
                    }
                }
            }
        }
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
     * @param null $lead
     * @param null $leadConvert
     * @return mixed
     */
    public function prepareLeadConversionObjectSimple($lead = NULL, $leadConvert = NULL)
    {

        return $this->_prepareLeadConversionObjectSimple($lead, $leadConvert);
    }

    /**
     * @param null $lead
     * @param stdClass $leadConvert
     * @return mixed
     */
    protected function _prepareLeadConversionObjectSimple($lead = NULL, $leadConvert = NULL)
    {
        if (!$leadConvert) {
            $leadConvert = new stdClass();
        }

        $leadConvert->convertedStatus = Mage::helper("tnw_salesforce")->getLeadConvertedStatus();

        $leadConvert->doNotCreateOpportunity = 'true';
        $leadConvert->overwriteLeadSource = 'false';
        $leadConvert->sendNotificationEmail = 'false';

        $userHelper = Mage::helper('tnw_salesforce/salesforce_data_user');

        /**
         * @comment fill conversion object by the data from existing lead
         */
        if (!empty($lead)) {


            $leadConvert->leadId = $lead->Id;

            //IMPORTANT: "OwnerId" is a property of source $lead object, "ownerId" - of result $leadConvert object

            // Retain OwnerID if Lead is already assigned, owner should be active and is not queue
            // If not, pull default Owner from Magento configuration
            if (
                property_exists($lead, 'OwnerId')
                && $userHelper->isUserActive($lead->OwnerId)
                && !$userHelper->isQueue($lead->OwnerId)
            ) {
                $leadConvert->ownerId = $lead->OwnerId;

            }
        }
        // Retain ownerId if Lead is already assigned, owner should be active and is not queue
        // If not, pull default Owner from Magento configuration
        if (
            !property_exists($leadConvert, 'ownerId')
            || !$leadConvert->ownerId
            || !$userHelper->isUserActive($leadConvert->ownerId)
            || $userHelper->isQueue($leadConvert->ownerId)
        ) {

            $leadConvert->ownerId = Mage::helper('tnw_salesforce')->getLeadDefaultOwner();
        }

        return $leadConvert;
    }

    public function prepareLeadConversionObject($parentEntityId, $accounts = array(), $parentEntityType = 'order')
    {
        return $this->_prepareLeadConversionObject($parentEntityId, $accounts, $parentEntityType);
    }

    /**
     * @comment Prepare object for lead conversion
     * @param $parentEntityId
     * @param array $accounts
     * @param string $parentEntityType
     * @return bool
     */
    protected function _prepareLeadConversionObject($parentEntityId, $accounts = array(), $parentEntityType = 'order')
    {
        try {

            if (!$this->getParent()) {
                throw new Exception('You should define parent object to access for cache');
            }

            if (!Mage::helper("tnw_salesforce")->getLeadConvertedStatus()) {
                throw new Exception('Converted Lead status is not set in the configuration, cannot proceed!');
            }

            $email = strtolower($this->_cache[$parentEntityType . 'ToEmail'][$parentEntityId]);

            if (isset($this->_cache[$parentEntityType . 'ToWebsiteId'][$parentEntityId])) {
                $websiteId = $this->_cache[$parentEntityType . 'ToWebsiteId'][$parentEntityId];
            } else {
                /**
                 * @comment try to find parent entity in cache or load from database
                 */
                if (!($parentEntity = Mage::registry($parentEntityType . '_cached_' . $parentEntityId))) {
                    $parentEntity = $this->_loadEntity($parentEntityId, $parentEntityType);
                }

                $websiteId = Mage::getModel('core/store')->load($parentEntity->getData('store_id'))->getWebsiteId();
            }

            $salesforceWebsiteId = $this->getWebsiteSfIds($websiteId);

            if (is_array($this->_cache['leadLookup'])
                && array_key_exists($salesforceWebsiteId, $this->_cache['leadLookup'])
                && array_key_exists($email, $this->_cache['leadLookup'][$salesforceWebsiteId])
                && is_object($this->_cache['leadLookup'][$salesforceWebsiteId][$email])
                && !$this->_cache['leadLookup'][$salesforceWebsiteId][$email]->IsConverted
            ) {
                $leadData = $this->_cache['leadLookup'][$salesforceWebsiteId][$email];
                $leadConvert = $this->_prepareLeadConversionObjectSimple($leadData);

                // Attach to existing account
                if (array_key_exists($email, $accounts) && $accounts[$email]) {
                    $leadConvert->accountId = $accounts[$email];
                } elseif (isset($this->_cache['accountLookup'][0][$email])) {
                    $leadConvert->accountId = $this->_cache['accountLookup'][0][$email]->Id;
                }

                if (isset($this->_cache['contactsLookup'][$salesforceWebsiteId][$email])) {
                    $leadConvert->contactId = $this->_cache['contactsLookup'][$salesforceWebsiteId][$email]->Id;
                }


                // Attach to existing account
                if (array_key_exists($email, $accounts) && $accounts[$email]) {
                    $leadConvert->accountId = $accounts[$email];
                } else {
                    //force lookup for accounts here if no accounts found.
                    //search by email domain was made before, search by company name here
                    $customerId = isset($this->_cache[$parentEntityType . 'ToCustomerId'][$parentEntityId])
                        ? $this->_cache[$parentEntityType . 'ToCustomerId'][$parentEntityId] : 'customerId';

                    //use customer entity instead of email to avoid additional load of entity
                    // and fix account (company name) for guest
                    $_email = isset($this->_cache[$parentEntityType . 'Customers'][$parentEntityId])
                        ? strtolower($this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->getEmail())
                        : $email;

                    $accountLookup = Mage::helper('tnw_salesforce/salesforce_data_account')->lookup(
                        array($customerId => $_email),
                        array($customerId => $salesforceWebsiteId)
                    );

                    if (isset($accountLookup[0][$email]) && isset($accountLookup[0][$email]->Id)) {
                        $leadConvert->accountId = $accountLookup[0][$email]->Id;
                    }
                }

                // logs
                foreach ($leadConvert as $key => $value) {
                    Mage::helper('tnw_salesforce')->log("Lead Conversion: " . $key . " = '" . $value . "'");
                }

                if ($leadConvert->leadId && !$this->_cache['leadLookup'][$salesforceWebsiteId][$email]->IsConverted) {
                    $this->_cache['leadsToConvert'][$parentEntityId] = $leadConvert;
                } else {
                    throw new Exception($parentEntityType . ' #' . $parentEntityId . ' - customer (email: ' . $email . ') needs to be synchronized first, aborting!');
                }
            }
        } catch (Exception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING:' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log($e->getMessage(), 1);

            return false;
        }

        $result = isset($this->_cache['leadsToConvert'][$parentEntityId]) ? $this->_cache['leadsToConvert'][$parentEntityId] : null;

        return $result;
    }

    /**
     * @return null|TNW_Salesforce_Helper_Salesforce_Abstract
     */
    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * @param $parent null|TNW_Salesforce_Helper_Salesforce_Abstract
     * @return $this
     */
    public function setParent($parent)
    {
        $this->_parent = $parent;

        /**
         * @comment Passing by Reference
         */
        $this->_cache = &$parent->_cache;

        return $this;
    }

    /**
     * @param $id
     * @param string $type
     * @return mixed
     * @throws Exception
     */
    protected function _loadEntity($id, $type = 'order')
    {
        /**
         * @comment 'Abandoned' type is related to the Quote magento object
         */
        if ($type == 'abandoned') {
            $type = 'quote';
        }
        /**
         * @comment according with code 'qquoteadv/qqadvcustomer' model loading is not necessary
         */

        $loadMethod = 'load';

        switch ($type) {
            case 'order':
                $object = Mage::getModel('sales/' . $type);
                $loadMethod = 'loadByIncrementId';
                break;
            case 'quote':
                $object = Mage::getModel('sales/' . $type);
                $stores = Mage::app()->getStores(true);
                $storeIds = array_keys($stores);
                $object->setSharedStoreIds($storeIds);
                break;
            default:
                throw new Exception('Incorrect entity defined!');
                break;
        }


        return $object->$loadMethod($id);
    }

    /**
     * @param null $key
     * @return string
     */
    public function getWebsiteSfIds($key = null)
    {
        return $this->getParent()->getWebsiteSfIds($key);
    }

    /**
     * @return mixed
     */
    public function isFromCLI()
    {
        return $this->getParent()->isFromCLI();
    }

    /**
     * @return mixed
     */
    public function isCron()
    {
        return $this->getParent()->isCron();
    }

    /**
     * @return bool
     */
    public function convertLeadsBulk($parentEntityType)
    {
        return $this->_convertLeadsBulk($parentEntityType);
    }

    protected function _convertLeadsBulk($parentEntityType)
    {
        try {

            if (!$this->getParent()) {
                throw new Exception('You should define parent object to access for cache');
            }

            $_howMany = 80;
            // Make sure that leadsToConvert cache has unique leads (by email)
            $_leadsToConvert = array();
            foreach ($this->_cache['leadsToConvert'] as $parentEntityId => $_objToConvert) {

                if (!in_array($_objToConvert->leadId, $_leadsToConvert)) {
                    $_leadsToConvert[$parentEntityId] = $_objToConvert->leadId;
                } else {
                    $_source = array_search($_objToConvert->leadId, $_leadsToConvert);
                    $this->_cache['duplicateLeadConversions'][$parentEntityId] = $_source;
                    unset($this->_cache['leadsToConvert'][$parentEntityId]);
                }
            }

            $_ttl = count($this->_cache['leadsToConvert']);
            if ($_ttl > $_howMany) {
                $_steps = ceil($_ttl / $_howMany);
                if ($_steps == 0) {
                    $_steps = 1;
                }
                for ($_i = 0; $_i < $_steps; $_i++) {
                    $_start = $_i * $_howMany;
                    $_itemsToPush = array_slice($this->_cache['leadsToConvert'], $_start, $_howMany, true);
                    $this->_pushLeadSegment($_itemsToPush, $parentEntityType);
                }
            } else {
                $this->_pushLeadSegment($this->_cache['leadsToConvert'], $parentEntityType);
            }

            // Update de duped lead conversion records
            if (!empty($this->_cache['duplicateLeadConversions'])) {
                foreach ($this->_cache['duplicateLeadConversions'] as $_what => $_source) {
                    if (is_array($this->_cache['convertedLeads']) && array_key_exists($_source, $this->_cache['convertedLeads'])) {
                        $this->_cache['convertedLeads'][$_what] = $this->_cache['convertedLeads'][$_source];
                    }
                }
            }
        } catch (Exception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING:' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log($e->getMessage(), 1);

            return false;
        }
    }

    /**
     * @param $_leadsChunkToConvert
     * @param string $parentEntityType
     */
    protected function _pushLeadSegment($_leadsChunkToConvert, $parentEntityType = 'order')
    {
        $results = $this->_mySforceConnection->convertLead(array_values($_leadsChunkToConvert));

        $_keys = array_keys($_leadsChunkToConvert);

        foreach ($results->result as $_key => $_result) {
            $parentEntityId = $_keys[$_key];

            // report transaction
            $this->_cache['responses']['leadsToConvert'][$parentEntityId] = $_result;

            $_email = strtolower($this->_cache[$parentEntityType . 'ToEmail'][$parentEntityId]);

            /**
             * @comment try to find parent entity in cache or load from database
             */
            if (!($parentEntity = Mage::registry($parentEntityType . '_cached_' . $parentEntityId))) {
                $parentEntity = $this->_loadEntity($parentEntityId, $parentEntityType);
            }

            $_websiteId = Mage::getModel('core/store')->load($parentEntity->getData('store_id'))->getWebsiteId();

            $_customerId = (is_object($parentEntity) && $parentEntity->getCustomerId()) ? $parentEntity->getCustomerId() : NULL;
            if (!$_customerId) {

                $_customerId = (is_object($this->_cache[$parentEntityType . 'Customers'][$parentEntityId])) ?
                    $this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->getId() :
                    NULL;
            }

            if (!$_result->success) {
                $this->_cache['leadsFailedToConvert'][$parentEntityId] = $_email;
                // Remove entity from the sync queue
                $keyToRemove = array_search($parentEntityId, $this->_cache['entitiesUpdating']);
                if ($keyToRemove) {
                    unset($this->_cache['entitiesUpdating'][$keyToRemove]);
                }
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Failed to convert Lead for Customer Email (' . $_email . ')');
                }
                Mage::helper('tnw_salesforce')->log('Convert Failed: (email: ' . $_email . ')', 1);
                $this->_processErrors($_result, $parentEntityType, $_leadsChunkToConvert[$parentEntityId]);

            } else {
                Mage::helper('tnw_salesforce')->log('Lead Converted for: (email: ' . $_email . ')');
                if ($_customerId) {

                    Mage::helper('tnw_salesforce')->log('Converted customer: (magento id: ' . $_customerId . ')');

                    $this->_cache['toSaveInMagento'][$_websiteId][$_customerId] = new stdClass();
                    $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->Email = $_email;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->ContactId = $_result->contactId;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->AccountId = $_result->accountId;
                    $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->WebsiteId = $this->getWebsiteSfIds($_websiteId);

                    // Update Salesforce Id
                    Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, $_result->contactId, 'salesforce_id');
                    // Update Account Id
                    Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, $_result->accountId, 'salesforce_account_id');
                    // Reset Lead Value
                    Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, NULL, 'salesforce_lead_id');
                    // Update Sync Status
                    Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, 1, 'sf_insync', 'customer_entity_int');

                    $this->_cache[$parentEntityType . 'Customers'][$parentEntityId] = Mage::getModel("customer/customer")->load($_customerId);
                } else {
                    Mage::helper('tnw_salesforce')->log('Converted customer: (guest)');

                    // For the guest
                    if (array_key_exists($parentEntityId, $this->_cache[$parentEntityType . 'Customers']) && !is_object($this->_cache[$parentEntityType . 'Customers'][$parentEntityId])) {
                        $this->_cache[$parentEntityType . 'Customers'][$parentEntityId] = (is_object($parentEntity)) ? $this->_getCustomer($parentEntity) : Mage::getModel("customer/customer");
                    }
                    $this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->setSalesforceLeadId(NULL);
                    $this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->setSalesforceId($_result->contactId);
                    $this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->setSalesforceAccountId($_result->accountId);
                    // Update Sync Status
                    $this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->setSfInsync(0);
                }

                $this->_cache['convertedLeads'][$parentEntityId] = new stdClass();
                $this->_cache['convertedLeads'][$parentEntityId]->contactId = $_result->contactId;
                $this->_cache['convertedLeads'][$parentEntityId]->accountId = $_result->accountId;
                $this->_cache['convertedLeads'][$parentEntityId]->email = $_email;

                unset($this->_cache['leadsToConvert'][$parentEntityId]); // remove from cache
                unset($this->_cache['leadLookup'][$_websiteId][$_email]); // remove from cache

                Mage::helper('tnw_salesforce')->log('Converted: (account: ' . $this->_cache['convertedLeads'][$parentEntityId]->accountId . ') and (contact: ' . $this->_cache['convertedLeads'][$parentEntityId]->contactId . ')');
            }
        }
    }

    public function convertLeads($parentEntityType)
    {
        return $this->_convertLeads($parentEntityType);
    }

    /**
     * @param $parentEntityType
     * @return bool
     */
    protected function _convertLeads($parentEntityType)
    {
        try {

            if (!$this->getParent()) {
                throw new Exception('You should define parent object to access for cache');
            }

            // Make sure that leadsToConvert cache has unique leads (by email)
            $_emailsForConvertedLeads = array();
            foreach ($this->_cache['leadsToConvert'] as $parentEntityId => $_objToConvert) {
                if (!in_array($this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->getEmail(), $_emailsForConvertedLeads)) {
                    $_emailsForConvertedLeads[] = $this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->getEmail();
                } else {
                    unset($this->_cache['leadsToConvert'][$parentEntityId]);
                }
            }

            $results = $this->_mySforceConnection->convertLead(array_values($this->_cache['leadsToConvert']));
            $_keys = array_keys($this->_cache['leadsToConvert']);
            foreach ($results->result as $_key => $_result) {
                $parentEntityId = $_keys[$_key];

                //Report Transaction
                $this->_cache['responses']['leadsToConvert'][$parentEntityId] = $_result;

                $_email = strtolower($this->_cache[$parentEntityType . 'ToEmail'][$parentEntityId]);

                if (!($parentEntity = Mage::registry($parentEntityType . '_cached_' . $parentEntityId))) {
                    $parentEntity = $this->_loadEntity($parentEntityId, $parentEntityType);
                }

                $_customerId = (is_object($parentEntity) && $parentEntity->getCustomerId()) ? $parentEntity->getCustomerId() : NULL;
                if (!$_customerId) {

                    $_customerId = (is_object($this->_cache[$parentEntityType . 'Customers'][$parentEntityId])) ?
                        $this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->getId() :
                        NULL;
                }


                if (!$_result->success) {
                    if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                        Mage::getSingleton('adminhtml/session')->addError('WARNING: Failed to convert Lead for Customer Email (' . $this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->getEmail() . ')');
                    }
                    Mage::helper('tnw_salesforce')->log('Convert Failed: (email: ' . $this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->getEmail() . ')', 1, "sf-errors");
                    if ($_customerId) {
                        // Update Sync Status
                        Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, 0, 'sf_insync', 'customer_entity_int');
                    }
                    $this->_processErrors($_result, 'convertLead', $this->_cache['leadsToConvert'][$parentEntityId]);
                } else {
                    Mage::helper('tnw_salesforce')->log('Lead Converted for: (email: ' . $_email . ')');

                    $_email = strtolower($this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->getEmail());
                    $_websiteId = $this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->getData('website_id');
                    if ($_customerId) {
                        Mage::helper('tnw_salesforce')->log('Converted customer: (magento id: ' . $_customerId . ')');

                        $this->_cache['toSaveInMagento'][$_websiteId][$_customerId] = new stdClass();
                        $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->Email = $_email;
                        $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->ContactId = $_result->contactId;
                        $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->AccountId = $_result->accountId;
                        $this->_cache['toSaveInMagento'][$_websiteId][$_customerId]->WebsiteId = $this->getWebsiteSfIds($_websiteId);

                        // Update Salesforce Id
                        Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, $_result->contactId, 'salesforce_id');
                        // Update Account Id
                        Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, $_result->accountId, 'salesforce_account_id');
                        // Reset Lead Value
                        Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, NULL, 'salesforce_lead_id');
                        // Update Sync Status
                        Mage::helper('tnw_salesforce/salesforce_customer')->updateMagentoEntityValue($_customerId, 1, 'sf_insync', 'customer_entity_int');

                        $this->_cache[$parentEntityType . 'Customers'][$parentEntityId] = Mage::getModel("customer/customer")->load($_customerId);
                    } else {
                        Mage::helper('tnw_salesforce')->log('Converted customer: (guest)');
                    }

                    // Update current customer values
                    $this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->setSalesforceLeadId(NULL);
                    $this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->setSalesforceId($_result->contactId);
                    $this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->setSalesforceAccountId($_result->accountId);
                    // Update Sync Status
                    $this->_cache[$parentEntityType . 'Customers'][$parentEntityId]->setSfInsync(0);

                    $this->_cache['convertedLeads'][$parentEntityId] = new stdClass();
                    $this->_cache['convertedLeads'][$parentEntityId]->contactId = $_result->contactId;
                    $this->_cache['convertedLeads'][$parentEntityId]->accountId = $_result->accountId;
                    $this->_cache['convertedLeads'][$parentEntityId]->email = $_email;

                    Mage::helper('tnw_salesforce')->log('Converted: (account: ' . $this->_cache['convertedLeads'][$parentEntityId]->accountId . ') and (contact: ' . $this->_cache['convertedLeads'][$parentEntityId]->contactId . ')');

                }
            }
        } catch (Exception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING:' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log($e->getMessage(), 1);

            return false;
        }
    }

    /**
     * @param Mage_Customer_Model_Customer $customer
     *
     * @return string
     */
    public function getCompanyByCustomer(Mage_Customer_Model_Customer $customer)
    {
        //company from customer
        $company = $customer->getCompany();

        //set company from billing address
        if (!$company && $customer->getDefaultBillingAddress()) {
            $address = $customer->getDefaultBillingAddress();
            if ($address->getCompany() && strlen($address->getCompany())) {
                $company = $address->getCompany();
            }
        }

        //set from domains
        if (!$company) {
            $lookupByDomain = Mage::helper('tnw_salesforce/salesforce_data_account')->lookupByEmailDomain(
                array($customer->getEmail() => $customer->getEmail()));
            if (!empty($lookupByDomain) && isset($lookupByDomain[$customer->getEmail()]->Name)) {
                $company = $lookupByDomain[$customer->getEmail()]->Name;
            }
        }

        //set company from firstname + lastname
        if (!$company && !Mage::helper("tnw_salesforce")->createPersonAccount()) {
            $company = $customer->getFirstname() . " " . $customer->getLastname();
        }

        return trim($company);
    }
}