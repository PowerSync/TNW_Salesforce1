<?php

/**
 * TODO: check depricating
 * @depricated ?
 * Class TNW_Salesforce_Helper_Customer
 */
class TNW_Salesforce_Helper_Customer extends TNW_Salesforce_Helper_Abstract
{
    /**
     * @var null
     */
    protected $_mySforceConnection = NULL;

    /**
     * @var null
     */
    protected $_order = NULL;

    /**
     * @var null
     */
    protected $_account = NULL;

    /**
     * @var null
     */
    protected $_contact = NULL;

    /**
     * @var null
     */
    protected $_lead = NULL;

    /**
     * @var null
     */
    protected $_contactId = NULL;

    /**
     * @var null
     */
    protected $_write = NULL;

    /**
     * @var array
     */
    protected $_attributes = array();

    /**
     * @var null
     */
    protected $_mapCollection = NULL;

    /**
     * @var null
     */
    protected $_mapAccountCollection = NULL;

    /**
     * @var null
     */
    protected $_customer = NULL;

    /**
     * @var null
     */
    protected $_defaultAccountId = NULL;

    /**
     * @var null
     */
    protected $_accountAttribute = NULL;
    protected $_response = NULL;

    public function __construct()
    {
        $this->preConfigure();
    }

    /**
     * @param string $type
     */
    protected function preConfigure($type = "Contact")
    {
        if (empty($this->_attributes)) {
            $resource = Mage::getResourceModel('eav/entity_attribute');
            $this->_attributes['created_in'] = $resource->getIdByCode('customer', 'created_in');
            $this->_attributes['sf_insync'] = $resource->getIdByCode('customer', 'sf_insync');
            $this->_attributes['salesforce_id'] = $resource->getIdByCode('customer', 'salesforce_id');
            $this->_attributes['salesforce_account_id'] = $resource->getIdByCode('customer', 'salesforce_account_id');
            $this->_attributes['salesforce_is_person'] = $resource->getIdByCode('customer', 'salesforce_is_person');
            $this->_attributes['password_hash'] = $resource->getIdByCode('customer', 'password_hash');
        }
        $this->_mapContactCollection = Mage::getModel('tnw_salesforce/mapping')->getCollection()->addObjectToFilter('Contact');
        $this->_mapLeadCollection = Mage::getModel('tnw_salesforce/mapping')->getCollection()->addObjectToFilter('Lead');
        if ($type == "Contact") {
            $this->_mapAccountCollection = Mage::getModel('tnw_salesforce/mapping')->getCollection()->addObjectToFilter('Account');
        }

        if (!$this->_customer) {
            $this->_customer = Mage::getModel('customer/customer');
        }
        if (!$this->_write) {
            $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        }
        if (!$this->_defaultAccountId) {
            $this->_defaultAccountId = Mage::helper('tnw_salesforce')->getDefaultAccountId();
        }
    }

    public function __destruct()
    {
        foreach ($this as $index => $value) unset($this->$index);
    }

    /**
     * Try to extract the Salesforce connection from the helper, if not available
     * we instantiate another Salesforce connection
     */
    public function checkConnection()
    {
        if (!$this->_mySforceConnection) {
            $this->_mySforceConnection = Mage::helper('tnw_salesforce/salesforce_data')->getClient();
        }
    }

