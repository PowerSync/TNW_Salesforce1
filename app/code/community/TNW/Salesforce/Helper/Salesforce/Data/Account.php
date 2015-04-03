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
        $companies = array_chunk($companies, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT, true);

        $result = array();

        foreach ($companies as $companiesChunk) {
            $lookupResults = $this->lookupByCompany($companiesChunk, $hashField);
            if (is_array($lookupResults) && !empty($lookupResults)) {
                $result = array_merge($result, $lookupResults);
                break;
            }
        }

        return $result;
    }

    /**
     * @param array $companies
     * @param string $hashField
     *
     * @return false|array
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

            if (empty($_companies)) {
                return false;
            }

            $query = 'SELECT Id, OwnerId, Name FROM Account WHERE ';

            $where = array();
            foreach ($companies as $_company) {
                if ($_company && !empty($_company)) {
                    $where[] = "(Name LIKE '%" . addslashes(utf8_encode($_company)) . "%')";
                }
            }

            if (empty($where)) {
                return false;
            }

            $query .= '(' . implode(' OR ', $where) . ')' ;

            if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                $query .= " AND IsPersonAccount != true";
            }

            $_results = $this->getClient()->query(($query));

            if (empty($_results) || !property_exists($_results, 'size') || $_results->size < 1) {
                Mage::helper('tnw_salesforce')->log("Account lookup by company name returned: " . $_results->size . " results...");
                return false;
            }
            $returnArray = array();
            foreach ($_results->records as $_item) {
                $_obj = new stdClass();
                $_obj->Id = (property_exists($_item, 'Id')) ? $_item->Id : NULL;
                $_obj->OwnerId = (property_exists($_item, 'OwnerId')) ? $_item->OwnerId : NULL;

                foreach($_companies as $_customIndex => $_company) {
                    if (strpos($_item->Name, $_company) !== false) {
                        $_obj->Name = $_company;
                        $_obj->CustomIndex = $_customIndex;
                        break;
                    }
                }

                if (!empty($hashField)) {

                    if (property_exists($_obj, $hashField)) {
                        $returnArray[$_obj->$hashField] = $_obj;
                    } elseif (property_exists($_item, $hashField)) {
                        $returnArray[$_item->$hashField] = $_obj;
                    }

                } else {
                    $returnArray[$_obj->Id] = $_obj;
                }

                unset($_obj);
            }

            return $returnArray;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not find a contact by Company: " . $this->_companyName);
        }

        return false;
    }
}