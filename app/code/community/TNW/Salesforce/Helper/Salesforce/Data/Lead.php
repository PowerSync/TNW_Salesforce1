<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce_Data_Lead extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * @comment Contains parent object for access to _cache and _websiteSfIds
     * @var null|TNW_Salesforce_Helper_Salesforce_Abstract
     */
    protected $_parent = null;

    /**
     * @param Mage_Customer_Model_Customer[] $customers
     * @param string $leadSource
     * @param string $idPrefix
     * @return array
     * @throws Mage_Core_Exception
     */
    public function lookup($customers, $leadSource = '', $idPrefix = '')
    {
        try {
            if (!is_object($this->getClient())) {
                return array();
            }

            $returnArray = array();
            foreach ($this->customLookup($customers, $leadSource, $idPrefix) as $item) {
                $return = $this->prepareRecord($item['customer'], $item['record']);
                if (empty($return)) {
                    continue;
                }

                list($website, $entityData) = each($return);
                if (!isset($returnArray[$website])) {
                    $returnArray[$website] = array();
                }

                $returnArray[$website] = array_merge($returnArray[$website], $entityData);
            }

            return $returnArray;
        }
        catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());

            $email = array_map(function ($customer) {return $customer->getEmail();}, $customers);
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("Could not find a contact by Magento Email #" . implode(",", $email));

            return array();
        }
    }

    /**
     * @param $customers Mage_Customer_Model_Customer[]
     * @param string $leadSource
     * @param string $idPrefix
     * @return array|bool
     * @throws Mage_Core_Exception
     */
    public function customLookup($customers, $leadSource = '', $idPrefix = '')
    {
        $_magentoId      = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
        $websiteFieldKey = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();

        $_results = array();
        foreach (array_chunk($customers, self::UPDATE_LIMIT, true) as $_customers) {
            $result = $this->_queryLeads($_magentoId, $_customers, $leadSource, $idPrefix);
            if (empty($result) || $result->size < 1) {
                continue;
            }

            $_results[] = $result;
        }

        if (empty($_results)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Lead lookup returned: no results...");
            return array();
        }

        $recordsEmail = $recordsMagentoId = array();
        $records = $this->mergeRecords($_results);
        foreach ($records as $key => $record) {
            // Index Email
            $recordsEmail[$key] = null;
            if (!empty($record->Email)) {
                $recordsEmail[$key] = strtolower($record->Email);
            }

            // Index MagentoId
            $recordsMagentoId[$key] = null;
            if (!empty($record->$_magentoId)) {
                $recordsMagentoId[$key] = $record->$_magentoId;
            }
        }

        $returnArray = array();
        foreach ($customers as $customer) {
            $_websiteKey = Mage::app()
                ->getWebsite($customer->getWebsiteId())
                ->getData('salesforce_id');

            $recordsIds = array();
            $recordsIds[] = array_keys($recordsMagentoId, $customer->getId());
            $recordsIds[] = array_keys($recordsEmail, strtolower($customer->getEmail()));

            $record = null;
            foreach ($recordsIds as $_recordsIds) {
                foreach ($_recordsIds as $recordsId) {
                    if (!isset($records[$recordsId])) {
                        continue;
                    }

                    if (empty($records[$recordsId]->$websiteFieldKey)) {
                        $record = $records[$recordsId];
                        continue;
                    }

                    if ($records[$recordsId]->$websiteFieldKey == $_websiteKey) {
                        $record = $records[$recordsId];
                        break;
                    }
                }

                if (!empty($record)) {
                    break;
                }
            }

            if (empty($record)) {
                continue;
            }

            $returnArray[] = array(
                'customer' => $customer,
                'record'   => $record
            );
        }

        return $returnArray;
    }

    /**
     * @param $customer Mage_Customer_Model_Customer
     * @param $record stdClass
     * @return array
     * @throws Mage_Core_Exception
     */
    public function prepareRecord($customer, $record)
    {
        $_magentoId  = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";

        $_websiteKey = Mage::app()
            ->getWebsite($customer->getWebsiteId())
            ->getData('salesforce_id');

        $tmp = new stdClass();
        $tmp->Id = $record->Id;
        $tmp->Email = strtolower($record->Email);
        $tmp->IsConverted = $record->IsConverted;
        $tmp->Company = (property_exists($record, 'Company')) ? $record->Company : null;
        $tmp->ConvertedAccountId = (property_exists($record, 'ConvertedAccountId')) ? $record->ConvertedAccountId : null;
        $tmp->ConvertedContactId = (property_exists($record, 'ConvertedContactId')) ? $record->ConvertedContactId : null;
        $tmp->MagentoId = (property_exists($record, $_magentoId)) ? $record->{$_magentoId} : null;
        $tmp->OwnerId = (property_exists($record, 'OwnerId')) ? $record->OwnerId : null;

        if ($tmp->Company) {
            $company = $customer->getData('company');

            if (empty($company)) {
                $company = $customer->getDefaultBillingAddress()
                    ? $customer->getDefaultBillingAddress()->getData('company') : null;
            }
            if (!$company) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Company name "' . $tmp->Company . '" found in the Lead. Put it to customer data temporary for next mapping process.');
                $customer->setData('company', $tmp->Company);
            }
        }

        /**
         * check converted condition
         */
        if (!$tmp->IsConverted || ($tmp->ConvertedAccountId && $tmp->ConvertedContactId)) {
            return array($this->prepareId($_websiteKey) => array(strtolower($customer->getEmail()) => $tmp));
        }

        return array();
    }

    /**
     * prepare duplicates for merge request and call request at the end
     * @param $duplicateData
     * @param string $leadSource
     * @return $this
     */
    public function mergeDuplicates($duplicateData, $leadSource = '')
    {
        try {
            $collection = Mage::getModel('tnw_salesforce_api_entity/lead')->getCollection();
            $collection->getSelect()->reset(Varien_Db_Select::COLUMNS);

            $collection->getSelect()->columns('Id');
            $collection->getSelect()->columns('Email');

            $collection->getSelect()->where("Email = ?", $duplicateData->getData('Email'));
            $collection->getSelect()->where("IsConverted != true");
            if ($leadSource) {
                $collection->getSelect()->where("LeadSource = ?", $leadSource);
            }

            if (Mage::helper('tnw_salesforce')->getCustomerScope() == "1") {
                $websiteField = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();

                /**
                 * try to find records with the same websiteId or with empty websiteId
                 */
                $_value = $duplicateData->getData($websiteField);
                if (!empty($_value)) {

                    $collection->getSelect()->where(
                        "($websiteField = ?)",
                        $duplicateData->getData($websiteField));

                } else {
                    /**
                     * if websiteId is empty - try to find one more record with websiteId for merging
                     */
                    $itemsCount = $duplicateData->getData('items_count');
                    $collection->getSelect()->limit($itemsCount + 1);
                }

                /**
                 * sorting reason: first record used as master object.
                 * So, records without websiteId will be merged to record with websiteId
                 */
                $order = new Zend_Db_Expr($websiteField . ' DESC NULLS FIRST');
                $collection->getSelect()->order($order);
            }

            $allDuplicates = $collection->getItems();
            $allDuplicatesCount = count($allDuplicates);

            $counter = 0;
            $duplicatesToMergeCount = 0;

            $duplicateToMerge = array();
            foreach ($allDuplicates as $k => $duplicate) {
                $counter++;
                $duplicatesToMergeCount++;
                $duplicateInfo = (object)array('Id' => $duplicate->getData('Id'));

                /**
                 * add next item to the beginning of array, so, record with websiteId will be last
                 */
                $duplicateToMerge[] = $duplicateInfo;

                /**
                 * try to merge piece-by-piece
                 */
                if (
                    $duplicatesToMergeCount == TNW_Salesforce_Helper_Salesforce_Data_User::MERGE_LIMIT
                    || ($allDuplicatesCount == $counter && $duplicatesToMergeCount > 1)
                ) {
                    $masterObject = Mage::helper('tnw_salesforce/salesforce_data_user')->sendMergeRequest($duplicateToMerge, 'Lead');

                    /**
                     * remove technical information
                     */
                    unset($masterObject->success);
                    unset($masterObject->mergedRecordIds);
                    unset($masterObject->updatedRelatedIds);

                    $duplicateToMerge = array();
                    $duplicateToMerge[] = $masterObject;

                    $duplicatesToMergeCount = 1;
                }

            }
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Leads merging error: " . $e->getMessage());
        }

        return $this;
    }

    /**
     * @param string $leadSource
     * @return tnw_salesforce_model_api_entity_resource_lead_collection
     */
    protected function _generateDuplicatesCollection($leadSource = '')
    {
        /** @var tnw_salesforce_model_api_entity_resource_lead_collection $collection */
        $collection = Mage::getModel('tnw_salesforce_api_entity/lead')->getCollection();
        $collection->getSelect()->reset(Varien_Db_Select::COLUMNS);
        $collection->getSelect()->columns('COUNT(Id) items_count');
        $collection->getSelect()->having('COUNT(Id) > ?', 1);
        /**
         * special option, define limitation for queries with sql expression
         */
        $collection->useExpressionLimit(true);
        $collection->getSelect()->where("IsConverted != true");
        if ($leadSource) {
            $collection->getSelect()->where("LeadSource = ?", $leadSource);
        }

        if (Mage::helper('tnw_salesforce')->getCustomerScope() == "1") {
            $websiteField = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix()
                . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();

            $collection->getSelect()
                ->columns($websiteField)
                ->group($websiteField)
                ->orHaving("$websiteField = '' ");
        }

        return $collection;
    }

    /**
     * get duplicates minimal data
     * @param $customers Mage_Customer_Model_Customer[]
     * @param string $leadSource
     * @return TNW_Salesforce_Model_Api_Entity_Lead[]
     */
    public function getDuplicates($customers, $leadSource = '')
    {
        /** @var tnw_salesforce_model_api_entity_resource_lead_collection $collection */
        $collection = $this->_generateDuplicatesCollection($leadSource);
        $collection->getSelect()
            ->columns('Email')
            ->where("Email != ''")
            ->group('Email');

        if (!empty($customers)) {
            $emails = array();
            foreach ($customers as $customer) {
                $emails[] = $customer->getEmail();
            }

            $collection->getSelect()->where('Email IN(?)', $emails);
        }

        return $collection->getItems();
    }

    /**
     * @param $_magentoId
     * @param $customers Mage_Customer_Model_Customer[]
     * @param string $leadSource
     * @param string $idPrefix
     * @return mixed
     * @throws Mage_Core_Exception
     */
    protected function _queryLeads($_magentoId, $customers, $leadSource = '', $idPrefix = '')
    {
        if (empty($customers)) {
            return array();
        }

        $query = "SELECT ID, OwnerId, Company, Email, IsConverted, ConvertedAccountId, ConvertedContactId, " . $_magentoId . ", " . Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject() . " FROM Lead WHERE ";

        $_lookup = array();
        foreach ($customers as $customer) {
            if (!$customer instanceof Mage_Customer_Model_Customer) {
                continue;
            }

            $_id      = $customer->getId();
            $_email   = strtolower($customer->getEmail());
            $_website = $customer->getWebsiteId()
                ? Mage::app()->getWebsite($customer->getWebsiteId())->getData('salesforce_id')
                : null;

            $tmp = "((Email='" . addslashes($_email) . "'";

            if (is_numeric($_id)) {
                $tmp .= " OR " . $_magentoId . "='" . $idPrefix . $_id . "'";
            }
            $tmp .= ")";
            if (
                Mage::helper('tnw_salesforce')->getCustomerScope() == "1"
                && !empty($_website)
            ) {
                $websiteFieldName = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix()
                    . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();

                $tmp .= " AND ($websiteFieldName = '$_website' OR $websiteFieldName = '')";
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

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("QUERY: " . $query);
        try {
            $_result = $this->getClient()->query(($query));
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            $_result = array();
        }

        return $_result;
    }

    /**
     * @comment convertation method for Customer Sync
     */
    public function convertLeadsSimple()
    {
        if (!empty($this->_cache['leadsToConvert'])) {
            $leadsToConvertChunks = array_chunk($this->_cache['leadsToConvert'], TNW_Salesforce_Helper_Data::BASE_CONVERT_LIMIT, true);

            foreach ($leadsToConvertChunks as $leadsToConvertChunk) {

                foreach ($leadsToConvertChunk as $_key => $_object) {
                    foreach ($_object as $key => $value) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveTrace("(" . $_key . ") Lead Conversion: " . $key . " = " . var_export($value, true) . "");
                    }
                }

                $_customerKeys = array_keys($leadsToConvertChunk);

                $_results = $this->getClient()->convertLead(array_values($leadsToConvertChunk));
                foreach ($_results as $_resultsArray) {
                    foreach ($_resultsArray as $_key => $_result) {
                        if (!property_exists($_result, 'success') || !(int)$_result->success) {
                            $this->_processErrors($_result, 'lead');
                        } else {
                            $_customerId = $_customerKeys[$_key];
                            $_customerEmail = $this->_cache['entitiesUpdating'][$_customerId];

                            $_websiteId = $this->_getWebsiteIdByCustomerId($_customerId);

                            if (!isset($this->_cache['toSaveInMagento'][$_websiteId][$_customerEmail])) {
                                $this->_cache['toSaveInMagento'][$_websiteId][$_customerEmail] = new stdClass();
                            }
                            $this->_cache['toSaveInMagento'][$_websiteId][$_customerEmail]->Email = $_customerEmail;
                            $this->_cache['toSaveInMagento'][$_websiteId][$_customerEmail]->ContactId = $_result->contactId;
                            $this->_cache['toSaveInMagento'][$_websiteId][$_customerEmail]->SalesforceId = $_result->contactId;
                            $this->_cache['toSaveInMagento'][$_websiteId][$_customerEmail]->AccountId = $_result->accountId;
                            $this->_cache['toSaveInMagento'][$_websiteId][$_customerEmail]->WebsiteId = $this->getWebsiteSfIds($_websiteId);
                            $this->_cache['toSaveInMagento'][$_websiteId][$_customerEmail]->LeadId = null;

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
        $customer = Mage::helper('tnw_salesforce/salesforce_customer')->getEntityCache($_customerId);
        return Mage::getSingleton('tnw_salesforce/mapping_type_customer')
            ->getWebsiteId($customer);
    }

    /**
     * @param null $lead
     * @param null $leadConvert
     * @return mixed
     */
    public function prepareLeadConversionObjectSimple($lead = NULL, $leadConvert = NULL)
    {
        if (!$leadConvert) {
            $leadConvert = new stdClass();
        }

        $leadConvert->convertedStatus = Mage::helper("tnw_salesforce")->getLeadConvertedStatus();

        $leadConvert->doNotCreateOpportunity = true;
        $leadConvert->overwriteLeadSource = false;
        $leadConvert->sendNotificationEmail = Mage::helper('tnw_salesforce/config_customer')
            ->isLeadEmailNotification();

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

    /**
     * @return null|TNW_Salesforce_Helper_Salesforce_Abstract_Base
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
     * @param null $key
     * @return string
     */
    public function getWebsiteSfIds($key = null)
    {
        return $this->getParent()->getWebsiteSfIds($key);
    }

    /**
     * @param Mage_Customer_Model_Customer $customer
     *
     * @return string
     */
    public function getCompanyByCustomer($customer)
    {
        //company from customer
        $company = TNW_Salesforce_Model_Mapping_Type_Customer::getCompanyByCustomer($customer);

        //set from domains
        if (empty($company)) {
            $lookupByDomain = Mage::helper('tnw_salesforce/salesforce_data_account')
                ->lookupByEmailDomain(array($customer));
            if (!empty($lookupByDomain) && isset($lookupByDomain['_'.$customer->getId()]->Name)) {
                $company = $lookupByDomain['_'.$customer->getId()]->Name;
            }
        }

        //set company from firstname + lastname
        if (empty($company) && !Mage::helper("tnw_salesforce")->createPersonAccount()) {
            $company = TNW_Salesforce_Model_Mapping_Type_Customer::generateCompanyByCustomer($customer);
        }

        return trim($company);
    }
}