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
    public function getProductPricebookEntry($salesforceProductId, $pricebookId)
    {
        $cache = Mage::app()->getCache();
        $useCache = Mage::app()->useCache('tnw_salesforce');

        if ($useCache && empty($this->_feeProductsPricebook)) {
            $this->_productsPricebookEntry = $cache->load('tnw_salesforce_products_pricebook_entry');
        }

        if (!isset($this->_productsPricebookEntry[$salesforceProductId]) || !isset($this->_productsPricebookEntry[$salesforceProductId][$pricebookId])) {
            $this->_productsPricebookEntry[$salesforceProductId][$pricebookId] = Mage::helper('tnw_salesforce/salesforce_data')->pricebookEntryLookupMultiple($salesforceProductId, $pricebookId);

            if ($useCache) {
                $cache->save($this->_productsPricebookEntry, "tnw_salesforce_products_pricebook_entry", array("TNW_SALESFORCE"));
            }
        }

        return $this->_productsPricebookEntry[$salesforceProductId][$pricebookId];
    }

}