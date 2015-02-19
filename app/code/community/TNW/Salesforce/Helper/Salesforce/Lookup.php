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

    protected function _queryProducts($_magentoId, $sku)
    {
        if (is_array($sku)) {
            $query = "SELECT ID, ProductCode, Name, " . $_magentoId . " FROM Product2 WHERE ProductCode IN ('" . implode("','", $sku) . "')";
        } else {
            $query = "SELECT ID, ProductCode, Name, " . $_magentoId . " FROM Product2 WHERE ProductCode='" . $sku . "'";
        }
        //Mage::helper('tnw_salesforce')->log("QUERY: " . $query);
        return $this->getClient()->query(($query));
    }

    protected function _queryPricebookEntries($sku)
    {
        $query = "SELECT ID, Product2Id, Pricebook2Id, UnitPrice";
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $query .= ", CurrencyIsoCode";
        }
        $query .= " FROM PricebookEntry WHERE Product2Id IN (SELECT Id FROM Product2 WHERE ProductCode ";
        if (is_array($sku)) {
            $query .= "IN ('" . implode("','", $sku) . "'))";
        } else {
            $query .= " = '" . $sku . "')";
        }
        //Mage::helper('tnw_salesforce')->log("QUERY: " . $query);
        return $this->getClient()->query(($query));
    }

    public function productLookup($sku = NULL)
    {
        if (!$sku) {
            Mage::helper('tnw_salesforce')->log("SKU is missing for product lookup");
            return false;
        }
        try {
            if (!is_object($this->getClient())) {
                return false;
            }
            $_howMany = 100;

            $_magentoId = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix() . "Magento_ID__c";

            $_results = array();

            $_ttl = count($sku);
            if ($_ttl > $_howMany) {
                $_steps = ceil($_ttl / $_howMany);
                if ($_steps == 0) {
                    $_steps = 1;
                }
                for ($_i = 0; $_i < $_steps; $_i++) {
                    $_start = $_i * $_howMany;
                    $_skus = array_slice($sku, $_start, $_howMany, true);
                    $_results[] = $this->_queryProducts($_magentoId, $_skus);
                    $_resultsPBE[] = $this->_queryPricebookEntries($_skus);
                }
            } else {
                $_results[] = $this->_queryProducts($_magentoId, $sku);
                $_resultsPBE[] = $this->_queryPricebookEntries($sku);
            }
            Mage::helper('tnw_salesforce')->log("Check if the product already exist in SalesForce...");
            if (!$_results[0] || $_results[0]->size < 1) {
                Mage::helper('tnw_salesforce')->log("Lookup returned: " . $_results[0]->size . " results...");
                return false;
            }

            $returnArray = array();
            foreach ($_results as $result) {
                foreach ($result->records as $_item) {
                    // Conditional preserves products only with Magento Id defined, otherwise last found product will be used
                    if (!array_key_exists($_item->ProductCode, $returnArray) || (property_exists($_item, $_magentoId) && $_item->$_magentoId)) {
                        $tmp = new stdClass();
                        $tmp->Id = $_item->Id;
                        $tmp->Name = $_item->Name;
                        $tmp->ProductCode = $_item->ProductCode;
                        $tmp->MagentoId = (property_exists($_item, $_magentoId)) ? $_item->$_magentoId : NULL;
                        $tmp->PriceBooks = array();
                        foreach ($_resultsPBE as $resultPBE) {
                            if (is_array($resultPBE->records)) {
                                foreach ($resultPBE->records as $_itm) {
                                    if ($_itm->Product2Id != $_item->Id) {
                                        continue;
                                    }
                                    $tmpPBE = new stdClass();
                                    $tmpPBE->Id = $_itm->Id;
                                    $tmpPBE->UnitPrice = $_itm->UnitPrice;
                                    $tmpPBE->Pricebook2Id = $_itm->Pricebook2Id;
                                    $tmpPBE->CurrencyIsoCode = (property_exists($_itm, 'CurrencyIsoCode')) ? $_itm->CurrencyIsoCode : NULL;
                                    $tmp->PriceBooks[] = $tmpPBE;
                                }
                            }
                        }
                        $returnArray[$_item->ProductCode] = $tmp;
                    }
                    Mage::getSingleton('tnw_salesforce/connection')->clearMemory();
                }
            }
            return $returnArray;
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Could not find a product by Magento SKU #" . $sku);
            unset($sku);
            return false;
        }
    }
}