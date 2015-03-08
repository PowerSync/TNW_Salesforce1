<?php

class TNW_Salesforce_Model_Config_Products_Tax
{
    protected $_productsLookup = array();
    protected $_taxProduct = array();

    public function toOptionArray()
    {
        if (Mage::helper('tnw_salesforce')->isWorking()) {
            $this->_getProducts();
        }
        if (!$this->_taxProduct && !empty($this->_productsLookup)) {
            $this->_taxProduct = array();
            foreach ($this->_productsLookup as $key => $_obj) {
                $this->_taxProduct[] = array(
                    'label' => $_obj,
                    'value' => $key
                );
            }
        } else if (empty($this->_productsLookup)) {
            $this->_taxProduct[] = array(
                'label' => 'No products found',
                'value' => 0
            );
        }
        return $this->_taxProduct;
    }

    protected function _getProducts() {
        if ($collection = Mage::helper('tnw_salesforce/salesforce_data')->productLookupAdvanced(NULL, 'Tax')) {
            foreach ($collection as $_item) {
                $this->_productsLookup[$_item->PricebookEntityId] = $_item->Name;
            }
            unset($collection, $_item);
        }
    }
}
