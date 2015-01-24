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
        $this->_companyName = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $company));
    }

    /**
     * @return bool|null
     */
    public function lookupByCompany()
    {
        try {
            if (!$this->_companyName) {
                Mage::helper('tnw_salesforce')->log("Company field is not provided, SKIPPING lookup!");

                return false;
            }

            $query = "SELECT Id, OwnerId FROM Account WHERE Name LIKE '%" . $this->_companyName . "%'";
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
                $_obj->Name = $this->_companyName;
                $returnArray[$_obj->Id] = $_obj;
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