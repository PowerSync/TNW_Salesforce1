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
     * @param $_magentoId
     * @param $_extra
     * @param $customers Mage_Customer_Model_Customer[]
     * @return array
     */
    protected function _queryContacts($_magentoId, $_extra, $customers)
    {
        if (empty($customers)) {
            return array();
        }
        $query = "SELECT ID, FirstName, LastName, Email, AccountId, OwnerId, " . $_magentoId . $_extra . " FROM Contact WHERE ";

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

            $tmp = "(((";
            $tmp .= "Email='" . addslashes($_email) . "'";
            if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                $tmp .= " OR Account.PersonEmail='" . addslashes($_email) . "'";
            }
            $tmp .= ")";

            if (is_numeric($_id)) {
                $tmp .= " OR " . $_magentoId . "='" . $_id . "'";

                if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                    $tmp .= " OR Account." . str_replace('__c', '__pc', $_magentoId) . "='" . $_id . "'";
                }
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
        $query .= join(' OR ', $_lookup);

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
     * @param Mage_Customer_Model_Customer[] $customers
     * @return array
     */
    public function getContactsByEmails($customers)
    {
        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
        $_extra = NULL;
        if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
            $_personMagentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__pc";
            $_extra = ", Account.OwnerId, Account.Name, Account.RecordTypeId, Account.IsPersonAccount, Account.PersonContactId, Account.PersonEmail, Account." . $_personMagentoId . ", Account.Id";
        } else {
            $_extra = ", Account.OwnerId, Account.Name";
        }

        if (Mage::helper('tnw_salesforce')->getCustomerScope() == "1") {
            $_extra .= ", " . Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();
        }

        $_results = array();
        foreach (array_chunk($customers, self::UPDATE_LIMIT, true) as $_customers) {
            /** @var stdClass $_result */
            $_result = $this->_queryContacts($_magentoId, $_extra, $_customers);
            if (!is_object($_result) || ($_result->size < 1)) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Contact lookup returned: no results...");

                continue;
            }

            $_results[] = $_result;
        }

        return $_results;
    }

    /**
     * @param Mage_Customer_Model_Customer[] $customers
     * @return array|bool
     */
    public function lookup($customers)
    {
        try {
            if (!is_object($this->getClient())) {
                return array();
            }

            $returnArray = array();
            foreach ($this->customLookup($customers) as $item) {
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

            $email = array_map(function ($customer) {
                return $customer->getEmail();
            }, $customers);

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("Could not find a contact by Magento Email #" . implode(", ", $email));

            return array();
        }
    }

    /**
     * @param $customers Mage_Customer_Model_Customer[]
     * @return array
     * @throws Mage_Core_Exception
     */
    public function customLookup($customers)
    {
        $_magentoId         = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
        $_personMagentoId   = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__pc";
        $websiteFieldKey    = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();

        $_results = $this->getContactsByEmails($customers);
        $records  = $this->mergeRecords($_results);
        if (empty($records)) {
            return array();
        }

        $recordsEmail = $recordsMagentoId = array();
        foreach ($records as $key => $record) {
            // Index Email
            $recordsEmail[$key] = null;
            if (!empty($record->Account->PersonEmail)) {
                $recordsEmail[$key] = $record->Account->PersonEmail;
            }

            if (!empty($record->Email)) {
                $recordsEmail[$key] = $record->Email;
            }

            // Index MagentoId
            $recordsMagentoId[$key] = null;
            if (!empty($record->Account->$_personMagentoId)) {
                $recordsMagentoId[$key] = $record->Account->$_personMagentoId;
            }

            if (!empty($record->Account->$_magentoId)) {
                $recordsMagentoId[$key] = $record->Account->$_magentoId;
            }

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

        return array($this->prepareId($_websiteKey) => array($customer->getEmail() => $tmp));
    }
}