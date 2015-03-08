<?php

class TNW_Salesforce_Model_Config_Products_Shipping
{
    protected $_productsLookup = array();
    protected $_shippingProduct = array();

    public function toOptionArray()
    {
        if (Mage::helper('tnw_salesforce')->isWorking()) {
            $this->_getProducts();
        }
        if (!$this->_shippingProduct && !empty($this->_productsLookup)) {
            $this->_shippingProduct = array();
            foreach ($this->_productsLookup as $key => $_obj) {
                $this->_shippingProduct[] = array(
                    'label' => $_obj,
                    'value' => $key
                );
            }
        } else if (empty($this->_productsLookup)) {
            $this->_shippingProduct[] = array(
                'label' => 'No products found',
                'value' => 0
            );
        }
        return $this->_shippingProduct;
    }

    protected function _getProducts() {
        if ($collection = Mage::helper('tnw_salesforce/salesforce_data')->productLookupAdvanced(NULL, 'Shipping')) {
            foreach ($collection as $_item) {
                $this->_productsLookup[$_item->PricebookEntityId] = $_item->Name;
            }
            unset($collection, $_item);
        }
    }
}
