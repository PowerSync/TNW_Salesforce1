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
        $cache = Mage::app()->getCache();
        $useCache = Mage::app()->useCache('tnw_salesforce');

        if ($useCache && empty($this->_feeProductsPricebook)) {
            $this->_productsPricebookEntry = $cache->load('tnw_salesforce_products_pricebook_entry');
        }

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

            /**
             * if currency code defined - use it as index or use '0', if multicurrency not enabled in Salesforce
             */
            if (!(is_null($currencyCode))
                && !isset($this->_productsPricebookEntry[$salesforceProductId][$pricebookId][$currencyCode])
                && isset($this->_productsPricebookEntry[$salesforceProductId][$pricebookId][0])
            ) {
                $currencyCode = 0;
            }

            if ($useCache) {
                $cache->save($this->_productsPricebookEntry, "tnw_salesforce_products_pricebook_entry", array("TNW_SALESFORCE"));
            }
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

}