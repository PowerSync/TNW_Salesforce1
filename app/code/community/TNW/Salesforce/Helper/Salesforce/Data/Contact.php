<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce_Data_Contact extends TNW_Salesforce_Helper_Salesforce_Data
{
    public function __construct()
    {
        parent::__construct();
    }

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
     * @param array $_emailsArray
     * @return TNW_Salesforce_Model_Api_Entity_Resource_Contact_Collection
     */
    public function getDuplicates($_emailsArray = array())
    {
        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";

        $collection = Mage::getModel('tnw_salesforce_api_entity/contact')->getCollection();

        $collection->getSelect()->reset(Varien_Db_Select::COLUMNS);
        $collection->getSelect()->columns('Email');
        $collection->getSelect()->columns('COUNT(Id) items_count');

        /**
         * special option, define limitation for queries with sql expression
         */
        $collection->useExpressionLimit(true);

        $collection->getSelect()->where("Email != ''");

        $collection->getSelect()->group('Email');

        $collection->getSelect()->having('COUNT(Id) > ?', 1);

        if (!empty($_emailsArray)) {

            $whereEmail = "Email = '" . implode("' OR Email = '", $_emailsArray) . "'";
            $whereCustomerId = "$_magentoId = '" . implode("' OR $_magentoId = '", array_keys($_emailsArray)) . "'";
            $collection->getSelect()->where("($whereEmail OR  $whereCustomerId)");
        }

        if (Mage::helper('tnw_salesforce')->getCustomerScope() == "1") {
            $websiteField = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();

            $collection->getSelect()->columns($websiteField);
            $collection->getSelect()->group($websiteField);
            /**
             * records with empty websiteId - are duplicates potentially
             */
            $collection->getSelect()->orHaving("$websiteField = '' ");

            $order = new Zend_Db_Expr($websiteField . ' ASC NULLS LAST');
            $collection->getSelect()->order($order);
        }

        if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
            $collection->getSelect()->where('Account.IsPersonAccount != true');
        }

        return $collection;
    }

    /**
     * @param $_magentoId
     * @param $_extra
     * @param $_emails
     * @param $_websites
     * @return array
     */
    protected function _queryContacts($_magentoId, $_extra, $_emails, $_websites)
    {
        if (empty($_emails)) {
            return false;
        }
        $query = "SELECT ID, FirstName, LastName, Email, AccountId, OwnerId, " . $_magentoId . $_extra . " FROM Contact WHERE ";

        $_lookup = array();
        foreach ($_emails as $_id => $_email) {
            if (empty($_email)) {
                continue;
            }
            $tmp = "(((";
            $tmp .= "Email='" . addslashes($_email) . "'";
            if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                $tmp .= " OR Account.PersonEmail='" . addslashes($_email) . "'";
            }
            $tmp .= ")";

            if (
                !empty($_id)
                && $_id != 0
            ) {
                $tmp .= " OR " . $_magentoId . "='" . $_id . "'";
            }

            if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                $tmp .= " OR Account." . str_replace('__c', '__pc', $_magentoId) . "='" . $_id . "'";
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
     * @param null $email
     * @param array $_websites
     * @return array
     */
    public function getContactsByEmails($email = NULL, $_websites = array())
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
        $_emailChunk = array_chunk($email, self::UPDATE_LIMIT);
        foreach ($_emailChunk as $_emails) {
            $_results[] = $this->_queryContacts($_magentoId, $_extra, $_emails, $_websites);
        }

        return $_results;
    }

    /**
     * @param null $email
     * @param array $_websites
     * @return array|bool
     */
    public function lookup($email = NULL, $_websites = array())
    {
        try {
            if (!is_object($this->getClient())) {
                return false;
            }
            $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";

            if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                $_personMagentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__pc";
            }

            $_results = $this->getContactsByEmails($email, $_websites);

            unset($query);
            if (empty($_results) || !$_results[0] || $_results[0]->size < 1) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Contact lookup returned: no results...");
                return false;
            }

            $returnArray = array();
            foreach ($_results as $result) {
                foreach ($result->records as $_item) {
                    $tmp = new stdClass();
                    $tmp->Id = $_item->Id;
                    $tmp->Email = (property_exists($_item, 'Email') && $_item->Email) ? strtolower($_item->Email) : NULL;
                    if ($tmp->Email === NULL) {
                        $tmp->Email = (
                            property_exists($_item, 'Account')
                            && is_object($_item->Account)
                            && property_exists($_item->Account, 'PersonEmail')
                        ) ? strtolower($_item->Account->PersonEmail) : NULL;
                    }

                    $tmp->OwnerId = (property_exists($_item, 'OwnerId')) ? $_item->OwnerId : NULL;
                    $tmp->FirstName = (property_exists($_item, 'FirstName')) ? $_item->FirstName : NULL;
                    $tmp->LastName = (property_exists($_item, 'LastName')) ? $_item->LastName : NULL;
                    $tmp->AccountId = (property_exists($_item, 'AccountId')) ? $_item->AccountId : NULL;
                    $tmp->AccountName = (property_exists($_item, 'Account') && property_exists($_item->Account, 'Name') && $_item->Account->Name) ? $_item->Account->Name : NULL;
                    $tmp->RecordTypeId = (property_exists($_item, 'Account') && property_exists($_item->Account, 'RecordTypeId') && $_item->Account->RecordTypeId) ? $_item->Account->RecordTypeId : NULL;
                    $tmp->AccountOwnerId = (property_exists($_item, 'Account') && property_exists($_item->Account, 'OwnerId') && $_item->Account->OwnerId) ? $_item->Account->OwnerId : NULL;
                    if (
                        Mage::helper('tnw_salesforce')->usePersonAccount()
                        && property_exists($_item, 'Account')
                        && property_exists($_item->Account, 'IsPersonAccount')
                        && $_item->Account->IsPersonAccount
                    ) {
                        $tmp->IsPersonAccount = $_item->Account->IsPersonAccount;
                        $tmp->PersonEmail = (property_exists($_item, 'PersonEmail') && $_item->PersonEmail) ? strtolower($_item->PersonEmail) : $tmp->Email;
                    }

                    $tmp->MagentoId = (property_exists($_item, $_magentoId)) ? $_item->{$_magentoId} : NULL;
                    if (!$tmp->MagentoId && property_exists($_item, 'Account') && property_exists($_item->Account, $_magentoId)) {
                        $tmp->MagentoId = $_item->Account->{$_magentoId};
                    }
                    if (
                        !$tmp->MagentoId
                        && Mage::helper('tnw_salesforce')->usePersonAccount()
                        && property_exists($_item, $_personMagentoId)
                    ) {
                        $tmp->MagentoId = $_item->Account->{$_personMagentoId};
                    }

                    if (property_exists($_item, 'Email') && $tmp->Email) {
                        $_key = $tmp->Email;
                    } elseif (property_exists($_item, 'PersonEmail') && $_item->PersonEmail) {
                        $_key = $tmp->PersonEmail;
                    } elseif (property_exists($_item, 'MagentoId') && $_item->MagentoId) {
                        $_key = $_item->MagentoId;
                    }
                    if (property_exists($_item, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject())) {
                        $_websiteKey = $_item->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()};
                    } else {
                        $_websiteKey = 0;
                        if ($tmp->MagentoId && array_key_exists($tmp->MagentoId, $_websites)) {
                            $_websiteKey = $_websites[$tmp->MagentoId];
                        }
                        if (!$_websiteKey) {
                            // Guest, grab the first record (create other records if Magento customer scope is not global)
                            $_personEmail = (property_exists($_item, 'PersonEmail') && $_item->PersonEmail) ? $tmp->PersonEmail : $tmp->Email;
                            $_customerId = array_search($_personEmail, $email);
                            if ($_customerId !== FALSE) {
                                $_websiteKey = $_websites[$_customerId];
                            }
                        }
                    }

                    $_websiteKey = $this->prepareId($_websiteKey);

                    /**
                     * get item if no other results or if MagentoId is same: matching by MagentoId should has the highest priority
                     */
                    if (
                        !isset($returnArray[$_websiteKey][$_key])
                        || ($tmp->MagentoId && !empty($email[$tmp->MagentoId]))
                    ) {

                        /**
                         * if record was found by MagentoId and has different email - use email from Magento system
                         */
                        if (!empty($email[$tmp->MagentoId])) {
                            $_key = $email[$tmp->MagentoId];
                        }

                        $returnArray[$_websiteKey][$_key] = $tmp;
                    }
                }
            }
            return $returnArray;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not find a contact by Magento Email #" . implode(",", $email));
            unset($email);
            return false;
        }
    }
}