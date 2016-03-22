<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Currency
{
    /**
     * @var array
     */
    protected $_cache = array();

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return $this->getOptions();
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        if (!$this->_cache) {
            $this->_cache[] = array(
                'label' => 'No',
                'value' => '0'
            );

            $_currencyList = array();
            foreach (Mage::app()->getStores() as $_storeId => $_store) {
                $_currencies = Mage::app()->getStore($_storeId)->getAvailableCurrencyCodes();
                foreach($_currencies as $_currency) {
                    if (!in_array($_currency, $_currencyList)) {
                        $_currencyList[] = $_currency;
                    }
                }
            }
            if (
                Mage::helper('tnw_salesforce')->getType() == "PRO"
                && count($_currencyList) > 1
            ) {
                $this->_cache[] = array(
                    'label' => 'Yes',
                    'value' => '1'
                );
            }
        }

        return $this->_cache;
    }
}