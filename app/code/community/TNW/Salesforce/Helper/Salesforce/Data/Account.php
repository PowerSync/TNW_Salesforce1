<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Data_Account
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
     * @param $_customerEmails
     * @param array $_websites
     * @return array
     *
     * @comment returns following structure: 0 => "$emails" => account data
     */
    public function lookup($_customerEmails, $_websites = array())
    {

        $_companies = array();
        $_emails = array();
        foreach ($_customerEmails as $_customerId => &$_email) {

            if ($_email instanceof Mage_Customer_Model_Customer) {
                $_customer = $_email;
                $_email = $_customer->getEmail();
            } else {
                $_customer = Mage::registry('customer_cached_' . $_customerId);
                if (!$_customer && $_customerId) {
                    $_customer = Mage::getModel('customer/customer')->load($_customerId);
                    Mage::register('customer_cached_' . $_customerId, $_customer);
                }
            }

            $key = '_' . $_customerId;
            $_emails[$key] = strtolower($_email);

            //try to find customer company name
            if ($_customer) {
                $_companyName = $_customer->getCompany();

                if (!$_companyName) {
                    $_companyName = $_customer->getDefaultBillingAddress()
                        ? trim($_customer->getDefaultBillingAddress()->getCompany()) : null;
                }

                //for guest get data from another path
                if (!$_companyName) {
                    $_companyName = $_customer->getBillingAddress()
                        ? trim($_customer->getBillingAddress()->getCompany()) : null;
                }

                /* Check if Person Accounts are enabled, if not default the Company name to first and last name */
                if (!$_companyName && !Mage::helper("tnw_salesforce")->createPersonAccount()) {
                    $_companyName = trim($_customer->getFirstname() . ' ' . $_customer->getLastname());
                }

                if ($_companyName) {
                    $_companies[$key] = $_companyName;
                }
            }
        }

        /**
         * @comment find accounts by the Company name, domain and existing contacts
         */
        $_accountsByCompany = (!empty($_companies)) ? $this->lookupByCompanies($_companies, 'CustomIndex') : array();

        $_accountsByDomain = $this->lookupByEmailDomain($_emails, 'id');

        $_accountsByContacts = $this->lookupByContact($_customerEmails, $_websites);

        $_accounts = array_merge($_accountsByCompany, $_accountsByDomain, $_accountsByContacts);

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
     * @param null $emails
     * @param array $websites
     * @param string $field
     * @return array, key - customerId
     */
    public function lookupByContact($emails = NULL, $websites = array(), $field = 'id')
    {
        $_results = Mage::helper('tnw_salesforce/salesforce_data_contact')->getContactsByEmails($emails, $websites);

        $returnArray = array();

        $_contactMagentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
        $_accountMagentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__pc";

        foreach ($_results as $result) {

            if (!is_object($result) || !property_exists($result, 'records') || empty($result->records)) {
                continue;
            }

            foreach ($result->records as $_item) {

                if (!property_exists($_item, 'Account')) {
                    continue;
                }

                $key = null;

                $_contactMagentoIdValue = property_exists($_item, $_contactMagentoId)? $_item->$_contactMagentoId: null;
                $_accountMagentoIdValue = property_exists($_item->Account, $_accountMagentoId)? $_item->Account->$_accountMagentoId: null;
                $_email = (property_exists($_item, 'Email') && $_item->Email) ? strtolower($_item->Email) : NULL;

                if (isset($emails[$_contactMagentoIdValue])) {
                    $key = $_contactMagentoIdValue;
                } elseif (isset($emails[$_accountMagentoIdValue])) {
                    $key = $_accountMagentoIdValue;
                } elseif (array_search($_email, $emails) !== false) {
                    $key = array_search($_email, $emails);
                }

                if ($field == 'email') {
                    $key = $emails[$key];
                } elseif ($field == 'id') {
                    $key = '_' . $key;
                }

                $returnArray[$key] = $_item->Account;
                $returnArray[$key]->Id = $_item->AccountId;
            }
        }
        return $returnArray;
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
        $accountIds = Mage::helper('tnw_salesforce/salesforce_data')->accountLookupByEmailDomain($emails, $_hashKey);
        return $this->lookupByCriterias($accountIds, 'CustomIndex','Id');
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

    protected function _prepareCriteriaSql($criteria, $field)
    {
        $sql = 'SELECT Id, OwnerId, Name FROM Account WHERE ';

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
                Mage::helper('tnw_salesforce')->log("Account search criteria is not provided, SKIPPING lookup!");

                return false;
            }

            $sql = $this->_prepareCriteriaSql($criteria, $field);
            $result = Mage::getSingleton('tnw_salesforce/api_client')->query($sql);

            if (empty($result)) {
                $this->log("Account lookup by " . var_export($criteria, true) . " returned: 0 results...");
                return false;
            }

            $returnArray = array();
            foreach ($result as $_item) {
                $_returnObject = new stdClass();
                $_returnObject->Id = (isset($_item['Id'])) ? $_item['Id'] : NULL;
                $_returnObject->OwnerId = (isset($_item['OwnerId'])) ? $_item['OwnerId'] : NULL;
                $_returnObject->Name = (isset($_item['Name'])) ? $_item['Name'] : NULL;

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
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not find an account by criteria: " . var_export($criteria));

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
                Mage::helper('tnw_salesforce')->log("Company field is not provided, SKIPPING lookup!");

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
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not find a contact by Company: " . $this->_companyName);
        }

        return false;
    }

    public function lookupContactIds($where)
    {
        $sql = "SELECT Id, PersonContactId FROM Account WHERE Id IN ('" . implode("','", array_values($where)) . "')";
        $result = Mage::getSingleton('tnw_salesforce/api_client')->query($sql);

        if (empty($result)) {
            $this->log("PersonAccount lookup did not return any results...");
            return false;
        }

        return $result;
    }
}