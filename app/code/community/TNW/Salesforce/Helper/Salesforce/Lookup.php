<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce_Lookup extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * @var null
     */
    protected $_write = NULL;

    /**
     * @var array
     */
    protected $_noConnectionArray = array();

    /**
     * TNW_Salesforce_Helper_Salesforce_Lookup constructor.
     */
    public function __construct()
    {
        $this->connect();
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

    /**
     * @param $_magentoId
     * @param $sku
     * @param bool $searchByName
     * @return mixed
     */
    protected function _queryProducts($_magentoId, $sku, $searchByName = false)
    {
        $pbFields = $fields = $from = $pbFrom = $orWhere = array();

        $fields[] = 'ID';
        $fields[] = 'ProductCode';
        $fields[] = 'Name';
        $fields[] = $_magentoId;

        /**
         * Start build Pricebook entities query
         */
        $pbFields[] = 'ID';
        $pbFields[] = 'Product2Id';
        $pbFields[] = 'Pricebook2Id';
        $pbFields[] = 'UnitPrice';
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $pbFields[] = "CurrencyIsoCode";
        }
        $pbFrom[] = 'PricebookEntries';

        /**
         * End build Pricebook entities query
         */
        $fields[] = '(SELECT '.implode(', ', $pbFields).' FROM '.implode(', ', $pbFrom).')';

        $from[] = 'Product2';


        if (is_array($sku)) {
            $orWhere[] = $_magentoId . " IN ('".implode("', '", array_keys($sku)). "')";
        } else {
            $sku = array($sku);
        }

        $orWhere[] = "ProductCode IN ('" . implode("', '", $sku) . "')";

        if ($searchByName == true) {

            $where = array();

            foreach ($sku as $s) {

                $where[] = " Name LIKE '%" . $s . "%'";
            }
            $orWhere[] = " (" . implode(' OR ', $where) . ") ";
        }

        $query = 'SELECT ' . implode(', ', $fields) . ' FROM ' . implode(', ', $from) . ' WHERE (' . implode(') OR (', $orWhere) . ')';

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Query: " . $query);

        $result = $this->getClient()->query(($query));

        return $result;
    }

    /**
     * @param $records array
     * @return array
     */
    protected function mergeRecords($records)
    {
        $_records = array();
        /** @var stdClass $record */
        foreach ($records as $record) {
            if (!is_object($record) || !property_exists($record, 'records') || empty($record->records)) {
                continue;
            }

            $_records[] = $record->records;
        }

        if (count($_records) == 0) {
            return array();
        }

        return call_user_func_array('array_merge', $_records);
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
            $_skuChunk = array_chunk($sku, $_howMany, true);
            foreach($_skuChunk as $_skus) {
                $_results[] = $this->_queryProducts($_magentoId, $_skus, $searchByName);
            }

            $records = $this->mergeRecords($_results);

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Product lookup result count: " . count($_results));

            $recordsMagentoId = $recordsSKU = array();

            foreach ($records as $key => $record) {
                // Index Email
                $recordsProductCode[$key] = null;
                if (!empty($record->ProductCode)) {
                    $recordsSKU[$key] = $record->ProductCode;
                }

                // Index MagentoId
                $recordsMagentoId[$key] = null;
                if (!empty($record->$_magentoId)) {
                    $recordsMagentoId[$key] = $record->$_magentoId;
                }
            }

            $returnArray = array();

            foreach ($sku as $id => $s) {
                $recordsIds = array();
                $recordsIds[] = array_keys($recordsMagentoId, $id);
                $recordsIds[] = array_keys($recordsSKU, strtolower($s));


                $record = null;
                foreach ($recordsIds as $_recordsIds) {
                    foreach ($_recordsIds as $recordsId) {
                        if (!isset($records[$recordsId])) {
                            continue;
                        }

                        $record = $records[$recordsId];
                    }

                    if (!empty($record)) {
                        break;
                    }
                }

                if (empty($record)) {
                    continue;
                }

                $record->PriceBooks = array();
                if (property_exists($record, 'PricebookEntries')) {
                    foreach ($record->PricebookEntries->records as $k => $pricebookEntry) {
                        if (!property_exists($pricebookEntry, 'CurrencyIsoCode')) {
                            $record->PricebookEntries->records[$k]->CurrencyIsoCode = null;
                        }
                    }
                    $record->PriceBooks = $record->PricebookEntries->records;
                }

                $returnArray[strtolower(trim($s))] = $record;
            }

            Mage::getSingleton('tnw_salesforce/connection')->clearMemory();

            return $returnArray;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not find a product by Magento SKU #" . var_export($sku, true));

            return false;
        }
    }
}