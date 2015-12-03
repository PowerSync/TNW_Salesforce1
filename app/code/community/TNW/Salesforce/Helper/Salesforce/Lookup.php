<?php

class TNW_Salesforce_Helper_Salesforce_Lookup extends TNW_Salesforce_Helper_Salesforce
{
    protected $_write = NULL;
    protected $_noConnectionArray = array();

    public function __construct()
    {
        $this->connect();
    }

    public function getWriter()
    {
        if (!$this->_write) {
            $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        }
        return $this->_write;
    }

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
     * @param $_magentoId
     * @param $sku
     * @param bool|false $searchByName
     * @return mixed
     */
    public function queryProducts($_magentoId, $sku, $searchByName = false)
    {
        $return = array();

        $result = $this->_queryProducts($_magentoId, $sku, $searchByName);

        if (property_exists($result, 'records') && is_array($result->records)) {
            foreach ($result->records as $item) {
                $return[] = (array)$item;
            }
        }

        return $return;
    }

    protected function _queryProducts($_magentoId, $sku, $searchByName = false)
    {
        if (is_array($sku)) {
            $query = "SELECT ID, ProductCode, Name, " . $_magentoId . " FROM Product2 WHERE (ProductCode IN ('" . implode("','", $sku) . "')";
            $query .= " OR " . $_magentoId . " IN ('".implode("', '", array_keys($sku))."'))";
        } else {
            $query = "SELECT ID, ProductCode, Name, " . $_magentoId . " FROM Product2 WHERE (ProductCode='" . $sku . "')";
        }

        if ($searchByName == true) {

            if (!is_array($sku)) {
                $sku = array($sku);
            }
            $where = array();

            foreach ($sku as $s) {

                $where[] = " Name LIKE '%" . $s . "%'";
            }
            $query .= " OR (" . implode(' OR ', $where) . ") ";
        }

        return $this->getClient()->query(($query));
    }

    /**
     * @param $sku
     * @param bool|false $searchByName
     * @return mixed
     */
    public function queryPricebookEntries($sku, $searchByName = false) {

        $return = array();

        $result = $this->_queryPricebookEntries($sku, $searchByName);

        if (property_exists($result, 'records') && is_array($result->records)) {
            foreach ($result->records as $item) {
                $key = null;
                if (property_exists($item, 'CurrencyIsoCode')) {
                    $key = (string)$item->CurrencyIsoCode;
                }
                $return[$key] = (array)$item;
            }
        }

        return $return;
    }

    /**
     * @param $sku
     * @param bool|false $searchByName
     * @return mixed
     */
    protected function _queryPricebookEntries($sku, $searchByName = false)
    {
        $query = "SELECT ID, Product2Id, Pricebook2Id, UnitPrice";
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $query .= ", CurrencyIsoCode";
        }
        $query .= " FROM PricebookEntry WHERE Product2Id IN (SELECT Id FROM Product2 WHERE ProductCode ";
        if (is_array($sku)) {

            $query .= "IN ('" . implode("','", $sku) . "')";
            $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
            $query .= " OR " . $_magentoId . " IN ('".implode("', '", array_keys($sku))."')";
            $query .= "";

        } else {
            $query .= " = '" . $sku . "'";
        }

        if ($searchByName == true) {

            if (!is_array($sku)) {
                $sku = array($sku);
            }
            $where = array();

            foreach ($sku as $s) {

                $where[] = " Name LIKE '%" . $s . "%'";
            }
            $query .= " OR " . implode(' OR ', $where) . " ";
        }

        $query .= ")";

        return $this->getClient()->query(($query));
    }

    /**
     * @param null $sku
     * @param bool|false $searchByName
     * @return array|bool
     */
    public function productLookup($sku = NULL, $searchByName = false)
    {
        if (!$sku) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SKU is missing for product lookup");
            return false;
        }

        try {
            if (!is_object($this->getClient())) {
                return false;
            }

            $_howMany = 100;
            $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";

            $_results = $_resultsPBE = array();
            $_skuChunk = array_chunk($sku, $_howMany);
            foreach($_skuChunk as $_skus) {
                $_results[] = $this->_queryProducts($_magentoId, $_skus, $searchByName);
                $_resultsPBE[] = $this->_queryPricebookEntries($_skus, $searchByName);
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Check if the product already exist in SalesForce...");
            if (!$_results[0] || $_results[0]->size < 1) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Lookup returned: " . $_results[0]->size . " results...");
                return false;
            }

            $returnArray = array();
            foreach ($_results as $result) {
                if (!(property_exists($result, 'records') && is_array($result->records))) {
                    continue;
                }

                foreach ($result->records as $_item) {
                    // Conditional preserves products only with Magento Id defined, otherwise last found product will be used
                    if (array_key_exists($_item->ProductCode, $returnArray) || !(property_exists($_item, $_magentoId) && $_item->$_magentoId)) {
                        continue;
                    }

                    $tmp = new stdClass();
                    $tmp->Id = $_item->Id;
                    $tmp->Name = $_item->Name;
                    $tmp->ProductCode = $_item->ProductCode;
                    $tmp->MagentoId = (property_exists($_item, $_magentoId)) ? $_item->$_magentoId : NULL;
                    $tmp->PriceBooks = array();

                    foreach ($_resultsPBE as $resultPBE) {
                        if (!(property_exists($resultPBE, 'records') && is_array($resultPBE->records))) {
                            continue;
                        }

                        foreach ($resultPBE->records as $_itm) {
                            if ($_itm->Product2Id != $_item->Id) {
                                continue;
                            }

                            $tmpPBE = new stdClass();
                            $tmpPBE->Id = $_itm->Id;
                            $tmpPBE->UnitPrice = $this->numberFormat($_itm->UnitPrice);
                            $tmpPBE->Pricebook2Id = $_itm->Pricebook2Id;
                            $tmpPBE->CurrencyIsoCode = (property_exists($_itm, 'CurrencyIsoCode')) ? $_itm->CurrencyIsoCode : NULL;
                            $tmp->PriceBooks[] = $tmpPBE;
                        }
                    }

                    $returnArray[$_item->ProductCode] = $tmp;
                    Mage::getSingleton('tnw_salesforce/connection')->clearMemory();
                }
            }

            return $returnArray;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not find a product by Magento SKU #" . var_export($sku, true));

            return false;
        }
    }
}