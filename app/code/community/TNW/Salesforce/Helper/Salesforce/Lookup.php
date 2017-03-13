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
}