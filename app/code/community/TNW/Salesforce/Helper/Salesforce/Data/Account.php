<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce_Data_Account extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * @param $duplicateData
     * @return $this
     */
    public function mergeDuplicates($duplicateData)
    {
        try {
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
     * @param bool $isPersonAccount
     * @return tnw_salesforce_model_api_entity_resource_account_collection
     */
    protected function _generateDuplicatesCollection($isPersonAccount = false)
    {
        /** @var tnw_salesforce_model_api_entity_resource_account_collection $collection */
        $collection = Mage::getModel('tnw_salesforce_api_entity/account')->getCollection();
        $collection->getSelect()->reset(Varien_Db_Select::COLUMNS)
            ->columns('COUNT(Id) items_count')
            ->having('COUNT(Id) > ?', 1);

        /**
         * special option, define limitation for queries with sql expression
         */
        $collection->useExpressionLimit(true);

        if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
            $collection->getSelect()->columns('IsPersonAccount');
            $collection->getSelect()->where('IsPersonAccount ' . ($isPersonAccount? ' = true': ' != true'));
            $collection->getSelect()->group('IsPersonAccount');
        }

        return $collection;
    }

    /**
     * @param $customers Mage_Customer_Model_Customer[]
     * @param bool $isPersonAccount
     * @return TNW_Salesforce_Model_Api_Entity_Account[]
     */
    public function getDuplicates($customers, $isPersonAccount = false)
    {
        $collection = $this->_generateDuplicatesCollection($isPersonAccount);

        /**
         * search typical accounts by name
         * search person accounts by email
         */
        if (!$isPersonAccount) {
            $collection->getSelect()->columns('Name');
            $collection->getSelect()->group('Name');
        }
        else {
            $collection->getSelect()->columns('PersonEmail');
            $collection->getSelect()->group('PersonEmail');

            if (!empty($customers)) {
                $emails = array();
                foreach ($customers as $customer) {
                    $emails[]   = $customer->getEmail();
                }

                $collection->getSelect()
                    ->where('PersonEmail IN(?)', $emails);
            }
        }

        return $collection->getItems();
    }

    /**
     * @param $_customers Mage_Customer_Model_Customer[]
     * @return array
     * @throws Exception
     *
     * @comment returns following structure: 0 => "$emails" => account data
     */
    public function lookup($_customers)
    {
        /**
         * @comment find accounts by the Company name, domain and existing contacts
         */
        $_accountsByCompany  = $this->lookupByCompanies($_customers);
        $_accountsForce      = $this->lookupForce($_customers);
        $_accountsByDomain   = $this->lookupByEmailDomain($_customers);
        $_accountsByContacts = $this->lookupByContact($_customers);

        $_accounts = array_merge($_accountsByCompany, $_accountsForce, $_accountsByDomain, $_accountsByContacts);

        $_accountsLookup = array();
        foreach ($_customers as $_customer) {
            if (empty($_accounts['_'.$_customer->getId()])) {
                continue;
            }

            /**
             * @comment accounts are not splitted by websites, so, we define 0 for cache array compatibility
             */
            $_accountsLookup[0][strtolower($_customer->getEmail())]
                = $_accounts['_'.$_customer->getId()];
        }

        return $_accountsLookup;
    }

    /**
     * @comment find accounts by contact
     * @param Mage_Customer_Model_Customer[] $_customers
     * @param string $field
     * @return array, key - customerId
     * @throws Exception
     */
    public function lookupByContact($_customers, $field = 'id')
    {
        $customLookup = Mage::helper('tnw_salesforce/salesforce_data_contact')
            ->customLookup($_customers);

        $returnArray = array();
        foreach ($customLookup as $item) {
            $returnArray = array_merge($returnArray,
                $this->prepareContactRecord($item['entity'], $item['record'], $field));
        }

        return $returnArray;
    }

    /**
     * @param $customer Mage_Customer_Model_Customer
     * @param $record
     * @param $field
     * @return array
     */
    public function prepareContactRecord($customer, $record, $field = 'id')
    {
        if (!property_exists($record, 'Account')) {
            return array();
        }

        $tmp = $record->Account;
        $tmp->Id = $record->AccountId;
        $tmp->RecordTypeId = property_exists($record->Account, 'RecordTypeId') ? $record->Account->RecordTypeId : null;
        $tmp->IsPersonAccount = property_exists($record->Account, 'IsPersonAccount') ? $record->Account->IsPersonAccount : false;

        $key = ($field == 'email')
            ? $customer->getEmail() : '_'.$customer->getId();

        return array($key => $tmp);
    }

    /**
     * @comment find and return accounts by some field
     * @param $criterias
     * @param string $hashField
     * @param string $field
     * @return array
     * @throws Exception
     */
    public function lookupByCriterias($criterias, $hashField = 'Id', $field = 'Name')
    {
        $result = array();
        foreach (array_chunk($criterias, self::UPDATE_LIMIT, true) as $criteriasChunk) {
            $result = array_merge($result, $this->lookupByCriteria($criteriasChunk, $hashField, $field));
        }

        return $result;
    }

    /**
     * @comment find account by domain name
     * @param Mage_Customer_Model_Customer[] $_customers
     * @return array
     * @throws Exception
     */
    public function lookupByEmailDomain($_customers)
    {
        $_emails = array();
        foreach ($_customers as $_customer) {
            $_emails['_' . $_customer->getId()] = strtolower($_customer->getEmail());
        }

        $accountIds  = Mage::helper('tnw_salesforce/salesforce_data')->accountLookupByEmailDomain($_emails);
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
     * @param Mage_Customer_Model_Customer[] $_customers
     * @return array
     * @throws Exception
     */
    public function lookupForce($_customers)
    {
        /** @var TNW_Salesforce_Helper_Config_Customer $_hCustomer */
        $_hCustomer = Mage::helper('tnw_salesforce/config_customer');
        if (!$_hCustomer->useAccountSyncCustomer()) {
            return array();
        }

        $forceAccountId = $_hCustomer->getAccountSelect();
        $forceAccount   = $this->lookupByCriteria(array('forceAccount' => $forceAccountId), 'CustomIndex', 'Id');

        $accounts = array();
        foreach ($_customers as $_customer) {
            $accounts['_'.$_customer->getId()] = $forceAccount['forceAccount'];
        }

        return $accounts;
    }

    /**
     * Find and return accounts by company names
     *
     * @param Mage_Customer_Model_Customer[] $_customers
     *
     * @return array
     * @throws Exception
     */
    public function lookupByCompanies($_customers)
    {
        $_companies = array();
        foreach ($_customers as $_customer) {
            $_companyName = $this->getCompanyByCustomer($_customer);
            if (!empty($_companyName)) {
                $_companies['_' . $_customer->getId()] = $_companyName;
            }
        }

        if (empty($_companies)) {
            return array();
        }

        return $this->lookupByCriterias($_companies, 'CustomIndex');
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
     * @return array
     * @throws Exception
     */
    public function lookupByCriteria($criteria = array(), $hashField = 'Id', $field = 'Name')
    {
        if (empty($criteria)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Account search criteria is not provided, SKIPPING lookup!');

            return array();
        }

        $sql = $this->_prepareCriteriaSql($criteria, $field);
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Account QUERY:\n{$sql}");

        $result = Mage::getSingleton('tnw_salesforce/api_client')->query($sql);
        if (empty($result)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Account lookup returned: 0 results...');

            return array();
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
            } elseif (!empty($_item[$hashField])) {
                $_hashKey = $_item[$hashField];
            } else {
                $_hashKey = $_returnObject->Id;
            }

            $returnArray[$_hashKey] = $_returnObject;
        }

        return $returnArray;
    }

    /**
     * @param $where
     * @return bool
     * @throws Exception
     */
    public function lookupContactIds($where)
    {
        $sql = "SELECT Id, PersonContactId FROM Account WHERE Id IN ('" . implode("','", array_values($where)) . "')";
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Account QUERY:\n{$sql}");

        $result = Mage::getSingleton('tnw_salesforce/api_client')->query($sql);
        if (empty($result)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("PersonAccount lookup did not return any results...");
            return false;
        }

        return $result;
    }
}