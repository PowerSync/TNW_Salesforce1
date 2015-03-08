<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Data_Account
 */
class TNW_Salesforce_Helper_Salesforce_Data_Account extends TNW_Salesforce_Helper_Salesforce_Data
{
    /** Account Name
     * @var null
     */
    protected $_companyName = NULL;


    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param null $company
     */
    public function setCompany($company = NULL)
    {
        // All punctuation
        $_regex = '/\p{P}+/i';
        $this->_companyName = preg_replace($_regex, '_', $company);

        // All whitespaces
        $_regex = '/\p{Z}+/i';
        $this->_companyName = preg_replace($_regex, '_', $company);

        $this->_companyName = strtolower($this->_companyName);
    }

    /**
     * @comment find and return accounts by company names
     * @param $companies
     */
    public function lookupByCompanies($_companies, $_hashField = 'Id')
    {
        $_companies = array_chunk($_companies, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT, true);

        $result = array();

        foreach ($_companies as $_companiesChunk) {
            $result = array_merge($result, $this->lookupByCompany($_companiesChunk, $_hashField));
        }

        return $result;
    }

    /**
     * @return bool|null
     */
    public function lookupByCompany($_companies = array(), $_hashField = 'Id')
    {
        try {
            if (!$this->_companyName && empty($_companies)) {
                Mage::helper('tnw_salesforce')->log("Company field is not provided, SKIPPING lookup!");

                return false;
            }

            if (!$_companies) {
                $_companies = array($this->_companyName);
            }

            $query = "SELECT Id, OwnerId, Name ";

            $query .= "FROM Account WHERE ";

            $where = array();
            foreach ($_companies as $_company) {
                $where[] = "(Name LIKE '%" . utf8_encode($_company) . "%')";
            }

            $query .= implode(' OR ', $where) ;

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
//                $_obj->Name = $this->_companyName;

                foreach($_companies as $_customIndex => $_company) {
                    if (strpos($_item->Name, $_company) !== false) {
                        $_obj->Name = $_company;
                        $_obj->CustomIndex = $_customIndex;
                        break;
                    }
                }

                if (!empty($_hashField)) {

                    if (property_exists($_obj, $_hashField)) {
                        $returnArray[$_obj->$_hashField] = $_obj;
                    } elseif (property_exists($_item, $_hashField)) {
                        $returnArray[$_item->$_hashField] = $_obj;
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
            unset($company);

            return false;
        }
    }
}