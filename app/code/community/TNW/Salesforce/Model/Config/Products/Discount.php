<?php

class TNW_Salesforce_Model_Config_Products_Discount
{
    protected $_productsLookup = array();
    protected $_discountProduct = array();

    public function toOptionArray()
    {
        if (Mage::helper('tnw_salesforce')->isWorking()) {
            $this->_getProducts();
        }
        if (!$this->_discountProduct && !empty($this->_productsLookup)) {
            foreach ($this->_productsLookup as $key => $_obj) {
                $this->_discountProduct[] = array(
                    'label' => $_obj,
                    'value' => $key
                );
            }
        } else if (empty($this->_productsLookup)) {
            $this->_discountProduct[] = array(
                'label' => 'No products found',
                'value' => 0
            );
        }
        return $this->_discountProduct;
    }

    protected function _getProducts() {
        if ($collection = Mage::helper('tnw_salesforce/salesforce_data')->productLookupAdvanced(NULL, 'Discount')) {
            foreach ($collection as $_item) {
                $this->_productsLookup[$_item->PricebookEntityId] = $_item->Name;
            }
            unset($collection, $_item);
        }
    }
}
