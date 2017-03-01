<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Helper_Salesforce_Data_Product
 */
class TNW_Salesforce_Helper_Salesforce_Data_Product extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * @var array
     */
    protected $_productsPricebookEntry = array();

    /**
     * @param $salesforceProductId
     * @param $pricebookId
     * @return mixed
     */
    public function getProductPricebookEntry($salesforceProductId, $pricebookId, $currencyCode = null)
    {
        $this->_productsPricebookEntry = $this->getStorage('tnw_salesforce_products_pricebook_entry');

        /**
         * try to find pricebook entries for product
         * if currency defined - try to return entry for this criteria too
         */
        if (!isset($this->_productsPricebookEntry[$salesforceProductId])
            || !isset($this->_productsPricebookEntry[$salesforceProductId][$pricebookId])
            || ( !(is_null($currencyCode))
                && !isset($this->_productsPricebookEntry[$salesforceProductId][$pricebookId][$currencyCode])
                && !isset($this->_productsPricebookEntry[$salesforceProductId][$pricebookId][0])
            )
        ) {
            $this->_productsPricebookEntry[$salesforceProductId][$pricebookId] = Mage::helper('tnw_salesforce/salesforce_data')->pricebookEntryLookupMultiple($salesforceProductId, $pricebookId);

            $this->setStorage($this->_productsPricebookEntry, "tnw_salesforce_products_pricebook_entry");
        }
        /**
         * if currency code defined - use it as index or use '0', if multicurrency not enabled in Salesforce
         */
        if (!(is_null($currencyCode))
            && !isset($this->_productsPricebookEntry[$salesforceProductId][$pricebookId][$currencyCode])
            && isset($this->_productsPricebookEntry[$salesforceProductId][$pricebookId][0])
        ) {
            $currencyCode = 0;
        }

        /**
         * return entry by currency code or all entries list for this pricebook
         */
        if (!(is_null($currencyCode))) {
            if (!isset($this->_productsPricebookEntry[$salesforceProductId][$pricebookId][$currencyCode])) {
                $this->_productsPricebookEntry[$salesforceProductId][$pricebookId][$currencyCode] = null;
            }

            return $this->_productsPricebookEntry[$salesforceProductId][$pricebookId][$currencyCode];
        } else {
            return $this->_productsPricebookEntry[$salesforceProductId][$pricebookId];
        }
    }

    /**
     * @param Mage_Catalog_Model_Product[] $products
     * @return array
     */
    public function lookup(array $products)
    {
        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';

        $_results = array();
        foreach (array_chunk($products, self::UPDATE_LIMIT, true) as $_products) {
            $_results[] = $this->_queryProducts($_products);
        }

        $records = $this->mergeRecords($_results);
        if (empty($records)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Product lookup returned: no results...');

            return array();
        }

        $returnArray = $clearToUpsert = array();
        foreach ($this->assignLookupToEntity($records, $products) as $item) {
            if (empty($item['record'])) {
                continue;
            }

            $records = $item['records'];
            unset($records[reset(array_keys($records, $item['record'], true))]);
            foreach ($records as $record) {
                if (empty($record->$_magentoId)) {
                    continue;
                }

                if ($record->$_magentoId == $item['entity']->getId()) {
                    $upsert = new stdClass();
                    $upsert->Id = $record->Id;
                    $upsert->$_magentoId = ' ';

                    $clearToUpsert[] = $upsert;
                }
            }

            $return = $this->prepareRecord($item['entity'], $item['record']);
            if (empty($return)) {
                continue;
            }

            $returnArray = array_merge($returnArray, $return);
        }

        if (!empty($clearToUpsert)) {
            //Clear Magento Id
            $this->getClient()->upsert('Id', $clearToUpsert, 'Product2');
        }

        return $returnArray;
    }

    /**
     * @param Mage_Catalog_Model_Product[] $products
     * @return stdClass
     */
    protected function _queryProducts(array $products)
    {
        $columns = $this->columnsLookupQuery();
        $conditions = $this->conditionsLookupQuery($products);

        $query = sprintf('SELECT %s FROM Product2 WHERE %s',
            $this->generateLookupSelect($columns),
            $this->generateLookupWhere($conditions));

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace("Product QUERY:\n{$query}");

        return $this->getClient()->query($query);
    }

    /**
     * @return array
     */
    protected function columnsLookupQuery()
    {
        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';

        /**
         * Start build Pricebook entities query
         */
        $pbFields[] = 'ID';
        $pbFields[] = 'Product2Id';
        $pbFields[] = 'Pricebook2Id';
        $pbFields[] = 'UnitPrice';
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $pbFields[] = 'CurrencyIsoCode';
        }

        return array(
            'ID', 'ProductCode', 'Name', $_magentoId,
            '(SELECT '.implode(', ', $pbFields).' FROM PricebookEntries)'
        );
    }

    /**
     * @param Mage_Catalog_Model_Product[] $products
     * @return mixed
     */
    protected function conditionsLookupQuery(array $products)
    {
        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';

        $conditions = array();
        /** @var Mage_Catalog_Model_Product $product */
        foreach ($products as $product) {
            $conditions['OR'][$_magentoId]['IN'][] = $product->getId();
            $conditions['OR']['ProductCode']['IN'][] = $product->getSku();
        }

        return $conditions;
    }

    /**
     * @param array $records
     * @return array
     */
    protected function collectLookupIndex(array $records)
    {
        $searchIndex = array();
        foreach ($records as $key => $record) {
            // Index SKU
            $searchIndex['sku'][$key] = null;
            if (!empty($record->ProductCode)) {
                $searchIndex['sku'][$key] = strtolower($record->ProductCode);
            }
        }

        return $searchIndex;
    }

    /**
     * @param array $searchIndex
     * @param Mage_Catalog_Model_Product $entity
     * @return array[]
     */
    protected function searchLookupPriorityOrder(array $searchIndex, $entity)
    {
        $recordsIds = array();

        // Priority 1
        $recordsIds[10] = array_keys($searchIndex['sku'], strtolower($entity->getSku()));

        return $recordsIds;
    }

    /**
     * @param array[] $recordsPriority
     * @param Mage_Catalog_Model_Product $entity
     * @return stdClass|null
     */
    protected function filterLookupByPriority(array $recordsPriority, $entity)
    {
        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';

        $findRecord = null;
        foreach ((array)reset($recordsPriority) as $record) {

            $findRecord = $record;

            if (!empty($record->$_magentoId) && $record->$_magentoId == $entity->getId()) {
                break;
            }
        }

        return $findRecord;
    }

    /**
     * @param $customer Mage_Catalog_Model_Product
     * @param $record stdClass
     * @return array
     */
    public function prepareRecord($customer, $record)
    {
        $record->PriceBooks = array();
        if (property_exists($record, 'PricebookEntries')) {
            foreach ($record->PricebookEntries->records as $k => $pricebookEntry) {
                if (!property_exists($pricebookEntry, 'CurrencyIsoCode')) {
                    $record->PricebookEntries->records[$k]->CurrencyIsoCode = null;
                }
            }
            $record->PriceBooks = $record->PricebookEntries->records;
        }

        return array(strtolower(trim($customer->getSku())) => $record);
    }
}