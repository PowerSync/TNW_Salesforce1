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
     * @param string $company
     *
     * @param $_customerEmails
     * @param array $_websites
     * @return array
     */
    public function lookup($_customerEmails, $_websites = array())
    {

        $_companies = array();
        $_emails = array();
        foreach ($_customerEmails as $_customerId => $_email) {

            if (!($_customer = Mage::registry('customer_cached_' . $_customerId))) {
                $_customer = Mage::getModel('customer/customer')->load($_customerId);
                Mage::register('customer_cached_' . $_customerId, $_customer);
            }
            /**
             * @comment try to find customer company name
             */
            $_companyName = $_customer->getCompany();

            if (!$_companyName) {
                $_companyName = (
                    $_customer->getDefaultBillingAddress() &&
                    $_customer->getDefaultBillingAddress()->getCompany() &&
                    strlen($_customer->getDefaultBillingAddress()->getCompany())
                ) ? $_customer->getDefaultBillingAddress()->getCompany() : NULL;
            }
            /* Check if Person Accounts are enabled, if not default the Company name to first and last name */
            if (!Mage::helper("tnw_salesforce")->createPersonAccount() && !$_companyName) {
                $_companyName = $_customer->getFirstname() . " " . $_customer->getLastname();
            }
            /**
             * @comment The "_" added to avoid array_merge problems: this one override numeric keys
             */
            $key = '_' . $_customer->getId();
            $_companies[$key] = $_companyName;
            $_emails[$key] = strtolower($_email);

        }

        /**
         * @comment find accounts by the Company name, domain and existing contacts
         */
        $_accountsByCompany = $this->lookupByCompanies($_companies, 'CustomIndex');

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

            if (!property_exists($result, 'records') || empty($result->records)) {
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

    /**
     * @comment Use the "CustomIndex" value in $_hashField parameter if returned array should use the keys of the $_companies array
     * @param array $_criteria
     * @param string $_hashField
     * @param string $field
     * @return array|bool
     */
    public function lookupByCriteria($_criteria = array(), $_hashField = 'Id', $field = 'Name')
    {
        try {
            if (empty($_criteria)) {
                Mage::helper('tnw_salesforce')->log("Account search criteria is not provided, SKIPPING lookup!");

                return false;
            }

            $query = "SELECT Id, OwnerId, Name FROM Account WHERE ";

            $where = array();
            foreach ($_criteria as $value) {
                if (!empty($value)) {
                    if ($field == 'Name') {
                        $where[] = "($field LIKE '%" . addslashes(utf8_encode($value)) . "%')";
                    } else {
                        $where[] = "($field = '" . addslashes(utf8_encode($value)) . "')";
                    }
                }
            }

            if (empty($where)) {
                return false;
            }

            $query .= '(' . implode(' OR ', $where) . ')';

            if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                $query .= " AND IsPersonAccount != true";
            }

            $_results = $this->getClient()->query(($query));

            if (empty($_results) || !property_exists($_results, 'size') || $_results->size < 1) {
                Mage::helper('tnw_salesforce')->log("Account lookup by " . implode(',', array_keys($_criteria)) . " returned: " . $_results->size . " results...");
                return false;
            }
            $returnArray = array();
            foreach ($_results->records as $_item) {
                $_obj = new stdClass();
                $_obj->Id = (property_exists($_item, 'Id')) ? $_item->Id : NULL;
                $_obj->OwnerId = (property_exists($_item, 'OwnerId')) ? $_item->OwnerId : NULL;

                foreach ($_criteria as $_customIndex => $_value) {
                    if (strpos($_item->$field, $_value) !== false) {
                        $_obj->$field = $_value;
                        $_obj->CustomIndex = $_customIndex;
                        break;
                    }
                }

                $_hashKey = null;

                if (!empty($_hashField)) {

                    if (property_exists($_obj, $_hashField)) {
                        $_hashKey = $_obj->$_hashField;
                    } elseif (property_exists($_item, $_hashField)) {
                        $_hashKey = $_item->$_hashField;
                    }
                }

                if (!$_hashKey) {
                    $_hashKey = $_obj->Id;
                }

                $returnArray[$_hashKey] = $_obj;

                unset($_obj);
            }

        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not find a contact by Company: " . $this->_companyName);
            unset($company);

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
}