    /**
     * @param null $_customer
     * @param null $_order
     * @param bool $_failSafe
     * @param bool $doInsert
     * @param bool $isGuest
     * @return bool|null
     */
    public function pushContact($_customer = NULL, $_order = NULL, $_failSafe = false, $doInsert = false, $isGuest = false)
    {
        $this->_order = $_order;
        if (
            !$_customer
            || !Mage::helper('tnw_salesforce')->isWorking()
            || Mage::getSingleton('core/session')->getFromSalesForce()
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Killing process");
            return false;
        }
        /* Existing Customer */
        if (!$_customer->getId() && $_customer->getEmail()) {
            $_customerClone = Mage::getModel('customer/customer')
                ->setWebsiteId(Mage::app()->getWebsite()->getId())
                ->loadByEmail($_customer->getEmail());
            if ($_customerClone->getId()) {
                $_customer->setId($_customerClone->getId());
            }
            unset($_customerClone);
        }
        // Check connection with Salesforce
        $this->checkConnection();
        if (!$this->_mySforceConnection) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Salesforce connection failed!");
            return;
        }
        // Try to find if contact or lead already exists
        $existingContact = Mage::helper("tnw_salesforce/salesforce_data")->getObjectId($_customer->getEmail(), "Contact", array("ID", "AccountId"));
        if (
        $existingContact
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Found contact (" . $existingContact->Id . ") by email.");
            $_customer
                ->setSalesforceAccountId($existingContact->AccountId)
                ->setSalesforceId($existingContact->Id);
        } else {
            $existingLead = Mage::helper("tnw_salesforce/salesforce_data")->getObjectId($_customer->getEmail());
            if (
                $existingLead
                && $existingLead->Id != $_customer->getSalesforceLeadId()
            ) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Found lead (" . $existingLead->Id . ") by email.");
                $_customer
                    ->setSalesforceLeadId($existingLead->Id);
            }
        }

        /* Check if we need to create this Customer as a Lead or Contact and Account */
        $contactExists = Mage::helper("tnw_salesforce/salesforce_data")->recordExists('Contact', $_customer->getSalesforceId());
        if (!$contactExists && Mage::helper("tnw_salesforce")->isCustomerAsLead()) {
            // Check if Lead already converted
            $type = ($_customer->getSalesforceId() && $_customer->getSalesforceAccountId()) ? "Contact" : "Lead";

            // Lookup
            $deleteId = Mage::helper("tnw_salesforce/salesforce_data")->isLeadConverted($_customer->getEmail());
            if (
                $_customer->getSalesforceId()
                && $_customer->getSalesforceAccountId()
                && !$contactExists
            ) {
                $type = 'Lead';
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Contact not found, check for converted lead");
                // Try Looking up Lead
                $_customer
                    ->setSalesforceAccountId(NULL)
                    ->setSalesforceId(NULL);
                if ($deleteId) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Removing converted Lead (" . $deleteId . ")");
                    $this->_mySforceConnection->delete(array($deleteId));
                    $_customer->setSalesforceLeadId(NULL);
                }
            } else if (
                $_customer->getSalesforceLeadId()
                && !Mage::helper("tnw_salesforce/salesforce_data")->recordExists('Lead', $_customer->getSalesforceLeadId())
            ) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Lead ID saved in Magento, however Lead does not exist in Salesforce");
                $_customer
                    ->setSalesforceAccountId(NULL)
                    ->setSalesforceId(NULL)
                    ->setSalesforceLeadId(NULL);
            } else if (
                $type == "Lead"
                && $deleteId
            ) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Found converted Lead (" . $deleteId . ") - Removing");
                $this->_mySforceConnection->delete(array($deleteId));
                $_customer->setSalesforceLeadId(NULL);
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Object type - " . $type);
        } else {
            $type = "Contact";
            // Check if Account ID is not set
            if (!$_customer->getSalesforceAccountId()) {
                $_accountId = Mage::helper("tnw_salesforce")->getDefaultAccountId();
                if (empty($_accountId)) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Please set default Account ID in System -> Config -> Salesforce -> Customer Configuration");
                    return $_customer;
                }
                // Set default account Id from config
                $_customer->setSalesforceAccountId($_accountId);
            }
        }

        if (
            $this->_order
            && $type == "Contact"
        ) {
            // Just need to create the opportunity, Account and Contact already created
            // We are not updating Addresses just yet, so there is no need to sync anything
            return $_customer;
        }
        /* Start Customer Update */
        try {
            # New Contact
            $this->_contact = new stdClass();
            $this->_lead = new stdClass();
            if ($type == "Lead") {
                $sfObjectType = "_lead";
            } else {
                $sfObjectType = "_contact";
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("------------------- " . $type . " Start -------------------");
            $this->preConfigure($type);

            $this->checkConnection();
            $this->_contactId = $_customer->getId();

            $logString = ($_customer->getId()) ? "customer #" . $_customer->getId() : "new customer";
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Trying to upsert " . $logString);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Contact ID: " . $_customer->getSalesforceId());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Contact Account ID: " . $_customer->getSalesforceAccountId());

            // During Order see if company field for the Billing address was filled in and use that as default Account Name
            // This is only pre-set the value, if Magento mapping for this field is configured - mapping will be used
            $_companyName = (
                $this->_order &&
                $this->_order->getBillingAddress() &&
                $this->_order->getBillingAddress()->getCompany() &&
                strlen($this->_order->getBillingAddress()->getCompany())
            ) ? $this->_order->getBillingAddress()->getCompany() : NULL;
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Company name from Billing Address: " . $_companyName);
            /* Check if Person Accounts are enabled, if not default the Company name to first and last name */
            if (!Mage::helper("tnw_salesforce")->createPersonAccount() && !$_companyName) {
                $_companyName = $_customer->getFirstname() . " " . $_customer->getLastname();
            }
            if ($type == "Lead") {
                $this->$sfObjectType->Company = $_companyName;
            } else {
                $this->$sfObjectType->AccountId = $_customer->getSalesforceAccountId();
            }
            // Default mappings
            if (!$_customer->getFirstname() || $_customer->getFirstname() == "") {
                $_customer->setFirstname('Unknown');
            }
            $this->$sfObjectType->FirstName = $_customer->getFirstname();
            if (!$_customer->getLastname() || $_customer->getLastname() == "") {
                $_customer->getLastname('Unknown');
            }
            $this->$sfObjectType->LastName = $_customer->getLastname();
            $this->$sfObjectType->Email = $_customer->getEmail();

            // Process Mapping for Contact
            $this->processMapping($_customer, $sfObjectType, $_order, $isGuest);

            if ($_customer->getSalesforceIsPerson() == 1) {
                unset($this->$sfObjectType->AccountId);
            }

            if (
                $type == 'Contact' && $_customer->getSalesforceId()
            ) {
                $upsertOn = 'Id';
                $this->$sfObjectType->Id = $_customer->getSalesforceId();
            } else {
                $upsertOn = 'Email';
            }

            /* Check if Assignment Rules are used */
            if ($type == "Lead") {
                $assignmentRule = Mage::helper('tnw_salesforce')->isLeadRule();
                if (!empty($assignmentRule) && $assignmentRule != "" && $assignmentRule != 0) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Assignment Rule used: " . $assignmentRule);
                    $header = new AssignmentRuleHeader($assignmentRule, false);
                    $this->_mySforceConnection->setAssignmentRuleHeader($header);
                    unset($assignmentRule, $header);
                }
            }
            if ($doInsert) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Force Insert!");
            }

            /* Push to Salesforce */
            Mage::dispatchEvent("tnw_salesforce_".strtolower($type)."_send_before",array("data" => array($this->$sfObjectType)));
            $resultContact = $this->_mySforceConnection->upsert($upsertOn, array($this->$sfObjectType), $type);
            Mage::dispatchEvent("tnw_salesforce_".strtolower($type)."_send_after",array(
                "data" => array($this->$sfObjectType),
                "result" => $resultContact
            ));

            /* Process Errors, Success */
            $result = (is_array($resultContact)) ? $resultContact[0] : $resultContact;
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("------------------- " . $type . " Sync End -------------------");
            if (!$result->success) {
                $errors = (is_array($result->errors)) ? $result->errors : array($result->errors);

                if ($errors[0]->message == "entity is deleted" && !$_failSafe) {
                    // Need to un-delete
                    if ($type == "Lead") {
                        $this->_mySforceConnection->undelete(array($_customer->getSalesforceLeadId()));
                    } else {
                        $this->_mySforceConnection->undelete(array($_customer->getSalesforceId()));
                        $this->_mySforceConnection->undelete(array($_customer->getSalesforceAccountId()));
                    }
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("*** " . $type . " was deleted, restoring Salesforce object ***");
                    $this->pushContact($_customer, $_order, true); // also add failsafe BOOLEAN value, just incase so we dont end up in infinite loop
                    return;
                }
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not upsert " . $type . "!");
                foreach ($errors as $_error) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Error: " . $_error->message);
                }
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($type . " #" . $result->id . " upserted");
                if ($type == "Lead") {
                    $_customer->setSalesforceLeadId($result->id);
                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("------------------- Account Sync Start -------------------");
                    // Process Mapping for Account
                    $this->_account = new stdClass();
                    $this->_account->Id = $_customer->getSalesforceAccountId();
                    $this->_account->Name = $_companyName;
                    $this->processMapping($_customer, '_account');
                    $this->pushAccount();
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("------------------- Account Sync End -------------------");
                }

            }
            Mage::getSingleton('core/session')->setFromSalesForce(true);
            $_customer->save();
            Mage::getSingleton('core/session')->setFromSalesForce(false);

            unset($result, $errors, $_error);
            return $_customer;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Failed to upsert " . $type . ": " . $e->getMessage());
            unset($e);
        }
    }

    protected function pushAccount()
    {
        $this->_account;
        try {
            Mage::dispatchEvent("tnw_salesforce_account_send_before",array("data" => array($this->_account)));
            $resultContact = $this->_mySforceConnection->upsert('Id', array($this->_account), 'Account');
            Mage::dispatchEvent("tnw_salesforce_account_send_after",array(
                "data" => array($this->_account),
                "result" => $resultContact
            ));

            /* Process Errors, Success */
            $result = (is_array($resultContact)) ? $resultContact[0] : $resultContact;
            if (!$result->success) {
                $errors = (is_array($result->errors)) ? $result->errors : array($result->errors);

                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not upsert Account!");
                foreach ($errors as $_error) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Error: " . $_error->message);
                }
            } else {
                // Don't need to update Account ID in Magento, since it's already there
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Account #" . $result->id . " upserted");
            }
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Failed to upsert Account: " . $e->getMessage());
            unset($e);
        }
    }

    /**
     * accepts a single customer object and upserts a contact into the db
     *
     * @param null $object
     * @return bool|stdClass
     */
    public function syncFromSalesforce($object = NULL)
    {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Pre Config');
        $this->preConfigure();

        $isCustomerNew = false;
        $isPersonAccount = false;

        if (property_exists($object, 'IsPersonAccount') && $object->IsPersonAccount == 1) {
            $object->Email = (property_exists($object, "PersonEmail") && $object->PersonEmail) ? $object->PersonEmail : NULL;
            $object->AccountId = (property_exists($object, "Id") && $object->Id) ? $object->Id : NULL;
            $isPersonAccount = true;
        }

        $email = (property_exists($object, "Email") && $object->Email) ? strtolower($object->Email) : NULL;
        $sfAccId = (property_exists($object, "AccountId") && $object->AccountId) ? $object->AccountId : NULL;
        $sfId = (property_exists($object, "Id") && $object->Id) ? $object->Id : NULL;
        $groupId = NULL;

        $mageId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
        $cid = (property_exists($object, $mageId) && $object->$mageId) ? $object->$mageId : NULL;

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Config Complete');

        if (!$sfId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Error upserting customer into Magento: Contact ID is missing");
            $this->_addError('Could not upsert Product into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }
        if (!$email && !$mageId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Error upserting customer into Magento: Email and Magento ID is missing");
            $this->_addError('Error upserting customer into Magento: Email and Magento ID is missing', 'EMAIL_AND_MAGENTO_ID_MISSING');
            return false;
        }
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Locate a customer in Magento');

        try {
            // Magneto ID provided
            if ($cid) {
                //Test if user exists
                $sql = "SELECT entity_id,group_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('customer_entity') . "` WHERE entity_id = '" . $cid . "'";
                $row = $this->_write->query($sql)->fetch();
                if (!$row) {
                    // Magento ID exists in Salesforce, user must have been deleted. Will re-create with the same ID
                    $isCustomerNew = true;
                } else {
                    $groupId = $row['group_id'];
                }
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('------------------');
            if ($cid && !$isCustomerNew) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Customer Loaded by using Magento ID: " . $cid);
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Possibly a New Customer');
                // No Magento ID
                if ($sfId && !$cid) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Find by SF Id');
                    // Try to find the user by SF Id
                    $sql = "SELECT entity_id FROM `" . Mage::helper('tnw_salesforce')->getTable('customer_entity_varchar') . "` WHERE value = '" . $sfId . "' AND attribute_id = '" . $this->_attributes['salesforce_id'] . "' AND entity_type_id = '1'";
                    $row = $this->_write->query($sql)->fetch();
                    $cid = ($row) ? $row['entity_id'] : NULL;
                }

                if ($cid) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Customer #" . $cid . " Loaded by using Salesforce ID: " . $sfId);
                    $sql = "SELECT entity_id,group_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('customer_entity') . "` WHERE entity_id = '" . $cid . "'";
                    $row = $this->_write->query($sql)->fetch();
                    $groupId = ($row) ? $row['group_id'] : NULL;
                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Find by email');
                    //Last reserve, try to find by email
                    $sql = "SELECT entity_id,group_id FROM `" . Mage::helper('tnw_salesforce')->getTable('customer_entity') . "` WHERE email = '" . $email . "'";
                    $row = $this->_write->query($sql)->fetch();
                    $cid = ($row) ? $row['entity_id'] : NULL;
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('MID by email: ' . $cid);
                    if ($cid) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Customer #" . $cid . " Loaded by using Email: " . $email);
                        $groupId = $row['group_id'];
                    } else {
                        //Brand new user
                        $isCustomerNew = true;
                    }
                }
            }
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: ' . $e->getMessage());
            $this->_addError('Customer location failed: ' . $e->getMessage(), 'CUSTOMER_FINDER_FAILED');
            return false;
        }


        if ($groupId === NULL) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice("SKIPPING: Sync for Customer (" . $email . "), customer was a guest!");
            $customer = new stdClass();
            $customer->isGuest = true;
            $groupId = 0;
            //return $customer;
        }
        if ($groupId === NULL || (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($groupId))) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice("SKIPPING: Sync for group #" . $groupId . " is disabled! Customer (" . $email . ")");
            $this->_addError("Sync for group #" . $groupId . " is disabled! Customer (" . $email . ")", 'CUSTOMER_SKIPPED');
            return false;
        }

        try {
            // Creating Customer Entity
            if ($isCustomerNew) {
                $this->_response->created = true;
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('New Customer');
                // New
                if ($cid) {
                    $sql = "INSERT INTO `" . Mage::helper('tnw_salesforce')->getTable('customer_entity') . "` VALUES (" . $cid . ",1,0,1,'" . $email . "',1,'',1,NOW(),NOW(),1,0)";
                } else {
                    $sql = "INSERT INTO `" . Mage::helper('tnw_salesforce')->getTable('customer_entity') . "` VALUES (NULL,1,0,1,'" . $email . "',1,'',1,NOW(),NOW(),1,0)";
                }
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($sql);
                $this->_write->query($sql);
                $cid = $this->_write->lastInsertId();
                $sql = "";
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Customer created');

            } else {
                //Existing
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('customer_entity') . "` SET email = '" . $email . "', updated_at = NOW() WHERE entity_id = '" . $cid . "';";
                $this->_response->created = false;
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Processing Magento Customer EAV Attributes');
            // creating customer attributes
            foreach ($this->_mapContactCollection as $_map) {
                $_attribute = Mage::getModel('eav/entity_attribute')->load($_map->attribute_id);
                // we ignore static and not select multiselect attributes, email is ignore in security reason just customer to be able login in magento
                //if ($_map->backend_type == "static" || is_null($_map->attribute_id)) {
                $frontendInput = $_attribute->getFrontendInput();
                if (($_map->backend_type == "static" && !in_array($frontendInput, array('select', 'multiselect'))) || is_null($_map->attribute_id)) {
                    continue;
                }
                if (!$_map->attribute_id && $_map->backend_type) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Attribute map for " . $_map->local_field . " needs to be updated!");
                    continue;
                }
                $mageGroup = explode(" : ", $_map->local_field);
                switch ($mageGroup[0]) {
                    case "Billing":
                    case "Shipping":
                        $dbPrefix = "customer_address";
                        $entityTypeId = 2;
                        break;
                    default:
                        $dbPrefix = "customer";
                        $entityTypeId = 1;
                        break;
                }
                $dbname = $dbPrefix . '_entity_' . $_map->backend_type;

                $value = $object->{$_map->sf_field};

                if ($_attribute->getFrontendInput() == 'select' && $_attribute->getAttributeCode() == 'group_id') {
                    // it's group_id drop down
                    $tableCustomerGroup = Mage::getSingleton('core/resource')->getTableName('customer/customer_group');
                    $sqlLine = "select customer_group_id from $tableCustomerGroup where customer_group_code = '$value'";
                    $res = $this->_write->query($sqlLine)->fetch();

                    // the group code not found, skipping
                    if (intval($res['customer_group_id']) < 1) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice("SKIPPING: the group code not found in magento db: $sqlLine");
                        continue;
                    }
                    $tableCustomerEntity = Mage::getSingleton('core/resource')->getTableName('customer/entity');
                    $sqlLine = "update $tableCustomerEntity set group_id = {$res['customer_group_id']} where email = '$email'";
                    $this->_write->query($sqlLine);
                    continue; // we stop the interation cause all updated
                } elseif ($_attribute->getFrontendInput() == "multiselect") {
                    $myValues = array_flip(explode(";", $object->{$_map->sf_field}));
                    $newValues = array();
                    foreach ($_attribute->getSource()->getAllOptions() as $_option) {
                        if (!empty($_option['label']) && in_array($_option['label'], $myValues)) {
                            $newValues[] = $_option['value'];
                        }
                    }
                    $value = join(",", $newValues);
                }
                $isNew = $isCustomerNew;
                // Check if value is new
                if ($entityTypeId == 2) {
                    //For Customer Address check if value already exists or not
                    $sqlCheck = "SELECT entity_id FROM `" . Mage::helper('tnw_salesforce')->getTable($dbPrefix . '_entity') . "` WHERE entity_type_id = " . $entityTypeId . " AND parent_id = " . $cid;
                    $row = $this->_write->query($sqlCheck)->fetch();
                    if ($row && array_key_exists('entity_id', $row) && $row['entity_id']) {
                        $isNew = false;
                        $eid = $row['entity_id'];
                    } else {
                        $isNew = false; // TODO: need to test, valid?
                        $eid = NULL;
                    }
                } else {
                    // Customer Entity check if value exists
                    $sqlCheck = "SELECT value_id FROM `" . Mage::helper('tnw_salesforce')->getTable($dbname) . "` WHERE entity_id = " . $cid . " AND attribute_id = '" . $_map->attribute_id . "'";
                    $row = $this->_write->query($sqlCheck)->fetch();
                    if ($row && array_key_exists('value_id', $row) && $row['value_id']) {
                        $isNew = false;
                        //$eid = $row['value_id'];
                        $eid = $cid;
                    } else {
                        $eid = "NULL";
                    }
                }

                if ($isNew) {
                    // New
                    $sql .= "INSERT INTO `" . Mage::helper('tnw_salesforce')->getTable($dbname) . "` VALUES (" . $eid . "," . $entityTypeId
                        . "," . $_map->attribute_id . "," . $cid . ",'" . $value . "');";
                } else {
                    //Existing
                    $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable($dbname) . "` SET value = '" . $value . "' WHERE entity_id = '"
                        . $eid . "' AND entity_type_id = " . $entityTypeId . " AND attribute_id = " . $_map->attribute_id . ";";
                }
            }
            /* add custom shit */
            if ($isCustomerNew) {
                // New
                $sql .= "INSERT INTO `" . Mage::helper('tnw_salesforce')->getTable($dbname) . "` VALUES (NULL,1," . $this->_attributes['created_in'] . "," . $cid . ",'Admin');";
                $newPassword = $this->_customer->hashPassword($this->_customer->generatePassword(8));
                $sql .= "INSERT INTO `" . Mage::helper('tnw_salesforce')->getTable($dbname) . "` VALUES (NULL,1," . $this->_attributes['password_hash'] . "," . $cid . ",'" . $newPassword . "');";
                $sql .= "INSERT INTO `" . Mage::helper('tnw_salesforce')->getTable($dbname) . "` VALUES (NULL,1," . $this->_attributes['salesforce_id'] . "," . $cid . ",'" . $sfId . "');";
                $sql .= "INSERT INTO `" . Mage::helper('tnw_salesforce')->getTable('customer_entity_int') . "` VALUES (NULL,1," . $this->_attributes['sf_insync'] . "," . $cid . ",'1');";
                $sql .= "INSERT INTO `" . Mage::helper('tnw_salesforce')->getTable($dbname) . "` VALUES (NULL,1," . $this->_attributes['salesforce_account_id'] . "," . $cid . ",'" . $sfAccId . "');";
                if ($isPersonAccount) {
                    $sql .= "INSERT INTO `" . Mage::helper('tnw_salesforce')->getTable($dbname) . "` VALUES (NULL,1," . $this->_attributes['salesforce_is_person'] . "," . $cid . ",'1');";
                }
                unset($newPassword);
            } else {
                //Existing
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable($dbname) . "` SET value = 'Admin' WHERE entity_id = '" . $cid . "' AND entity_type_id = 1 AND attribute_id = " . $this->_attributes['created_in'] . ";";
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable($dbname) . "` SET value = '" . $sfId . "' WHERE entity_id = '" . $cid . "' AND entity_type_id = 1 AND attribute_id = " . $this->_attributes['salesforce_id'] . ";";
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('customer_entity_int') . "` SET value = '1' WHERE entity_id = '" . $cid . "' AND entity_type_id = 1 AND attribute_id = " . $this->_attributes['sf_insync'] . ";";
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable($dbname) . "` SET value = '" . $sfAccId . "' WHERE entity_id = '" . $cid . "' AND entity_type_id = 1 AND attribute_id = " . $this->_attributes['salesforce_account_id'] . ";";
                if ($isPersonAccount) {
                    $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable($dbname) . "` SET value = '1' WHERE entity_id = '" . $cid . "' AND entity_type_id = 1 AND attribute_id = " . $this->_attributes['salesforce_is_person'] . ";";
                }
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($sql);
            $this->_write->query($sql);
            unset($customer, $object);
            $customer = new stdClass();
            $customer->isGuest = false;
            $customer->id = $cid;
            $customer->email = $email;
            $customer->sfId = $sfId;
            $customer->sfAccId = $sfAccId;
            return $customer;
        }   catch (Exception $e) {
            $this->_addError("Error upserting customer into Magento: " . $e->getMessage(), 'MAGENTO_CUSTOMER_UPSERT_FAILED');
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Error upserting customer into Magento: " . $e->getMessage() . ". SQL: " . $sql);
            unset($e);
            return false;
        }
    }

    /**
     * @param array $customers
     * @param string $type
     * @return bool
     */
    public function updateContacts($customers = array(), $type = 'Contact')
    {
        $queueStatus = true;
        if (!is_array($customers) || count($customers) == 0) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("List of customers sent for update is not a valid array or is empty.");
            return $queueStatus;
        }
        /* Try updated Salesforce Constacts with Magento Id's */
        try {
            $this->checkConnection();
            if (!$this->_mySforceConnection) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice("SKIPPING: Salesforce connection failed!");
                return;
            }
            $queuedCustomers = array();
            foreach ($customers as $_customer) {
                if (!is_object($_customer) || $_customer->isGuest) {
                    continue;
                }
                if (!property_exists($_customer, "sfId")) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Customer #" . $_customer->id . " could not be synced with Salesforce, Contact ID missing.");
                    continue;
                }
                if (!property_exists($_customer, "sfAccId")) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Customer #" . $_customer->id . " could not be synced with Salesforce, Account ID missing.");
                    continue;
                }
                $contact = new stdClass();
                $mageId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
                $contact->$mageId = $_customer->id;
                $contact->Id = $_customer->sfId;
                if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
                    $syncParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . "disableMagentoSync__c";
                    $contact->$syncParam = true;
                }
                $queuedCustomers[$_customer->id] = $contact;
                unset($contact, $_customer);
            }
            if (!empty($queuedCustomers)) {
                $_customerIds = array_keys($queuedCustomers);

                Mage::dispatchEvent("tnw_salesforce_".strtolower($type)."_send_before",array("data" => array_values($queuedCustomers)));
                $resultContact = $this->_mySforceConnection->upsert('Id', array_values($queuedCustomers), $type);
                Mage::dispatchEvent("tnw_salesforce_".strtolower($type)."_send_after",array(
                    "data" => array_values($queuedCustomers),
                    "result" => $resultContact
                ));

                /* Log Response */
                $_sfResponses = array();
                foreach($resultContact as $_key => $_result) {
                    $_sfResponses[$_customerIds[$_key]] = $_result;
                    if (!$_result->success) {
                        $queueStatus = false;
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not update Magento ID for contacts in Salesforce!");
                        $errors = (is_array($_result->errors)) ? $_result->errors : array($_result->errors);
                        foreach ($errors as $_error) {
                            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Error: " . $_error->message);
                        }
                    } else {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Contact #" . $_result->id . " in Salesforce successfully updated w/ Magento Id");
                    }
                }

                $logger = Mage::helper('tnw_salesforce/report');
                $logger->reset();
                $logger->add('Salesforce', $type, $queuedCustomers, $_sfResponses);

                $logger->send();

                unset($resultContact, $queuedCustomers);
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not create an array of queued customers to update Magneto ID");
            }
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Error: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Possibly hitting salesforce limits");
            $queueStatus = false;
            unset($e);
        }
        return $queueStatus;
    }

    /**
     * @param null $object
     * @return bool|stdClass
     */
    public function contactProcess($object = NULL)
    {
        if (
            !$object
            || !Mage::helper('tnw_salesforce')->isWorking()
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("No Salesforce object passed on connector is not working");
            return false;
        }
        $this->_response = new stdClass();
        $_type = $object->attributes->type;
        unset($object->attributes);

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("** " . $_type . " #" . $object->Id . " **");
        $customer = $this->syncFromSalesforce($object);
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("** finished upserting " . $_type . " #" . $object->Id . " **");

        // Handle success and fail
        if (is_object($customer)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Salesforce " . $_type . " #" . $object->Id . " upserted!");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Magento Id: " . $customer->id);

            $this->_assignCustomerToOrder($customer);

            $this->_response->success = true;

        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not upsert " . $_type . " into Magento, see Magento log for details");
            $customer = false;
            $this->_response->success = false;
        }

        $logger = Mage::helper('tnw_salesforce/report');
        $logger->reset();

        $logger->add('Magento', 'Customer (' . $_type . ')', array($object->Id => $object), array($object->Id => $this->_response));

        $logger->send();

        return $customer;
    }

    protected function _assignCustomerToOrder($_customer)
    {
        if (!$_customer->id || !$_customer->email) {
            return;
        }
        $orders = Mage::getModel('sales/order')->getCollection()
            ->addAttributeToSelect('entity_id')
            ->addFieldToFilter('customer_email', $_customer->email)
            ->addFieldToFilter('customer_id', array('null' => true));
        if ($orders && !empty($orders)) {
            $sql = "";
            $_ordersUpdated = array();
            foreach ($orders as $_order) {
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET customer_id = " . $_customer->id . " WHERE entity_id = " . $_order['entity_id'] . ";";
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_grid') . "` SET customer_id = " . $_customer->id . " WHERE entity_id = " . $_order['entity_id'] . ";";
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_address') . "` SET customer_id = " . $_customer->id . " WHERE parent_id = " . $_order['entity_id'] . ";";
                $_ordersUpdated[] = $_order['entity_id'];
            }
            if (!empty($sql)) {
                Mage::getSingleton('core/resource')->getConnection('core_write')->query($sql);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Orders: (" . join(', ', $_ordersUpdated) . ") were associated with customer (" . $_customer->email . ").");
            }
        }
    }

    protected function processMapping($_customer = NULL, $type = '_contact', $_order = NULL, $isGuest = false)
    {
        if (!$_customer) {
            return false;
        }

        // Process the mapping
        if ($type == '_lead') {
            $collection = $this->_mapLeadCollection;
        } else if ($type == '_account') {
            $collection = $this->_mapAccountCollection;
        } else {
            $collection = $this->_mapContactCollection;
        }
        foreach ($collection as $_map) {
            $_doSkip = $value = false;
            $conf = explode(" : ", $_map->local_field);
            $sf_field = $_map->sf_field;

            switch ($conf[0]) {
                case "Customer":
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                    $_attr = $_customer->getAttribute($conf[1]);
                    if (
                        is_object($_attr) && $_attr->getFrontendInput() == "select"
                    ) {
                        $newAttribute = $_customer->getResource()->getAttribute($conf[1])->getSource()->getOptionText($_customer->$attr());
                    } elseif (is_object($_attr) && $_attr->getFrontendInput() == "multiselect") {
                        $values = explode(",", $_customer->$attr());
                        $newValues = array();
                        foreach ($values as $_val) {
                            $newValues[] = $_customer->getResource()->getAttribute($conf[1])->getSource()->getOptionText($_val);
                        }
                        $newAttribute = join(";", $newValues);
                    } else {
                        $newAttribute = $_customer->$attr();
                    }
                    // Reformat date fields
                    if ($_map->getBackendType() == "datetime" || $conf[1] == 'created_at') {
                        if ($_customer->$attr()) {
                            $timestamp = strtotime($_customer->$attr());
                            if ($conf[1] == 'created_at') {
                                $newAttribute = gmdate(DATE_ATOM, $timestamp);
                            } else {
                                $newAttribute = date("Y-m-d", $timestamp);
                            }
                        } else {
                            $_doSkip = true; //Skip this filed if empty
                        }
                    }
                    if (!$_doSkip) {
                        $value = $newAttribute;
                    }
                    break;
                case "Billing":
                case "Shipping":
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                    $var = 'getDefault' . $conf[0] . 'Address';
                    /* only push default address if set */
                    $address = ($$var) ? $$var : $_customer->$var();
                    if ($address) {
                        $value = $address->$attr();
                        if (is_array($value)) {
                            $value = implode(", ", $value);
                        }
                    }
                    break;
                case "Custom":
                    $value = $_map->getCustomValue();
                    break;
                default:
                    break;
            }
            if ($value) {
                $this->$type->$sf_field = $value;
            }
        }
        unset($collection, $_map, $group);

        if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
            $syncParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . "disableMagentoSync__c";
            $this->$type->$syncParam = true;
        }
        $mIdParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
        $this->$type->$mIdParam = $this->_contactId;

        /* Dump contact object into the log */
        foreach ($this->$type as $key => $value) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($type . " Object: " . $key . " = '" . $value . "'");
        }
    }
}
