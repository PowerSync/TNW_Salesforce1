<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce_Data extends TNW_Salesforce_Helper_Salesforce
{
    /**
     * Chunk size
     */
    const UPDATE_LIMIT = 50;

    const PROFESSIONAL_SALESFORCE_RECORD_TYPE_LABEL = 'NOT IN USE';

    /**
     * @var array
     */
    protected $_tableDescription = array();

    /**
     * @var array
     */
    protected $_noConnectionArray = array();

    /**
     * @return null
     */
    public function getWriter()
    {
        if (!$this->_write) {
            $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        }

        return $this->_write;
    }

    /**
     * @return bool|null
     */
    public function connect()
    {
        return TNW_Salesforce_Model_Connection::createConnection()->initConnection();
    }

    /**
     * @param array $emails
     * @return array
     */
    public function accountLookupByEmailDomain($emails = array())
    {
        $_domains = array_map(function($_email) {
            return strtolower(substr(stristr($_email, '@'), 1));
        }, $emails);

        try {
            /** @var TNW_Salesforce_Model_Mysql4_Account_Matching_Collection $collection */
            $collection = Mage::getModel('tnw_salesforce/account_matching')
                ->getCollection();
            $collection->addFieldToFilter('email_domain', $_domains);

            $toOptionHash = $collection->toOptionHashCustom();
        }
        catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not find a contact by email domain #" . join(',', $_domains));

            return array();
        }

        $_return = array();
        foreach($_domains as $_hashKey => $_domain) {
            if (!isset($toOptionHash[$_domain])) {
                continue;
            }

            $_return[$_hashKey] = $toOptionHash[$_domain];
        }

        return $_return;
    }

    /**
     * @return array|bool
     */
    public function getAccountPersonRecordType()
    {
        try {

            try {
                $this->getClient();
            } catch (Exception $e) {
                return $this->_noConnectionArray;
            }

            if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                $query = "SELECT Id, Name, SobjectType, IsPersonType FROM RecordType WHERE SobjectType='Account' AND IsPersonType=True";
                $allRules = $this->getClient()->query(($query));
            } else {
                $allRules = new stdClass();
                $allRules->done = true;
            }
            if ($allRules && property_exists($allRules, 'done') && $allRules->done) {
                if (!property_exists($allRules, 'records') || $allRules->size < 1) {
                    $_default = new stdClass();
                    $_default->Id = '';
                    $_default->Name = '-- Not Applicable --';

                    $allRules->records = array($_default);
                }
            }
            unset($sfObject, $query);

            return $allRules->records;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not get a Person Record Type of the Account");
            unset($e);

            return false;
        }
    }

    /**
     * @return array|bool
     */
    public function getAccountBusinessRecordType()
    {
        try {
            try {
                $this->getClient();
            } catch (Exception $e) {
                return $this->_noConnectionArray;
            }

            if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                $query = "SELECT Id, Name, IsPersonType FROM RecordType WHERE SobjectType='Account' AND IsPersonType=False";
                $allRules = $this->getClient()->query(($query));
            } else {
                $query = "SELECT Id, Name FROM RecordType WHERE SobjectType='Account'";
                $allRules = $this->getClient()->query(($query));
            }

            $_default = new stdClass();
            $_default->Id = '';
            $_default->Name = 'Use Default';

            array_unshift($allRules->records, $_default);
            return $allRules->records;
        } catch (Exception $e) {
            $allRules = new stdClass();
            // Captures a usecase for Professional version of Salesforce
            $_default = new stdClass();
            $_default->Id = '';
            $_default->Name = self::PROFESSIONAL_SALESFORCE_RECORD_TYPE_LABEL;
            $allRules->records = array($_default);

            unset($e);

            return $allRules->records;
        }
    }

    /**
     * @param $entity
     * @return array
     * @throws Zend_Cache_Exception
     */
    public function getRecordTypeByEntity($entity)
    {
        $_data = $this->getStorage('tnw_salesforce_' . strtolower($entity) . '_record_type');
        if (!is_array($_data)) {
            $query = "SELECT Id, Name FROM RecordType WHERE SobjectType='$entity'";

            $_data = array();
            try {
                $allRules = $this->getClient()->query($query);
                if (!empty($allRules->records)) {
                    $_data = $allRules->records;
                }
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError($e->getMessage());
            }

            $this->setStorage($_data, 'tnw_salesforce_' . strtolower($entity) . '_record_type');
        }

        return $_data;

    }

    /**
     * @param string $type
     * @return array|bool
     */
    public function getStatus($type = 'Lead')
    {
        try {
            try {
                $this->getClient();
            } catch (Exception $e) {
                return $this->_noConnectionArray;
            }

            $sfObject = ($type == 'Opportunity') ? 'OpportunityStage' : 'LeadStatus';
            $extraWhere = ($type == 'Lead') ? ' WHERE IsConverted=True ' : NULL;
            $query = "SELECT ID, MasterLabel FROM " . $sfObject . $extraWhere . " ORDER BY SortOrder";
            $allRules = $this->getClient()->queryAll(($query));
            unset($sfObject, $query);

            return $allRules->records;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not get a list of " . $type . " States");
            unset($e);

            return false;
        }
    }

    /**
     * @param array $ids
     * @return array|bool
     */
    public function opportunityLookup($ids = array())
    {
        $ids = !is_array($ids)
            ? array($ids) : $ids;

        try {

            try {
                $this->getClient();
            } catch (Exception $e) {
                return false;
            }

            $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";

            $_results = array();
            foreach (array_chunk($ids, self::UPDATE_LIMIT) as $_ids) {
                $result = $this->_queryOpportunities($_magentoId, $_ids);
                if (empty($result) || $result->size < 1) {
                    continue;
                }

                $_results[] = $result;
            }

            if (empty($_results)) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Lookup returned: no results...");
                return false;
            }

            $returnArray = array();
            foreach ($_results as $result) {
                foreach ($result->records as $_item) {
                    $tmp = new stdClass();
                    $tmp->Id = $_item->Id;
                    $tmp->AccountId = (property_exists($_item, "AccountId")) ? $_item->AccountId : NULL;
                    $tmp->Pricebook2Id = (property_exists($_item, "Pricebook2Id")) ? $_item->Pricebook2Id : NULL;
                    $tmp->MagentoId = $_item->$_magentoId;
                    $tmp->OpportunityContactRoles = (property_exists($_item, "OpportunityContactRoles")) ? $_item->OpportunityContactRoles : NULL;
                    $tmp->OpportunityLineItems = (property_exists($_item, "OpportunityLineItems")) ? $_item->OpportunityLineItems : NULL;
                    $tmp->Notes = (property_exists($_item, "Notes")) ? $_item->Notes : NULL;
                    $tmp->OwnerId = (property_exists($_item, "OwnerId")) ? $_item->OwnerId : NULL;
                    $tmp->StageName = (property_exists($_item, "StageName")) ? $_item->StageName : NULL;

                    $returnArray[$tmp->MagentoId] = $tmp;
                }
            }
            return $returnArray;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not find any existing orders in Salesforce matching these IDs (" . implode(",", $ids) . ")");

            return false;
        }
    }

    /**
     * @param $_magentoId
     * @param $ids
     * @return array|stdClass
     */
    protected function _queryOpportunities($_magentoId, $ids)
    {
        $_selectFields = array(
            "ID",
            "AccountId",
            "Pricebook2Id",
            "OwnerId",
            "StageName",
            $_magentoId,
            "(SELECT Id, ContactId, Role FROM OpportunityContactRoles)",
            "(SELECT Id, Quantity, ServiceDate, UnitPrice, PricebookEntry.ProductCode, PricebookEntry.Product2Id, PricebookEntryId, Description, PricebookEntry.UnitPrice, PricebookEntry.Name FROM OpportunityLineItems)",
            "(SELECT Id, Title, Body FROM Notes)"
        );

        $query = "SELECT " . implode(',', $_selectFields) . " FROM Opportunity WHERE " . $_magentoId . " IN ('" . implode("','", $ids) . "')";

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("QUERY: " . $query);
        try {
            $result = $this->getClient()->query($query);
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            $result = array();
        }

        return $result;
    }

    /**
     * @param null $sku
     * @param null $name
     * @return array|bool
     */
    public function productLookupAdvanced($sku = NULL, $name = null)
    {
        try {
            try {
                $this->getClient();
            } catch (Exception $e) {
                return false;
            }

            $pricebookId = Mage::helper('tnw_salesforce')->getDefaultPricebook();
            if (!$pricebookId) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not proceed with product lookup because Default Pricebook is not set.");
                return false;
            }
            $query = '';
            $query .= "SELECT ID, Name, ProductCode, (SELECT ID FROM PricebookEntries WHERE Pricebook2Id='" . $pricebookId . "') FROM Product2";

            if ($sku || $name) {
                $query .= " WHERE";
                $query .= ($sku) ? " ProductCode = '" . $sku . "'" : NULL;
                $query .= ($sku && $name) ? ' AND' : NULL;
                $query .= ($name) ? " Name LIKE '%" . $name . "%'" : NULL;
            }

            $result = $this->getClient()->query(($query));
            unset($query);
            if (!$result || $result->size < 1) {
                return false;
            }
            $returnArray = array();
            foreach ($result->records as $_item) {
                if (
                    property_exists($_item, 'PricebookEntries')
                    && $_item->PricebookEntries->size > 0
                ) {
                    $tmp = new stdClass();
                    $tmp->Id = $_item->Id;
                    $tmp->ProductCode = (property_exists($_item, 'ProductCode')) ? $_item->ProductCode : NULL;
                    $tmp->Name = $_item->Name;
                    if ($_item->PricebookEntries->size > 0) {
                        $tmp->PricebookEntityId = $_item->PricebookEntries->records[0]->Id;
                        $returnArray[$_item->Id] = $tmp;
                    }
                }
            }

            return $returnArray;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not lookup Product by partial Name '" . $name . "' & Procebook #" . $pricebookId);
            unset($prodId, $pricebookId, $e);
            return false;
        }
    }

    /**
     * @param null $prodId
     * @param null $pricebookId
     * @return bool
     */
    protected function _pricebookEntryLookup($prodId = NULL, $pricebookId = NULL)
    {
        try {
            $this->getClient();
        } catch (Exception $e) {
            return false;
        }

        $query = "SELECT ID, Product2Id, Pricebook2Id, UnitPrice";

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $query .= ", CurrencyIsoCode";
        }

        $query .= " FROM PricebookEntry WHERE ";

        if (is_array($prodId)) {
            $query .= " Product2Id IN ('" . implode("','", $prodId) . "') AND Pricebook2Id = '" . $pricebookId . "'";
        } else {
            $query .= " Product2Id='" . $prodId . "' AND Pricebook2Id = '" . $pricebookId . "'";
        }
        $result = $this->getClient()->query(($query));

        return $result;
    }
    /**
     * @param null $prodId
     * @param null $pricebookId
     * @return array|bool
     */
    public function pricebookEntryLookup($prodId = NULL, $pricebookId = NULL)
    {
        try {
            $result = $this->_pricebookEntryLookup($prodId, $pricebookId);
            unset($query);
            if (!$result || $result->size < 1) {
                return false;
            }
            if (is_array($prodId)) {
                $returnArray = array();
                foreach ($result->records as $_item) {
                    $tmp = new stdClass();
                    $tmp->Id = $_item->Id;
                    $tmp->Product2Id = $_item->Product2Id;
                    $tmp->Pricebook2Id = $_item->Pricebook2Id;
                    $tmp->UnitPrice = $this->numberFormat($_item->UnitPrice);
                    $returnArray[$_item->Product2Id] = $tmp;
                }
                return $returnArray;
            } else {
                return $result->records[0]->Id;
            }
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not lookup Pricebook Entry for Product #" . $prodId . " & Procebook #" . $pricebookId);
            unset($prodId, $pricebookId, $e);
            return false;
        }
    }

    /**
     * @param null $prodId
     * @param null $pricebookId
     * @return array|bool
     */
    public function pricebookEntryLookupMultiple($prodId = NULL, $pricebookId = NULL)
    {

        try {
            $result = $this->_pricebookEntryLookup($prodId, $pricebookId);

            if (!$result || $result->size < 1) {
                return false;
            }

            $return = array();

            if (property_exists($result, 'records') && is_array($result->records)) {
                foreach ($result->records as $item) {
                    $key = 0;
                    if (property_exists($item, 'CurrencyIsoCode')) {
                        $key = (string)$item->CurrencyIsoCode;
                    }
                    $return[$key] = (array)$item;
                }
            }

            return $return;

        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not lookup Pricebook Entry for Product #" . $prodId . " & Procebook #" . $pricebookId);
            unset($prodId, $pricebookId, $e);
            return false;
        }
    }

    /**
     * @param null $sku
     * @return array|bool
     */
    public function productLookup($sku = NULL)
    {
        $sku = !is_array($sku)
            ? array($sku) : $sku;

        if (empty($sku)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SKU is missing for product lookup");
            return false;
        }

        try {
            try {
                $this->getClient();
            } catch (Exception $e) {
                return false;
            }

            $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";

            $_results = array();
            foreach (array_chunk($sku, self::UPDATE_LIMIT) as $_sku) {
                $result = $this->_queryProduct($_magentoId, $_sku);
                if (empty($result) || $result->size < 1) {
                    continue;
                }

                $_results[] = $result;
            }

            if (empty($_results)) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Product lookup returned: no results...");
                return false;
            }

            $returnArray = array();
            foreach ($_results as $result) {
                foreach ($result->records as $_item) {
                    // Conditional preserves products only with Magento Id defined, otherwise last found product will be used
                    if (!array_key_exists($_item->ProductCode, $returnArray) || (property_exists($_item, $_magentoId) && $_item->$_magentoId)) {
                        $tmp = new stdClass();
                        $tmp->Id = $_item->Id;
                        $tmp->Name = $_item->Name;
                        $tmp->ProductCode = $_item->ProductCode;
                        $tmp->MagentoId = (property_exists($_item, $_magentoId)) ? $_item->$_magentoId : NULL;

                        $returnArray[$_item->ProductCode] = $tmp;
                    }
                }
            }

            return $returnArray;
        }
        catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not find a product by Magento SKU #" . $sku);
            return false;
        }
    }

    /**
     * @param $_magentoId
     * @param $sku
     * @return array|stdClass
     */
    protected function _queryProduct($_magentoId, $sku)
    {
        $query = "SELECT ID, ProductCode, Name, " . $_magentoId . " FROM Product2 WHERE ProductCode IN ('" . implode("','", $sku) . "')";

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("QUERY: " . $query);
        try {
            $result = $this->getClient()->query($query);
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            $result = array();
        }

        return $result;
    }

    /**
     * @return array|bool
     */
    public function getUsers()
    {
        try {
            $_users = array();
            if (Mage::helper('tnw_salesforce')->isWorking()) {
                $query = "SELECT Id, Name FROM User WHERE IsActive = true AND UserType != 'CsnOnly'";

                try {
                    $this->getClient();
                } catch (Exception $e) {
                    return $this->_noConnectionArray;
                }

                $result = $this->getClient()->query(($query));
                unset($query);

                if (!$result || $result->size < 1) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: No active users found in Salesforce!");
                    $_users[] = array(
                        'label' => 'API User',
                        'value' => 0
                    );
                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Extracted users from Salesforce!");
                    foreach ($result->records as $_user) {
                        $_users[] = array(
                            'label' => $_user->Name,
                            'value' => $_user->Id
                        );
                    }
                }
            }
            return $_users;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            unset($e, $obj, $id);
            return array();
        }
    }

    /**
     * @param $customer Mage_Customer_Model_Customer
     * @return null|string
     */
    public function getCompanyByCustomer($customer)
    {
        return TNW_Salesforce_Model_Mapping_Type_Customer::companyByCustomer($customer);
    }

    /**
     * @param $records array
     * @return array
     */
    protected function mergeRecords($records)
    {
        $_records = array();
        /** @var stdClass $record */
        foreach ($records as $record) {
            if (empty($record->records)) {
                continue;
            }

            $_records[] = $record->records;
        }

        if (count($_records) == 0) {
            return array();
        }

        return call_user_func_array('array_merge', $_records);
    }

    /**
     * @param $obj
     * @param $field
     * @param null $default
     * @return null
     */
    protected function getProperty($obj, $field, $default = null)
    {
        foreach (explode('/', $field) as $_field) {
            if (!property_exists($obj, $_field)) {
                return $default;
            }

            $obj = $obj->$_field;
        }

        return $obj;
    }

    /*               ---- OLD SHIT -------                            */


    /**
     * @param null $field
     * @return array|bool|mixed
     */
    public function getAllFields($field = NULL)
    {
        $sortedList = array();
        $list = $this->describeTable($field);
        if ($list) {
            foreach ($list->fields as $_field) {
                if (!$_field->deprecatedAndHidden) {
                    $sortedList[$_field->name] = $list->label . ' : ' . $_field->label;
                }
            }
        }

        ksort($sortedList);
        return $sortedList;
    }


    /**
     * @param string $alias
     * @return array|bool|mixed
     */
    public function describeTable($alias)
    {
        switch ($alias) {
            case 'WishlistOpportunity':
            case 'Abandoned':
                $table = 'Opportunity';
                break;
            case 'WishlistOpportunityLine':
            case 'Abandoneditem':
            case 'AbandonedItem':
                $table = 'OpportunityLineItem';
                break;
            case 'OrderInvoice':
            case 'OpportunityInvoice':
                $table = 'tnw_invoice__Invoice__c';
                break;
            case 'OrderInvoiceItem':
            case 'OpportunityInvoiceItem':
                $table = 'tnw_invoice__InvoiceItem__c';
                break;
            case 'OrderShipment':
            case 'OpportunityShipment':
                $table = 'tnw_shipment__Shipment__c';
                break;
            case 'OrderShipmentItem':
            case 'OpportunityShipmentItem':
                $table = 'tnw_shipment__ShipmentItem__c';
                break;
            case 'OrderCreditMemo':
                $table = 'Order';
                break;
            case 'OrderCreditMemoItem':
                $table = 'OrderItem';
                break;
            case 'CampaignSalesRule':
                $table = 'Campaign';
                break;

            default:
                $table = $alias;
                break;
        }

        $transport = new Varien_Object(array(
            'object_name'   => $table,
            'magento_alias' => $alias
        ));
        Mage::dispatchEvent('tnw_salesforce_describe_table', array('transport' => $transport, 'helper' => $this));
        $table = $transport->getData('object_name');

        try {
            $describe = $this->getStorage("tnw_salesforce_describe_" . strtolower($table) . "_fields");
            if (empty($describe)) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Extracting fields for " . $table . " object...");

                $describe = Mage::getResourceSingleton('tnw_salesforce_api_entity/account')
                    ->getReadConnection()
                    ->describeTable($table);

                $this->setStorage($describe, "tnw_salesforce_describe_" . strtolower($table) . "_fields");
            }

            return $describe;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not get a list of all fields from " . $alias . " Object");

            return false;
        }
    }

    /**
     * @param string $object
     * @param string $field
     * @return stdClass[]
     */
    public function getPicklistValues($object, $field)
    {
        $list = $this->describeTable($object);
        if ($list) {
            foreach ((array)$list->fields as $_field) {
                if ($_field->name == $field) {
                    return $_field->picklistValues;
                }
            }
        }

        return array();
    }

    /**
     * @param null $obj
     * @param null $id
     * @return bool
     */
    public function recordExists($obj = NULL, $id = NULL)
    {
        try {
            try {
                $this->getClient();
            } catch (Exception $e) {
                return false;
            }

            $list = $this->getClient()->retrieve("Id, Name", $obj, array($id));
            unset($obj, $id);
            return (count($list) > 0) ? true : false;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not find an " . $obj . " object #" . $id);
            unset($e, $obj, $id);
            return false;
        }
    }

    /**
     * @param null $id
     * @return bool|null
     */
    public function getAccountName($id = NULL)
    {
        try {
            return Mage::getModel('tnw_salesforce_api_entity/account')->load($id)->getData('Name');
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            return false;
        }
    }

    /**
     * @param null $email
     * @return string|false
     */
    public function isLeadConverted($email = NULL)
    {
        try {
            if (!$email) {
                return null;
            }
            $lead = Mage::getModel('tnw_salesforce_api_entity/lead')->load($email, 'Email');
            return $lead->isConverted() ? $lead->getId() : false;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not find a Lead by email: " . $email);
            return false;
        }
    }

    public function isPersonAccount($id = NULL)
    {
        try {
            try {
                $this->getClient();
            } catch (Exception $e) {
                return false;
            }
            $list = $this->getClient()->retrieve("Id, isPersonAccount", 'Account', array($id));
            return ($list && property_exists($list[0], "IsPersonAccount") && $list[0]->IsPersonAccount) ? true : false;
        } catch (Exception $e) {
            // TODO: Log exception?
            return false;
        }
    }

    /**
     * @param null $email
     * @param string $obj
     * @param array $fields
     * @return bool|null
     */
    public function getObjectId($email = NULL, $obj = "Lead", $fields = array("ID"))
    {
        try {

            try {
                $this->getClient();
            } catch (Exception $e) {
                return NULL;
            }

            $query = "SELECT " . join(",", $fields) . " FROM " . $obj . " WHERE Email='" . $email . "'";
            $list = $this->getClient()->query(($query));
            $return = (is_object($list) && property_exists($list, "records") && is_array($list->records)) ? $list->records[0] : NULL;
            return $return;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not find a " . $obj . " by email: " . $email);
            unset($e, $email);
            return false;
        }
    }

    /**
     * @return array|bool
     */
    public function getRules()
    {
        try {

            try {
                $this->getClient();
            } catch (Exception $e) {
                return $this->_noConnectionArray;
            }

            $query = "SELECT ID, Name FROM AssignmentRule";
            $allRules = $this->getClient()->query(($query));

            return ($allRules && property_exists($allRules, 'records')) ? $allRules->records : array();
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not get a list of Assignment Rules");

            return false;
        }
    }

    /**
     * @param null $obj
     * @return array|bool
     */
    public function getQuery($obj = NULL)
    {
        try {
            if (!$obj) {
                return false;
            }

            try {
                $this->getClient();
            } catch (Exception $e) {
                return false;
            }

            $sortedList = array();
            $query = "SELECT ID, Name FROM " . $obj;
            $result = $this->getClient()->query(($query));

            foreach ($result->records as $_item) {
                $sortedList[$_item->Name] = $_item->Id;
            }
            ksort($sortedList);

            return array_flip($sortedList);
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not execute Salesforce query on " . $obj . " Object");

            return false;
        }
    }

    /**
     * @param null $oid
     * @param null $cid
     * @return bool
     */
    public function roleLookup($oid = NULL, $cid = NULL)
    {
        if (!$oid) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Opportunity ID is missing for OpportunityContactRole lookup");
            return false;
        }
        if (!$cid) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Contact ID is missing for OpportunityContactRole lookup");
            return false;
        }
        try {
            try {
                $this->getClient();
            } catch (Exception $e) {
                return false;
            }

            $query = "SELECT ID FROM OpportunityContactRole WHERE OpportunityId = '" . $oid . "' AND ContactId='" . $cid . "'";
            $result = $this->getClient()->query(($query));

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Check if the customer opportunity role already exist in SalesForce...");
            if (!$result || $result->size < 1) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Lookup returned: " . $result->size . " results...");
                return false;
            }
            return $result->records;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not lookup a role for Customer #" . $cid . " & Opportunity #" . $oid);

            return false;
        }
    }

    /**
     * @return bool
     */
    public function getStandardPricebookId()
    {
        try {

            try {
                $this->getClient();
            } catch (Exception $e) {
                return false;
            }

            $query = "SELECT ID FROM Pricebook2 WHERE IsStandard = TRUE";
            $result = $this->getClient()->query(($query));
            if (!$result || $result->size < 1) {
                return false;
            }

            return $result->records[0]->Id;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not lookup standard Pricebook Id");

            return false;
        }
    }

    /**
     * @return array|bool
     */
    public function getNotStandardPricebooks()
    {
        try {

            try {
                $this->getClient();
            } catch (Exception $e) {
                return false;
            }

            $query = "SELECT ID, Name FROM Pricebook2 WHERE IsStandard = FALSE";
            $result = $this->getClient()->query(($query));
            foreach ($result->records as $_item) {
                $sortedList[$_item->Name] = $_item->Id;
            }
            ksort($sortedList);

            return array_flip($sortedList);
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not lookup non standard Pricebook Id");
            unset($e);

            return false;
        }
    }

    /**
     * @param null $oid
     * @return array|bool|string
     */
    public function getOpportunityItems($oid = NULL)
    {
        $oid = !is_array($oid)
            ? array($oid) : $oid;

        if (empty($oid)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError("ERROR, cannot extract OpportunityLineItems: No Opportunity ID was specified");
            return false;
        }

        $_results = array();
        foreach (array_chunk($oid, self::UPDATE_LIMIT) as $_oid) {
            $_results[] = $this->_queryOpportunityItems($_oid);
        }

        $records = $this->mergeRecords($_results);
        if (empty($records)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Opportunity Items lookup returned: no results...');

            return false;
        }

        return $records;
    }

    /**
     * @param array $oid
     * @return array|stdClass
     */
    protected function _queryOpportunityItems(array $oid)
    {
        $query = "SELECT ID, PricebookEntryId, Quantity, ServiceDate, UnitPrice, Description FROM OpportunityLineItem WHERE OpportunityId IN ('".implode("', '", $oid)."')";

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("OpportunityLineItem Lookup Query:\n{$query}");
        try {
            $result = $this->getClient()->query($query);
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            $result = array();
        }

        return $result;
    }

    /**
     * @param array $records
     * @param array $entities
     * @return array
     */
    public function assignLookupToEntity(array $records, array $entities)
    {
        $returnArray = array();
        $searchIndex = $this->collectLookupIndex($records);
        foreach ($entities as $entity) {
            $recordsPriority = $this->searchLookupPriorityOrder($searchIndex, $entity);
            ksort($recordsPriority, SORT_NUMERIC);

            array_walk_recursive($recordsPriority, function (&$record) use($records) {
                $record = $records[$record];
            });

            $returnArray[] = array(
                'entity' => $entity,
                'record' => $this->filterLookupByPriority($recordsPriority, $entity),
                'records' => $records
            );
        }

        return $returnArray;
    }

    /**
     * @param array $records
     * @return array
     */
    protected function collectLookupIndex(array $records)
    {
        return array();
    }

    /**
     * @param array $searchIndex
     * @param $entity
     * @return array[]
     */
    protected function searchLookupPriorityOrder(array $searchIndex, $entity)
    {
        return array();
    }

    /**
     * @param array[] $recordsPriority
     * @param $entity
     * @return stdClass|null
     */
    protected function filterLookupByPriority(array $recordsPriority, $entity)
    {
        return null;
    }

    protected function generateLookupSelect(array $columns)
    {
        return implode(', ', $columns);
    }

    /**
     * @param array $groups
     * @return string
     */
    protected function generateLookupWhere(array $groups)
    {
        $this->prepareLookupWhereGroup($groups);
        return $this->generateLookupWhereGroup($groups);
    }

    /**
     * @param array $groups
     */
    protected function prepareLookupWhereGroup(array &$groups)
    {
        foreach ($groups as &$group) {
            foreach ($group as $fieldName => &$condition) {
                switch (true) {
                    case isset($condition['=']):
                        $value = $this->soqlQuote($condition['=']);
                        $condition = "$fieldName={$value}";
                        break;

                    case isset($condition['LIKE']):
                        $value = $this->soqlQuote($condition['LIKE']);
                        $condition = "$fieldName LIKE {$value}";
                        break;

                    case isset($condition['IN']):
                        $in = implode(',', array_map(array($this, 'soqlQuote'), $condition['IN']));
                        $condition = "$fieldName IN ({$in})";
                        break;

                    default:
                        if (is_array($condition)) {
                            $this->prepareLookupWhereGroup($condition);
                        }
                        break;
                }
            }
        }
    }

    /**
     * @param $value
     * @return string
     */
    public function soqlQuote($value)
    {
        $value = addslashes($value);
        return "'$value'";
    }

    /**
     * @param array $groups
     * @return string
     */
    protected function generateLookupWhereGroup(array $groups)
    {
        $sql = '';
        $first = true;
        foreach ($groups as $key => $group) {
            foreach ($group as $fieldName => $condition) {
                $sql .= ($first ? '': " $key ");

                if (!is_array($condition)) {
                    $sql .= $condition;
                } else {
                    $sql .= "({$this->generateLookupWhereGroup($condition)})";
                }

                $first = false;
            }
        }

        return $sql;
    }
}