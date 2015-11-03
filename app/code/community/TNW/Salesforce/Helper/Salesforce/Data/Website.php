<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Data_Website
 */
class TNW_Salesforce_Helper_Salesforce_Data_Website extends TNW_Salesforce_Helper_Salesforce_Data
{
    protected $_fields = array();

    public function __construct()
    {
        parent::__construct();

        $this->_fields = array(
            'name'          =>  'Name',
            'website_id'    =>  Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Website_ID__c',
            'sort_order'    =>  Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Sort_Order__c',
            'code'          =>  Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Code__c',
        );
    }

    /**
     * @param $_ids
     * @param $_codes
     * @return array
     */
    protected function _queryWebsites($_ids, $_codes)
    {
        $query = "SELECT Id," . join(',', $this->_fields) . " FROM " . Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject() . " WHERE ";
        if (is_array($_ids)) {
            $query .= $this->_fields['website_id'] . " IN (" . implode(",", $_ids) . ")";
        } else {
            $query .= $this->_fields['website_id'] . "=" . $_ids;
        }

        if (!empty($_codes)) {
            $query .= " OR " . $this->_fields['code'] . " IN ('" . implode("','", $_codes) . "')";
        }

        Mage::getModel('tnw_salesforce/tool_log')->saveTrace("QUERY: " . $query);

        try {
            $_result = $this->getClient()->query(($query));
        } catch (Exception $e) {
            Mage::getModel('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            $_result = array();
        }

        return $_result;
    }

    public function websiteLookup($_codes, $_ids) {
        $_howMany = 50;
        try {
            if (!is_object($this->getClient())) {
                return false;
            }
            $_results = array();

            $_ttl = count($_ids);
            if ($_ttl > $_howMany) {
                $_steps = ceil($_ttl / $_howMany);
                for ($_i = 0; $_i < $_steps; $_i++) {
                    $_start = $_i * $_howMany;
                    $_websites = array_slice($_codes, $_start, $_howMany, true);
                    $_chunkedIds = array_slice($_ids, $_start, $_howMany, true);
                    $_results[] = $this->_queryWebsites($_chunkedIds, $_websites);
                }
            } else {
                $_results[] = $this->_queryWebsites($_ids, $_codes);
            }
            unset($query);
            if (empty($_results) || !$_results[0] || $_results[0]->size < 1) {
                Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Website lookup returned: " . $_results[0]->size . " results...");
                return false;
            }
            $returnArray = array();
            foreach ($_results as $result) {
                foreach ($result->records as $_item) {
                    $tmp = new stdClass();
                    $tmp->Id = $_item->Id;

                    $tmp->{$this->_fields['name']} = (property_exists($_item, $this->_fields['name'])) ? $_item->{$this->_fields['name']} : NULL;
                    $tmp->{$this->_fields['website_id']} = (property_exists($_item, $this->_fields['website_id'])) ? $_item->{$this->_fields['website_id']} : NULL;
                    $tmp->{$this->_fields['sort_order']} = (property_exists($_item, $this->_fields['sort_order'])) ? $_item->{$this->_fields['sort_order']} : NULL;
                    $tmp->{$this->_fields['code']} = (property_exists($_item, $this->_fields['code'])) ? $_item->{$this->_fields['code']} : NULL;

                    if ($tmp->{$this->_fields['website_id']}) {
                        $returnArray[$tmp->{$this->_fields['website_id']}] = $tmp;
                    } elseif ($_item->Id) {
                        $returnArray[$_item->Id] = $tmp;
                    }
                }
            }
            return $returnArray;
        } catch (Exception $e) {
            Mage::getModel('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Could not find a website by Magento ID's #: " . implode(",", $_ids));
            unset($email);
            return false;
        }
    }
}