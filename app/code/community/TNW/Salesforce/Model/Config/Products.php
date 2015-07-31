<?php

class TNW_Salesforce_Model_Config_Products
{
    protected $_productsLookup = array();
    protected $_product = array();

    public function buildDropDown($type)
    {
        if (Mage::helper('tnw_salesforce')->isWorking()) {
            $this->_getProducts($type);
        }
        if (!$this->_product && !empty($this->_productsLookup)) {
            $this->_product = array();
            foreach ($this->_productsLookup as $key => $_obj) {
                $this->_product[] = array(
                    'label' => $_obj,
                    'value' => $key
                );
            }
        } else if (empty($this->_productsLookup)) {
            $this->_product[] = array(
                'label' => 'No products found',
                'value' => 0
            );
        }
        return $this->_product;
    }

    /**
     * Load products fees from Salesforce
     * @param $type
     */
    protected function _getProducts($type) {
        $magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";

        if ($collection = Mage::helper('tnw_salesforce/salesforce_lookup')->queryProducts($magentoId, $type, true)) {
            foreach ($collection as $_item) {
                $this->_productsLookup[$this->_getKey($_item)] = $_item['Name'];
            }
            unset($collection, $_item);
        }
    }

    /**
     * Get key for the dropdown menu
     * @param $_item
     * @return mixed
     */
    protected function _getKey($_item) {
        return serialize($_item);
    }

}