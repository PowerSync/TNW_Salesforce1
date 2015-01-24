<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Data_Contact
 */
class TNW_Salesforce_Helper_Salesforce_Data_Contact extends TNW_Salesforce_Helper_Salesforce_Data
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param $_magentoId
     * @param $_extra
     * @param $email
     * @param $ids
     * @return array
     */
    protected function _queryContacts($_magentoId, $_extra, $_emails, $_websites)
    {
        $query = "SELECT ID, FirstName, LastName, Email, AccountId, OwnerId, " . $_magentoId . $_extra . " FROM Contact WHERE ";

        $_lookup = array();
        foreach($_emails as $_id => $_email) {
            $tmp = "((Email='" . addslashes($_email) . "'";

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
                $tmp .= " AND (" . $this->getSfPrefix() . "Website__c = '" . $_websites[$_id] . "' OR " . $this->getSfPrefix() . "Website__c = '')";
            }
            $tmp .= ")";
            $_lookup[] = $tmp;
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
    public function lookup($email = NULL, $_websites = array())
    {
        $_howMany = 50;
        try {
            if (!is_object($this->getClient())) {
                return false;
            }
            $_magentoId = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix() . "Magento_ID__c";
            $_extra = NULL;
            if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
                $_personMagentoId = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix() . "Magento_ID__pc";
                $_extra = ", Account.OwnerId, Account.Name, Account.RecordTypeId, Account.IsPersonAccount, Account.PersonEmail, Account." . $_personMagentoId . ", Account.Id";
            } else {
                $_extra = ", Account.OwnerId, Account.Name";
            }
            if (
                Mage::helper('tnw_salesforce')->getCustomerScope() == "1"
            ) {
                $_extra .= ", " . $this->getSfPrefix() . "Website__c";
            }
            $_results = array();

            $_ttl = count($email);
            if ($_ttl > $_howMany) {
                $_steps = ceil($_ttl / $_howMany);
                for ($_i = 0; $_i < $_steps; $_i++) {
                    $_start = $_i * $_howMany;
                    $_emails = array_slice($email, $_start, $_howMany, true);
//                    $_chunkedWebsites = array_slice($_websites, $_start, $_howMany, true);
//                    $_results[] = $this->_queryContacts($_magentoId, $_extra, $_emails, $_chunkedWebsites);
                    $_results[] = $this->_queryContacts($_magentoId, $_extra, $_emails, $_websites);
                }
            } else {
                $_results[] = $this->_queryContacts($_magentoId, $_extra, $email, $_websites);;
            }

            unset($query);
            if (empty($_results) || !$_results[0] || $_results[0]->size < 1) {
                Mage::helper('tnw_salesforce')->log("Contact lookup returned: " . $_results[0]->size . " results...");
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
                    if (property_exists($_item, $this->getSfPrefix() . 'Website__c')) {
                        $_websiteKey = $_item->{$this->getSfPrefix().'Website__c'};
                    } else {
                        $_websiteKey = 0;
                        if ($tmp->MagentoId && array_key_exists($tmp->MagentoId, $_websites)) {
                            $_websiteKey = $_websites[$tmp->MagentoId];
                        }
                        if (!$_websiteKey) {
                            // Guest, grab the first record (create other records if Magento customer scope is not global)
                            $_personEmail = (property_exists($_item, 'PersonEmail') && $_item->PersonEmail) ? $tmp->Email : $tmp->Email;
                            $_customerId = array_search($_personEmail, $email);
                            if ($_customerId !== FALSE) {
                                $_websiteKey = $_websites[$_customerId];
                            }
                        }
                    }
                    $returnArray[$_websiteKey][$_key] = $tmp;
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