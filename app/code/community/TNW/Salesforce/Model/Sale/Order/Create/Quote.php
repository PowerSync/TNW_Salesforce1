<?php

class TNW_Salesforce_Model_Sale_Order_Create_Quote extends Mage_Sales_Model_Quote
{
    /**
     * Retrieve quote address collection
     *
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    public function getAddressesCollection()
    {
        if (is_null($this->_addresses)) {
            $this->_addresses = Mage::getResourceModel('sales/quote_address_collection')
                ->setQuoteFilter($this->getId())
                ->setItemObjectClass(Mage::getConfig()->getModelClassName('tnw_salesforce/sale_order_create_quote_address'));

            if ($this->getId()) {
                foreach ($this->_addresses as $address) {
                    $address->setQuote($this);
                }
            }
        }

        return $this->_addresses;
    }
}