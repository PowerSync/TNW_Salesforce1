<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce_Data_Contact extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * @param $duplicateData
     * @return $this
     */
    public function mergeDuplicates($duplicateData)
    {
        try {
            $collection = Mage::getModel('tnw_salesforce_api_entity/contact')->getCollection();
            $collection->getSelect()->reset(Varien_Db_Select::COLUMNS);

            $collection->getSelect()->columns('Id');
            $collection->getSelect()->columns('Email');

            $collection->getSelect()->where("Email = ?", $duplicateData->getData('Email'));

            if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                $collection->getSelect()->where('Account.IsPersonAccount != true');
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
                    $masterObject = Mage::helper('tnw_salesforce/salesforce_data_user')->sendMergeRequest($duplicateToMerge, 'Contact');

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
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Contact merging error: " . $e->getMessage());
        }

        return $this;
    }

    /**
     * @return TNW_Salesforce_Model_Api_Entity_Resource_Contact_Collection
     */
    protected function _generateDuplicatesCollection()
    {
        /** @var tnw_salesforce_model_api_entity_resource_contact_collection $collection */
        $collection = Mage::getModel('tnw_salesforce_api_entity/contact')->getCollection();
        $collection->getSelect()->reset(Varien_Db_Select::COLUMNS)
            ->columns('COUNT(Id) items_count')
            ->having('COUNT(Id) > ?', 1);

        /**
         * special option, define limitation for queries with sql expression
         */
        $collection->useExpressionLimit(true);

        if (Mage::helper('tnw_salesforce')->getCustomerScope() == "1") {
            $websiteField = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix()
                . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();

            $order = new Zend_Db_Expr($websiteField . ' ASC NULLS LAST');

            $collection->getSelect()
                ->columns($websiteField)
                ->group($websiteField)
                ->orHaving("$websiteField = '' ")
                ->order($order);
        }

        if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
            $collection->getSelect()->where('Account.IsPersonAccount != true');
        }

        return $collection;
    }

    /**
     * @param $customers Mage_Customer_Model_Customer[]
     * @return TNW_Salesforce_Model_Api_Entity_Contact[]
     */
    public function getDuplicates($customers = array())
    {
        $collection = $this->_generateDuplicatesCollection();
        $collection->getSelect()
            ->columns('Email')
            ->where("Email != ''")
            ->group('Email');

        if (!empty($customers)) {
            $emails = $iDs = array();
            foreach ($customers as $customer) {
                $iDs[]      = $customer->getId();
                $emails[]   = $customer->getEmail();
            }

            $collection->getSelect()
                ->where('Email IN(?)', $emails);
        }

        return $collection->getItems();
    }

    /**
     * @param $customers Mage_Customer_Model_Customer[]
     * @return stdClass
     * @throws Exception
     */
    protected function _queryContacts(array $customers)
    {
        $columns = $this->columnsLookupQuery();
        $conditions = $this->conditionsLookupQuery($customers);

        $query = sprintf('SELECT %s FROM Contact WHERE %s',
            $this->generateLookupSelect($columns),
            $this->generateLookupWhere($conditions));

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace("Contact QUERY:\n{$query}");

        return $this->getClient()->query($query);
    }

    /**
     * @return array
     */
    protected function columnsLookupQuery()
    {
        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';
        $columns = array(
            'ID', 'FirstName', 'LastName', 'Email', 'AccountId', $_magentoId,
            'OwnerId', 'Account.Id', 'Account.OwnerId', 'Account.Name'
        );

        if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
            $_personMagentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__pc';
            $columns = array_merge($columns, array(
                'Account.RecordTypeId', 'Account.IsPersonAccount', 'Account.PersonContactId',
                'Account.PersonEmail', "Account.{$_personMagentoId}"
            ));
        }

        if (Mage::helper('tnw_salesforce')->getCustomerScope() == "1") {
            $columns[] = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix()
                . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();
        }

        return $columns;
    }

    /**
     * @param Mage_Customer_Model_Customer[] $customers
     * @return mixed
     */
    protected function conditionsLookupQuery(array $customers)
    {
        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';
        $websiteFieldName = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix()
            . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();

        $conditions['OR'] = array();
        foreach ($customers as $customer) {
            if (!$customer instanceof Mage_Customer_Model_Customer) {
                continue;
            }

            $_id      = $customer->getId();
            $_email   = strtolower($customer->getEmail());
            $_website = $customer->getWebsiteId()
                ? Mage::app()->getWebsite($customer->getWebsiteId())->getData('salesforce_id')
                : null;

            $orCond['AND']['eaw']['AND']['email']['OR']['Email']['='] = $_email;
            if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                $orCond['AND']['eaw']['AND']['email']['OR']['Account.PersonEmail']['='] = $_email;
            }

            if (!empty($_website) && Mage::helper('tnw_salesforce')->getCustomerScope() == "1") {
                $orCond['AND']['eaw']['AND'][$websiteFieldName]['IN'] = array($_website, '');
            }

            if (is_numeric($_id)) {
                $orCond['OR']['magentoId']['OR'][$_magentoId]['='] = $_id;

                if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                    $orCond['OR']['magentoId']['OR']['Account.' . str_replace('__c', '__pc', $_magentoId)]['='] = $_id;
                }
            }

            $conditions['OR'][$customer->getData('email')] = $orCond;
        }

        return $conditions;
    }

    /**
     * @param Mage_Customer_Model_Customer[] $customers
     * @return array
     * @throws Exception
     */
    public function getContactsByEmails(array $customers)
    {
        $_results = array();
        foreach (array_chunk($customers, self::UPDATE_LIMIT, true) as $_customers) {
            $_results[] = $this->_queryContacts($_customers);
        }

        return $this->mergeRecords($_results);
    }

    /**
     * @param Mage_Customer_Model_Customer[] $customers
     * @return array
     * @throws Exception
     */
    public function lookup(array $customers)
    {
        $_magentoId         = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';
        $_personMagentoId   = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__pc';

        $records = $this->getContactsByEmails($customers);
        if (empty($records)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Contact lookup returned: no results...');

            return array();
        }

        $returnArray = $clearContactToUpsert = $clearAccountToUpsert = array();
        foreach ($this->assignLookupToEntity($records, $customers) as $item) {
            if (empty($item['record'])) {
                continue;
            }

            $records = $item['records'];
            $searchRecordIds = array_keys($records, $item['record'], true);
            unset($records[reset($searchRecordIds)]);
            foreach ($records as $record) {
                if (!empty($record->$_magentoId) && $record->$_magentoId == $item['entity']->getId()) {
                    $upsert = new stdClass();
                    $upsert->Id = $record->Id;
                    $upsert->$_magentoId = ' ';

                    $clearContactToUpsert[] = $upsert;
                }

                if (!empty($record->Account->$_personMagentoId) && $record->Account->$_personMagentoId == $item['entity']->getId()) {
                    $upsert = new stdClass();
                    $upsert->Id = $record->Account->Id;
                    $upsert->$_personMagentoId = ' ';

                    $clearAccountToUpsert[] = $upsert;
                }
            }

            $return = $this->prepareRecord($item['entity'], $item['record']);
            if (empty($return)) {
                continue;
            }

            list($website, $entityData) = each($return);
            if (!isset($returnArray[$website])) {
                $returnArray[$website] = array();
            }

            $returnArray[$website] = array_merge($returnArray[$website], $entityData);
        }

        if (!empty($clearContactToUpsert)) {
            // Clear Magento Id
            $this->getClient()->upsert('Id', $clearContactToUpsert, 'Contact');
        }

        if (!empty($clearAccountToUpsert)) {
            // Clear Magento Id
            $this->getClient()->upsert('Id', $clearAccountToUpsert, 'Account');
        }

        return $returnArray;
    }

    /**
     * @param array $records
     * @return array
     */
    protected function collectLookupIndex(array $records)
    {
        $searchIndex = array();
        foreach ($records as $key => $record) {
            // Index Email
            $searchIndex['email'][$key] = null;
            if (!empty($record->Account->PersonEmail)) {
                $searchIndex['email'][$key] = strtolower($record->Account->PersonEmail);
            }

            if (!empty($record->Email)) {
                $searchIndex['email'][$key] = strtolower($record->Email);
            }
        }

        return $searchIndex;
    }

    /**
     * @param array $searchIndex
     * @param Mage_Customer_Model_Customer $entity
     * @return array[]
     */
    protected function searchLookupPriorityOrder(array $searchIndex, $entity)
    {
        $recordsIds = array();

        // Priority 1
        $recordsIds[10] = array_keys($searchIndex['email'], strtolower($entity->getData('email')));

        return $recordsIds;
    }

    /**
     * @param array[] $recordsPriority
     * @param Mage_Customer_Model_Customer $entity
     * @return stdClass|null
     */
    protected function filterLookupByPriority(array $recordsPriority, $entity)
    {
        $websiteFieldKey = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix()
            . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();

        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';
        $_personMagentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__pc';

        $findRecord = null;
        foreach ($recordsPriority as $records) {
            foreach ($records as $record) {
                if (!empty($record->Account->$_personMagentoId) && $record->Account->$_personMagentoId == $entity->getId()) {
                    $findRecord = $record;
                    break 2;
                } elseif (!empty($record->$_magentoId) && $record->$_magentoId == $entity->getId()) {
                    $findRecord = $record;
                    break 2;
                }

                if (empty($record->$websiteFieldKey)) {
                    $findRecord = $record;
                    continue;
                }

                if ($record->$websiteFieldKey == Mage::app()->getWebsite($entity->getData('website_id'))->getData('salesforce_id')) {
                    $findRecord = $record;
                    break 2;
                }
            }

            if (!empty($findRecord)) {
                break;
            }
        }

        return $findRecord;
    }

    /**
     * @param Mage_Customer_Model_Customer[] $customers
     * @return array
     * @throws Exception
     * @deprecated
     */
    public function customLookup(array $customers)
    {
        $records = $this->getContactsByEmails($customers);
        if (empty($records)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Contact lookup returned: no results...');

            return array();
        }

        return $this->assignLookupToEntity($records, $customers);
    }

    /**
     * @param $customer Mage_Customer_Model_Customer
     * @param $record stdClass
     * @return array
     */
    public function prepareRecord($customer, $record)
    {
        $_magentoId         = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
        $_personMagentoId   = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__pc";

        $_websiteKey = Mage::app()
            ->getWebsite($customer->getWebsiteId())
            ->getData('salesforce_id');

        $tmp = new stdClass();
        $tmp->Id    = $record->Id;
        $tmp->Email = !empty($record->Email) ? strtolower($record->Email) : null;
        if (null === $tmp->Email) {
            $tmp->Email = !empty($record->Account->PersonEmail) ? strtolower($record->Account->PersonEmail) : null;
        }

        $tmp->OwnerId        = !empty($record->OwnerId) ? $record->OwnerId : null;
        $tmp->FirstName      = !empty($record->FirstName) ? $record->FirstName : null;
        $tmp->LastName       = !empty($record->LastName) ? $record->LastName : null;
        $tmp->AccountId      = !empty($record->AccountId) ? $record->AccountId : null;
        $tmp->AccountName    = !empty($record->Account->Name) ? $record->Account->Name : null;
        $tmp->RecordTypeId   = !empty($record->Account->RecordTypeId) ? $record->Account->RecordTypeId : null;
        $tmp->AccountOwnerId = !empty($record->Account->OwnerId) ? $record->Account->OwnerId : null;

        if (
            Mage::helper('tnw_salesforce')->usePersonAccount()
            && !empty($record->Account->IsPersonAccount)
        ) {
            $tmp->IsPersonAccount = $record->Account->IsPersonAccount;
            $tmp->PersonEmail = !empty($record->PersonEmail) ? strtolower($record->PersonEmail) : $tmp->Email;
        }

        $tmp->MagentoId = (property_exists($record, $_magentoId)) ? $record->{$_magentoId} : NULL;
        if (!$tmp->MagentoId && isset($record->Account->{$_magentoId})) {
            $tmp->MagentoId = $record->Account->{$_magentoId};
        }
        if (
            !$tmp->MagentoId
            && Mage::helper('tnw_salesforce')->usePersonAccount()
            && isset($record->Account->{$_personMagentoId})
        ) {
            $tmp->MagentoId = $record->Account->{$_personMagentoId};
        }

        return array($this->prepareId($_websiteKey) => array(strtolower($customer->getEmail()) => $tmp));
    }
}
