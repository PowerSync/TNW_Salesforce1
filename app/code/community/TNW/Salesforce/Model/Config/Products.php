<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Products
{
    protected $_productsLookup = array();
    protected $_product = array();

    public function buildDropDown($type)
    {
        try {
            if (Mage::helper('tnw_salesforce')->isWorking()) {
                $this->_getProducts($type);
            }

            $this->_product = array();
            $this->_product[] = array(
                'label' => 'Generate new product',
                'value' => 0
            );

            if (!empty($this->_productsLookup)) {
                foreach ($this->_productsLookup as $key => $_obj) {
                    $this->_product[] = array(
                        'label' => $_obj,
                        'value' => $key
                    );
                }
            }
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError($e->getMessage());
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