<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Data
 */
class TNW_Salesforce_Helper_Salesforce_Data extends TNW_Salesforce_Helper_Salesforce
{
    /**
     * @var null
     */
    protected $_write = NULL;

    /**
     * @var array
     */
    protected $_noConnectionArray = array();

    public function __construct()
    {
        $this->connect();
    }

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
        return Mage::getSingleton('tnw_salesforce/connection')->initConnection();
    }

    /**
     * @return mixed
     */
    public function getClient()
    {
        return Mage::getSingleton('tnw_salesforce/connection')->getClient();
    }

    public function isLoggedIn()
    {
        return Mage::getSingleton('tnw_salesforce/connection')->isLoggedIn();
    }

    /**
     * @param array $emails
     * @return array
     */
    public function accountLookupByEmailDomain($emails = array(), $_hashKey = 'email')
    {
        try {
            $_domainsArray = array();
            foreach ($emails as $key => $_email) {
                if ($_hashKey == 'email') {
                    $_hashKey = $key;
                } else {
                    $_hashKey = $key;
                }
                $_domainsArray[$_hashKey] = strtolower(substr(stristr($_email, '@'), 1));
            }

            $_return = array();
            //Check if domain is a catch all.
            $_catchAllDomains = Mage::helper('tnw_salesforce')->getCatchAllAccounts();

            $_accountId = NULL;
            if (array_key_exists('domain', $_catchAllDomains) && !empty($_catchAllDomains['domain'])) {
                foreach ($_domainsArray as $_hashKey => $_domain) {
                    if (in_array($_domain, $_catchAllDomains['domain'])) {
                        $_key = array_search($_domain, $_catchAllDomains['domain']);
                        $_return[$_hashKey] = $_catchAllDomains['account'][$_key];
                    }
                }
            }

            return $_return;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not find a contact by email domain #" . join(',', $_domainsArray));
            unset($company);

            return array();
        }
    }

    /**
     * @return array|bool
     */
    public function getAccountPersonRecordType()
    {
        try {
            if (!is_object($this->getClient())) {

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
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not get a Person Record Type of the Account");
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
            if (!is_object($this->getClient())) {

                return $this->_noConnectionArray;
            }
            if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                $query = "SELECT Id, Name, IsPersonType FROM RecordType WHERE SobjectType='Account' AND IsPersonType=False";
                $allRules = $this->getClient()->query(($query));
            } else {
                $query = "SELECT Id, Name FROM RecordType WHERE SobjectType='Account'";
                $allRules = $this->getClient()->query(($query));
            }
            if ($allRules && property_exists($allRules, 'done') && $allRules->done) {
                if (!property_exists($allRules, 'records') || $allRules->size < 1) {
                    $_default = new stdClass();
                    $_default->Id = '';
                    $_default->Name = 'Use Default';
                    $allRules->records = array($_default);
                }
            }
            unset($sfObject, $query);

            return $allRules->records;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not get a Business Record Type of the Account");
            unset($e);

            return false;
        }
    }

    /**
     * @param string $type
     * @return array|bool
     */
    public function getStatus($type = 'Lead')
    {
        try {
            if (!is_object($this->getClient())) {

                return $this->_noConnectionArray;
            }
            $sfObject = ($type == 'Opportunity') ? 'OpportunityStage' : 'LeadStatus';
            $extraWhere = ($type == 'Lead') ? ' WHERE IsConverted=True ' : NULL;
            $query = "SELECT ID, MasterLabel FROM " . $sfObject . $extraWhere . " ORDER BY SortOrder";
            $allRules = $this->getClient()->queryAll(($query));
            unset($sfObject, $query);

            return $allRules->records;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not get a list of " . $type . " States");
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
        try {
            if (!is_object($this->getClient())) {

                return false;
            }
            $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
            $_selectFields = array(
                "ID",
                "AccountId",
                "Pricebook2Id",
                "OwnerId",
                $_magentoId,
                "(SELECT Id, ContactId, Role FROM OpportunityContactRoles)",
                "(SELECT Id, Quantity, ServiceDate, UnitPrice, PricebookEntry.ProductCode, PricebookEntryId, Description, PricebookEntry.UnitPrice, PricebookEntry.Name FROM OpportunityLineItems)",
                "(SELECT Id, Title, Body FROM Notes)"
            );
            if (is_array($ids)) {
                $query = "SELECT " . implode(',', $_selectFields) . " FROM Opportunity WHERE " . $_magentoId . " IN ('" . implode("','", $ids) . "')";
            } else {
                $query = "SELECT " . implode(',', $_selectFields) . " FROM Opportunity WHERE " . $_magentoId . "='" . $ids . "'";
            }
            $result = $this->getClient()->query(($query));
            unset($query);
            if (!$result || $result->size < 1) {
                Mage::helper('tnw_salesforce')->log("Opportunity lookup returned: " . $result->size . " results...");
                return false;
            }
            $returnArray = array();
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
                $returnArray[$tmp->MagentoId] = $tmp;
            }
            return $returnArray;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not find any existing orders in Salesforce matching these IDs (" . implode(",", $ids) . ")");
            unset($email);
            return false;
        }
    }

    /**
     * @param null $sku
     * @param null $name
     * @return array|bool
     */
    public function productLookupAdvanced($sku = NULL, $name = null)
    {
        try {
            if (!is_object($this->getClient())) {
                return false;
            }
            $pricebookId = Mage::helper('tnw_salesforce')->getDefaultPricebook();
            if (!$pricebookId) {
                Mage::helper('tnw_salesforce')->log("Could not proceed with product lookup because Default Pricebook is not set.");
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
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not lookup Product by partial Name '" . $name . "' & Procebook #" . $pricebookId);
            unset($prodId, $pricebookId, $e);
            return false;
        }
    }

    /**
     * @param null $prodId
     * @param null $pricebookId
     * @return array|bool
     */
    public function pricebookEntryLookup($prodId = NULL, $pricebookId = NULL)
    {
        try {
            if (!is_object($this->getClient())) {
                return false;
            }
            if (is_array($prodId)) {
                $query = "SELECT ID, Product2Id, Pricebook2Id, UnitPrice FROM PricebookEntry WHERE Product2Id IN ('" . implode("','", $prodId) . "') AND Pricebook2Id = '" . $pricebookId . "'";
            } else {
                $query = "SELECT ID, Product2Id, Pricebook2Id, UnitPrice FROM PricebookEntry WHERE Product2Id='" . $prodId . "' AND Pricebook2Id = '" . $pricebookId . "'";
            }
            $result = $this->getClient()->query(($query));
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
                    $tmp->UnitPrice = $_item->UnitPrice;
                    $returnArray[$_item->Product2Id] = $tmp;
                }
                return $returnArray;
            } else {
                return $result->records[0]->Id;
            }
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not lookup Pricebook Entry for Product #" . $prodId . " & Procebook #" . $pricebookId);
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
        if (!$sku) {
            Mage::helper('tnw_salesforce')->log("SKU is missing for product lookup");
            return false;
        }
        try {
            if (!is_object($this->getClient())) {
                return false;
            }
            $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
            if (is_array($sku)) {
                $query = "SELECT ID, ProductCode, Name, " . $_magentoId . " FROM Product2 WHERE ProductCode IN ('" . implode("','", $sku) . "')";
            } else {
                $query = "SELECT ID, ProductCode, Name, " . $_magentoId . " FROM Product2 WHERE ProductCode='" . $sku . "'";
            }
            $result = $this->getClient()->query(($query));

            unset($query);
            Mage::helper('tnw_salesforce')->log("Check if the product already exist in SalesForce...");
            if (!$result || $result->size < 1) {
                Mage::helper('tnw_salesforce')->log("Lookup returned: " . $result->size . " results...");
                return false;
            }
            $returnArray = array();
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
            return $returnArray;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not find a product by Magento SKU #" . $sku);
            unset($sku);
            return false;
        }
    }



    /**
     * @return array|bool
     */
    public function getUsers()
    {
        try {
            $_users = array();
            if (Mage::helper('tnw_salesforce')->isWorking()) {
                $query = "SELECT Id, Name FROM User WHERE IsActive = true AND UserType = 'Standard'";
                if (!is_object($this->getClient())) {
                    return $this->_noConnectionArray;
                }
                $result = $this->getClient()->query(($query));
                unset($query);

                if (!$result || $result->size < 1) {
                    Mage::helper('tnw_salesforce')->log("Error: No active users found in Salesforce!", 1, "sf-errors");
                    $_users[] = array(
                        'label' => 'API User',
                        'value' => 0
                    );
                } else {
                    Mage::helper('tnw_salesforce')->log("Extracted users from Salesforce!");
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
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage(), 1, "sf-errors");
            unset($e, $obj, $id);
            return false;
        }
    }




    /*               ---- OLD SHIT -------                            */


    /**
     * @param null $field
     * @return array|bool|mixed
     */
    public function getAllFields($field = NULL)
    {
        try {
            $_useCache = Mage::app()->useCache('tnw_salesforce');
            $cache = Mage::app()->getCache();

            if ($cache->load("tnw_salesforce_" . strtolower($field) . "_fields")) {
                $_data = unserialize($cache->load("tnw_salesforce_" . strtolower($field) . "_fields"));
            } else {
                Mage::helper('tnw_salesforce')->log("Extracting fields for " . $field . " object...");
                if (!is_object($this->getClient())) {
                    return $this->_noConnectionArray;
                }
                $sortedList = array();
                $list = $this->getClient()->describeSObject($field);
                if ($list) {
                    foreach ($list->fields as $_field) {
                        if ($_field->createable && $_field->updateable && !$_field->deprecatedAndHidden) {
                            $sortedList[$_field->name] = $field . ' : ' . $_field->label;
                        }
                    }
                }
                unset($list, $_field);
                ksort($sortedList);
                $_data = $sortedList;
                if ($_useCache) {
                    $cache->save(serialize($_data), "tnw_salesforce_" . strtolower($field) . "_fields", array("TNW_SALESFORCE"));
                }
            }

            return $_data;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage(), 1, "sf-errors");
            Mage::helper('tnw_salesforce')->log("Could not get a list of all fields from " . $field . " Object", 1, "sf-errors");
            unset($e);
            return false;
        }
    }

    /**
     * @param null $object
     * @param null $field
     * @return bool
     */
    public function getPicklistValues($object = NULL, $field = NULL)
    {
        if (!$object || !$field || !is_object($this->getClient())) {
            return false;
        }
        try {
            $list = $this->getClient()->describeSObject($object);
            if ($list) {
                foreach ($list->fields as $_field) {
                    if ($_field->name == $field) {
                        $sortedList = $_field->picklistValues;
                        return $sortedList;
                    }
                }
            }
            unset($list);
            return false;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage(), 1, "sf-errors");
            Mage::helper('tnw_salesforce')->log("Could not get picklist (" . $field . ") values from " . $object . " Object", 1, "sf-errors");
            unset($e);
            return false;
        }
    }

    /**
     * @param null $obj
     * @param null $id
     * @return bool
     */
    public function recordExists($obj = NULL, $id = NULL)
    {
        try {
            if (!is_object($this->getClient())) {
                return false;
            }
            $list = $this->getClient()->retrieve("Id, Name", $obj, array($id));
            unset($obj, $id);
            return (count($list) > 0) ? true : false;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage(), 1, "sf-errors");
            Mage::helper('tnw_salesforce')->log("Could not find an " . $obj . " object #" . $id, 1, "sf-errors");
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
            if (!is_object($this->getClient())) {
                return NULL;
            }
            Mage::helper('tnw_salesforce')->log("Trying to get Account Name for #" . $id);
            $query = "SELECT Name FROM Account WHERE Id='" . $id . "'";
            $list = $this->getClient()->query(($query));
            unset($query, $id);
            $accountName = ($list->records[0]->Name) ? $list->records[0]->Name : NULL;
            return $accountName;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage(), 1, "sf-errors");
            unset($e, $obj, $id);
            return false;
        }
    }

    /**
     * @param null $email
     * @return bool|null
     */
    public function isLeadConverted($email = NULL)
    {
        try {
            if (!is_object($this->getClient())) {
                return NULL;
            }
            $query = "SELECT ID, IsConverted FROM Lead WHERE Email='" . $email . "'";
            $list = $this->getClient()->query(($query));
            $isConverted = ($list && property_exists($list, "records") && is_array($list->records) && $list->records[0]->IsConverted) ? $list->records[0]->Id : NULL;
            return $isConverted;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage(), 1, "sf-errors");
            Mage::helper('tnw_salesforce')->log("Could not find a Lead by email: " . $email, 1, "sf-errors");
            unset($e, $email);
            return false;
        }
    }

    public function isPersonAccount($id = NULL)
    {
        try {
            if (!is_object($this->getClient())) {
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
            if (!is_object($this->getClient())) {
                return NULL;
            }
            $query = "SELECT " . join(",", $fields) . " FROM " . $obj . " WHERE Email='" . $email . "'";
            $list = $this->getClient()->query(($query));
            $return = (is_object($list) && property_exists($list, "records") && is_array($list->records)) ? $list->records[0] : NULL;
            return $return;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage(), 1, "sf-errors");
            Mage::helper('tnw_salesforce')->log("Could not find a " . $obj . " by email: " . $email, 1, "sf-errors");
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
            if (!is_object($this->getClient())) {
                return $this->_noConnectionArray;
            }
            $query = "SELECT ID, Name FROM AssignmentRule";
            $allRules = $this->getClient()->query(($query));

            return ($allRules && property_exists($allRules, 'records')) ? $allRules->records : array();
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage(), 1, "sf-errors");
            Mage::helper('tnw_salesforce')->log("Could not get a list of Assignment Rules", 1, "sf-errors");

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
            if (!$obj || !is_object($this->getClient())) {
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
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage(), 1, "sf-errors");
            Mage::helper('tnw_salesforce')->log("Could not execute Salesforce query on " . $obj . " Object", 1, "sf-errors");

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
            Mage::helper('tnw_salesforce')->log("Opportunity ID is missing for OpportunityContactRole lookup", 1, "sf-errors");
            return false;
        }
        if (!$cid) {
            Mage::helper('tnw_salesforce')->log("Contact ID is missing for OpportunityContactRole lookup", 1, "sf-errors");
            return false;
        }
        try {
            if (!is_object($this->getClient())) {
                return false;
            }
            $query = "SELECT ID FROM OpportunityContactRole WHERE OpportunityId = '" . $oid . "' AND ContactId='" . $cid . "'";
            $result = $this->getClient()->query(($query));

            Mage::helper('tnw_salesforce')->log("Check if the customer opportunity role already exist in SalesForce...");
            if (!$result || $result->size < 1) {
                Mage::helper('tnw_salesforce')->log("Lookup returned: " . $result->size . " results...", 1, "sf-errors");
                return false;
            }
            return $result->records;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage(), 1, "sf-errors");
            Mage::helper('tnw_salesforce')->log("Could not lookup a role for Customer #" . $cid . " & Opportunity #" . $oid, 1, "sf-errors");

            return false;
        }
    }

    /**
     * @return bool
     */
    public function getStandardPricebookId()
    {
        try {
            if (!is_object($this->getClient())) {

                return false;
            }
            $query = "SELECT ID FROM Pricebook2 WHERE IsStandard = TRUE";
            $result = $this->getClient()->query(($query));
            if (!$result || $result->size < 1) {
                return false;
            }

            return $result->records[0]->Id;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage(), 1, "sf-errors");
            Mage::helper('tnw_salesforce')->log("Could not lookup standard Pricebook Id", 1, "sf-errors");

            return false;
        }
    }

    /**
     * @return array|bool
     */
    public function getNotStandardPricebooks()
    {
        try {
            if (!is_object($this->getClient())) {
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
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage(), 1, "sf-errors");
            Mage::helper('tnw_salesforce')->log("Could not lookup non standard Pricebook Id", 1, "sf-errors");
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
        try {
            if (!$oid) {
                Mage::helper('tnw_salesforce')->log("Error, cannot extract OpportunityLineItems: No Opportunity ID was specified", 1, "sf-errors");

                return false;
            }
            if (!is_object($this->getClient())) {
                return false;
            }
            $query = "SELECT ID, PricebookEntryId, Quantity, ServiceDate, UnitPrice, Description FROM OpportunityLineItem WHERE OpportunityId ";
            if (is_array($oid)) {
                $query .= " IN ('".implode("', '", $oid)."')";
            } else {
                $query .= " = '$oid'";
            }

            Mage::helper('tnw_salesforce')->log("OpportunityLineItem Lookup Query: " . $query);
            $result = $this->getClient()->query(($query));
            $items = array();
            if (property_exists($result, 'records')) {
                foreach ($result->records as $_cartItem) {
                    $items[] = $_cartItem;
                }
                unset($result, $_cartItem);
            }

            return $items;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage(), 1, "sf-errors");
            Mage::helper('tnw_salesforce')->log("Could not lookup Opportunity Line Items for Opportunity #" . $oid, 1, "sf-errors");
            $errorString = $e->getMessage();

            return $errorString;
        }
    }
}