<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Data_Contact
 */
class TNW_Salesforce_Helper_Salesforce_Data_Lead extends TNW_Salesforce_Helper_Salesforce_Data
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param $_magentoId
     * @param $emails
     * @param $_websites
     * @return mixed
     */
    protected function _queryLeads($_magentoId, $emails, $_websites)
    {
        if (empty($emails)) {
            return array();
        }

        $query = "SELECT ID, OwnerId, Email, IsConverted, ConvertedAccountId, ConvertedContactId, " . $_magentoId . ", " . Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject() . " FROM Lead WHERE ";
        $_lookup = array();
        foreach($emails as $_id => $_email) {
            if (empty($_email)) {continue;}
            $tmp = "((Email='" . addslashes($_email) . "'";

            if (
                !empty($_id)
                && $_id != 0
            ) {
                $tmp .= " OR " . $_magentoId . "='" . $_id . "'";
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

        Mage::helper('tnw_salesforce')->log("QUERY: " . $query);
        try {
            $_result = $this->getClient()->query(($query));
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("ERROR: " . $e->getMessage());
            $_result = array();
        }

        return $_result;
    }

    /**
     * @param null $email
     * @param array $ids
     * @return array|bool
     */
    public function lookup($email = NULL, $ids = array())
    {
        $_howMany = 35;
        try {
            if (!is_object($this->getClient())) {
                return false;
            }
            $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";

            $_results = array();

            $_ttl = count($email);
            if ($_ttl > $_howMany) {
                $_steps = ceil($_ttl / $_howMany);
                if ($_steps == 0) {
                    $_steps = 1;
                }
                for ($_i = 0; $_i < $_steps; $_i++) {
                    $_start = $_i * $_howMany;
                    $_emails = array_slice($email, $_start, $_howMany, true);
                    $_results[] = $this->_queryLeads($_magentoId, $_emails, $ids);
                }
            } else {
                $_results[] = $this->_queryLeads($_magentoId, $email, $ids);
            }

            unset($query);
            if (empty($_results) || !$_results[0] || $_results[0]->size < 1) {
                Mage::helper('tnw_salesforce')->log("Lookup returned: no results...");
                return false;
            }

            $returnArray = array();
            foreach ($_results as $result) {
                foreach ($result->records as $_item) {
                    $tmp = new stdClass();
                    $tmp->Id = $_item->Id;
                    $tmp->Email = strtolower($_item->Email);
                    $tmp->IsConverted = $_item->IsConverted;
                    $tmp->ConvertedAccountId = (property_exists($_item, 'ConvertedAccountId')) ? $_item->ConvertedAccountId : NULL;
                    $tmp->ConvertedContactId = (property_exists($_item, 'ConvertedContactId')) ? $_item->ConvertedContactId : NULL;
                    $tmp->MagentoId = (property_exists($_item, $_magentoId)) ? $_item->{$_magentoId} : NULL;
                    $tmp->OwnerId = (property_exists($_item, 'OwnerId')) ? $_item->OwnerId : NULL;
                    if (property_exists($_item, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject())) {
                        $_websiteKey = $_item->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()};
                    } else {
                        $_websiteKey = 0;
                        if ($tmp->MagentoId && array_key_exists($tmp->MagentoId, $ids)) {
                            $_websiteKey = $ids[$tmp->MagentoId];
                        }
                        if (!$_websiteKey) {
                            // Guest, grab the first record (create other records if Magento customer scope is not global)
                            if ($tmp->MagentoId && array_key_exists($tmp->MagentoId, $ids)) {
                                $_websiteKey = $ids[$tmp->MagentoId];
                            }
                            if (!$_websiteKey) {
                                // Guest, grab the first record (create other records if Magento customer scope is not global)
                                $_personEmail = (property_exists($_item, 'PersonEmail') && $_item->PersonEmail) ? $tmp->Email : $tmp->Email;
                                $_customerId = array_search($_personEmail, $email);
                                if ($_customerId !== FALSE) {
                                    $_websiteKey = $ids[$_customerId];
                                }
                            }
                        }
                    }
                    if (
                        !$tmp->IsConverted
                        || (
                            $tmp->ConvertedAccountId
                            && $tmp->ConvertedContactId
                        )
                    )
                    $returnArray[$_websiteKey][$tmp->Email] = $tmp;
                }
            }
            return $returnArray;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not find a contact by Magento Email #" . implode(",", $email));
            unset($email);
            return false;
        }
    }
}