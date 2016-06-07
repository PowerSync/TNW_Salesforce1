<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce_Data_Account extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * Account Name
     *
     * @var string
     */
    protected $_companyName = null;


    /**
     * @param $duplicateData
     * @return $this
     */
    public function mergeDuplicates($duplicateData)
    {
        try {

            $mergedRecords = array();

            $collection = Mage::getModel('tnw_salesforce_api_entity/account')->getCollection();
            $collection->getSelect()->reset(Varien_Db_Select::COLUMNS);

            $collection->getSelect()->columns('Id');

            if (!$duplicateData->getData('IsPersonAccount')) {

                $collection->getSelect()->columns('Name');
                $collection->getSelect()->where("Name = ?", $duplicateData->getData('Name'));
            } else {
                $collection->getSelect()->columns('PersonEmail');
                $collection->getSelect()->where("PersonEmail = ?", $duplicateData->getData('PersonEmail'));
            }

            if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                $collection->getSelect()->columns('IsPersonAccount');
                $collection->getSelect()->where('IsPersonAccount = ' . ($duplicateData->getData('IsPersonAccount')? 'true': 'false'));
                $order = new Zend_Db_Expr('IsPersonAccount ASC NULLS FIRST');
                $collection->getSelect()->order($order);

            }

            $allDuplicates = $collection->getItems();
            $allDuplicatesCount = count($allDuplicates);

            $counter = 0;
            $duplicatesToMergeCount = 0;

            $duplicateToMerge = array();
            $mergePersonAccount = false;
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
                 * merge if:
                 * 1) items-per-merge limit is reached
                 * 2) it's last item in collection
                 * 3) IsPersonAccount changed, send merging request for previous accounts. Merge B2B with B2B and B2C with B2C only
                 */
                if (
                    $duplicatesToMergeCount == TNW_Salesforce_Helper_Salesforce_Data_User::MERGE_LIMIT
                    || ($allDuplicatesCount == $counter && $duplicatesToMergeCount > 1)
                    || ($duplicate->getData('IsPersonAccount') != $mergePersonAccount)
                ) {

                    /**
                     * remove last account with not-matching 'IsPersonAccount' flag, it'll go through separate request
                     */
                    if ($duplicate->getData('IsPersonAccount') != $mergePersonAccount) {
                        $masterObject = array_pop($duplicateToMerge);
                    }

                    if (count($duplicateToMerge) > 1) {

                        $result = Mage::helper('tnw_salesforce/salesforce_data_user')->sendMergeRequest($duplicateToMerge, 'Account');

                        /**
                         * remove technical information
                         */
                        unset($result->success);
                        unset($result->mergedRecordIds);
                        unset($result->updatedRelatedIds);

                        $masterObject = $result;
                    }

                    $duplicateToMerge = array();
                    $duplicateToMerge[] = $masterObject;

                    $duplicatesToMergeCount = 1;

                    $mergePersonAccount = $duplicate->getData('IsPersonAccount');
                }

            }
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Account merging error: " . $e->getMessage());
        }

        return $this;
    }

    /**
     * @return TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection
     */
    public function getDuplicates($_emailsArray = array(), $isPersonAccount = false)
    {
        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";

        $collection = Mage::getModel('tnw_salesforce_api_entity/account')->getCollection();

        $collection->getSelect()->reset(Varien_Db_Select::COLUMNS);
        $collection->getSelect()->columns('COUNT(Id) items_count');

        /**
         * special option, define limitation for queries with sql expression
         */
        $collection->useExpressionLimit(true);

        /**
         * search typical accounts by name
         * search person accounts by email
         */
        if (!$isPersonAccount) {
            $collection->getSelect()->columns('Name');
            $collection->getSelect()->group('Name');

        } else {

            if (!empty($_emailsArray)) {

                $magentoId = str_replace('__c', '__pc', $_magentoId);

                $whereEmail = "PersonEmail = '" . implode("' OR PersonEmail = '", $_emailsArray) . "'";
                $whereCustomerId = "$magentoId = '" . implode("' OR $magentoId = '", array_keys($_emailsArray)) . "'";
                $collection->getSelect()->where("($whereEmail OR  $whereCustomerId)");
            }
            $collection->getSelect()->columns('PersonEmail');
            $collection->getSelect()->group('PersonEmail');
        }

        $collection->getSelect()->having('COUNT(Id) > ?', 1);

        if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
            $collection->getSelect()->columns('IsPersonAccount');
            $collection->getSelect()->where('IsPersonAccount ' . ($isPersonAccount? ' = true': ' != true'));
            $collection->getSelect()->group('IsPersonAccount');
        }

        return $collection;
    }

    /**
     * @param $_customers Mage_Customer_Model_Customer[]
     * @return array
     *
     * @comment returns following structure: 0 => "$emails" => account data
     */
    public function lookup($_customers)
    {

        $_companies = array();
        $_emails = array();
        foreach ($_customers as $_customer) {
            $key            = '_' . $_customer->getId();
            $_emails[$key]  = strtolower($_customer->getEmail());

            $_companyName = $this->getCompanyByCustomer($_customer);
            if (!empty($_companyName)) {
                $_companies[$key] = $_companyName;
            }
        }

        /**
         * @comment find accounts by the Company name, domain and existing contacts
         */
        $_accountsByCompany = (!empty($_companies)) ? $this->lookupByCompanies($_companies, 'CustomIndex') : array();

        $_accountsForce = $this->lookupForce($_emails);

        $_accountsByDomain = $this->lookupByEmailDomain($_emails, 'id');

        $_accountsByContacts = $this->lookupByContact($_customers);

        $_accounts = array_merge($_accountsByCompany, $_accountsForce, $_accountsByDomain, $_accountsByContacts);

        $_accountsLookup = array();

        foreach ($_accounts as $_id => $_account) {
            /**
             * @comment accounts are not splitted by websites, so, we define 0 for cache array compatibility
             */
            $_email = $_emails[$_id];
            $_accountsLookup[0][$_email] = $_account;
        }

        return $_accountsLookup;
    }

    /**
     * @comment find accounts by contact
     * @param Mage_Customer_Model_Customer[] $_customers
     * @param string $field
     * @return array, key - customerId
     */
    public function lookupByContact($_customers, $field = 'id')
    {
        return Mage::helper('tnw_salesforce/salesforce_data_contact')
            ->customLookup($_customers, array($this, 'prepareContactRecord', array('field' => $field)));
    }

    /**
     * @param $customer Mage_Customer_Model_Customer
     * @param $record
     * @param $customData
     * @return array
     */
    public function prepareContactRecord($customer, $record, $customData)
    {
        if (!property_exists($record, 'Account')) {
            return array();
        }

        $tmp = $record->Account;
        $tmp->Id = $record->AccountId;
        $tmp->RecordTypeId = property_exists($record->Account, 'RecordTypeId') ? $record->Account->RecordTypeId : null;
        $tmp->IsPersonAccount = property_exists($record->Account, 'IsPersonAccount') ? $record->Account->IsPersonAccount : false;

        $key = (isset($customData['field']) && $customData['field'] == 'email')
            ? $customer->getEmail() : '_'.$customer->getId();

        return array($key => $tmp);
    }

    /**
     * @comment find and return accounts by some field
     * @param $criterias
     * @param string $hashField
     * @param string $field
     * @return array
     */
    public function lookupByCriterias($criterias, $hashField = 'Id', $field = 'Name')
    {
        $criterias = array_chunk($criterias, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT, true);

        $result = array();

        foreach ($criterias as $criteriasChunk) {
            $lookupResults = $this->lookupByCriteria($criteriasChunk, $hashField, $field);
            if (is_array($lookupResults)) {
                $result = array_merge($result, $lookupResults);
                break;
            }
        }

        return $result;
    }

    /**
     * @comment find account by domain name
     * @param array $emails
     * @return array
     */
    public function lookupByEmailDomain($emails = array(), $_hashKey = 'email')
    {
        $accountIds  = Mage::helper('tnw_salesforce/salesforce_data')->accountLookupByEmailDomain($emails);
        $accountObjs = $this->lookupByCriterias($accountIds, 'CustomIndex', 'Id');

        $return = array();
        foreach ($accountIds as $key => $accountId) {
            foreach ($accountObjs as $accountObj) {
                if ($accountObj->Id == $accountId) {
                    $return[$key] = $accountObj;
                    break;
                }
            }
        }

        return $return;
    }

    /**
     * @comment find account force
     * @param array $emails
     * @return array
     */
    public function lookupForce($emails = array())
    {
        /** @var TNW_Salesforce_Helper_Config_Customer $_hCustomer */
        $_hCustomer = Mage::helper('tnw_salesforce/config_customer');
        if (!$_hCustomer->useAccountSyncCustomer()) {
            return array();
        }

        $forceAccountId = $_hCustomer->getAccountSelect();
        $forceAccount   = $this->lookupByCriteria(array('forceAccount' => $forceAccountId), 'CustomIndex', 'Id');

        $accounts = array();
        foreach (array_keys($emails) as $id) {
            $accounts[$id] = $forceAccount['forceAccount'];
        }

        return $accounts;
    }

    /**
     * @param null $company
     * @return $this
     */
    public function setCompany($company = null)
    {
        $this->_companyName = trim($company);

        return $this;
    }

    /**
     * Find and return accounts by company names
     *
     * @param array $companies
     * @param string $hashField
     *
     * @return array
     */
    public function lookupByCompanies(array $companies, $hashField = 'Id')
    {
        return $this->lookupByCriterias($companies, $hashField);
    }

    /**
     * @param $criteria
     * @param $field
     * @return bool|string
     */
    protected function _prepareCriteriaSql($criteria, $field)
    {
        $additionalSql = '';
        if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
            $additionalSql = ", RecordTypeId, IsPersonAccount";
        }

        $sql = 'SELECT Id, OwnerId, Name' . $additionalSql . ' FROM Account WHERE ';

        $where = array();
        foreach ($criteria as $value) {
            if (!empty($value)) {
                $where[] = sprintf('%s = \'%s\'', $field, addslashes(utf8_encode($value)));
            }
        }

        if (empty($where)) {
            return false;
        }

        $sql .= '(' . implode(' OR ', $where) . ')';

        if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
            $sql .= " AND IsPersonAccount != true";
        }
        
        return $sql;
    }
    
    /**
     * Use the CustomIndex value in $hashField parameter if returned array should use the keys of the $_companies array
     * @param array $criteria
     * @param string $hashField
     * @param string $field
     * @return array|bool
     */
    public function lookupByCriteria($criteria = array(), $hashField = 'Id', $field = 'Name')
    {
        try {
            if (empty($criteria)) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Account search criteria is not provided, SKIPPING lookup!");

                return false;
            }

            $sql = $this->_prepareCriteriaSql($criteria, $field);
            $result = Mage::getSingleton('tnw_salesforce/api_client')->query($sql);

            if (empty($result)) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Account lookup by " . var_export($criteria, true) . " returned: 0 results...");
                return false;
            }

            $returnArray = array();
            foreach ($result as $_item) {
                $_returnObject = new stdClass();
                $_returnObject->Id = (isset($_item['Id'])) ? $_item['Id'] : NULL;
                $_returnObject->OwnerId = (isset($_item['OwnerId'])) ? $_item['OwnerId'] : NULL;
                $_returnObject->Name = (isset($_item['Name'])) ? $_item['Name'] : NULL;
                $_returnObject->RecordTypeId = (isset($_item['RecordTypeId'])) ? $_item['RecordTypeId'] : NULL;
                $_returnObject->IsPersonAccount = (isset($_item['IsPersonAccount'])) ? $_item['IsPersonAccount'] : false;

                foreach ($criteria as $_customIndex => $_value) {
                    // Need case insensitive match
                    if (strtolower($_item[$field]) == strtolower($_value)) {
                        $_returnObject->$field = $_value;
                        $_returnObject->CustomIndex = $_customIndex;
                        break;
                    }
                }

                if (isset($_returnObject->$hashField) && $_returnObject->$hashField) {
                    $_hashKey = $_returnObject->$hashField;
                } elseif (isset($_item->$hashField) && $_item->$hashField) {
                    $_hashKey = $_item->$hashField;
                } else {
                    $_hashKey = $_returnObject->Id;
                }

                $returnArray[$_hashKey] = $_returnObject;

                unset($_returnObject);
            }

        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not find an account by criteria: " . var_export($criteria));

            return false;
        }
        return $returnArray;
    }

    /**
     * @comment Use the "CustomIndex" value in $_hashField parameter if returned array should use the keys of the $_companies array
     * @return bool|null
     */
    public function lookupByCompany(array $companies = array(), $hashField = 'Id')
    {
        try {
            if (!$this->_companyName && empty($companies)) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Company field is not provided, SKIPPING lookup!");

                return false;
            }

            if (!$companies) {
                $companies = array($this->_companyName);
            }

            if (empty($companies)) {
                return false;
            }

            $returnArray = $this->lookupByCriteria($companies, $hashField);

            return $returnArray;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not find a contact by Company: " . $this->_companyName);
        }

        return false;
    }

    public function lookupContactIds($where)
    {
        $sql = "SELECT Id, PersonContactId FROM Account WHERE Id IN ('" . implode("','", array_values($where)) . "')";
        $result = Mage::getSingleton('tnw_salesforce/api_client')->query($sql);

        if (empty($result)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("PersonAccount lookup did not return any results...");
            return false;
        }

        return $result;
    }
}