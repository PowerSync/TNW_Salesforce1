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

    /**
     * Assign customer model to quote with billing and shipping address change
     *
     * @param  Mage_Customer_Model_Customer    $customer
     * @param  Mage_Sales_Model_Quote_Address  $billingAddress
     * @param  Mage_Sales_Model_Quote_Address  $shippingAddress
     * @return Mage_Sales_Model_Quote
     */
    public function assignCustomerWithAddressChange(
        Mage_Customer_Model_Customer    $customer,
        Mage_Sales_Model_Quote_Address  $billingAddress  = null,
        Mage_Sales_Model_Quote_Address  $shippingAddress = null
    )
    {
        if ($customer->getId()) {
            $this->setCustomer($customer);

            if (!is_null($billingAddress)) {
                $this->setBillingAddress($billingAddress);
            } else {
                $defaultBillingAddress = $customer->getDefaultBillingAddress();
                if ($defaultBillingAddress && $defaultBillingAddress->getId()) {
                    $billingAddress = Mage::getModel('tnw_salesforce/sale_order_create_quote_address')
                        ->importCustomerAddress($defaultBillingAddress);
                    $this->setBillingAddress($billingAddress);
                }
            }

            if (is_null($shippingAddress)) {
                $defaultShippingAddress = $customer->getDefaultShippingAddress();
                if ($defaultShippingAddress && $defaultShippingAddress->getId()) {
                    $shippingAddress = Mage::getModel('tnw_salesforce/sale_order_create_quote_address')
                        ->importCustomerAddress($defaultShippingAddress);
                } else {
                    $shippingAddress = Mage::getModel('tnw_salesforce/sale_order_create_quote_address');
                }
            }
            $this->setShippingAddress($shippingAddress);
        }

        return $this;
    }

    /**
     * Retrieve quote address by type
     *
     * @param   string $type
     * @return  Mage_Sales_Model_Quote_Address
     */
    protected function _getAddressByType($type)
    {
        foreach ($this->getAddressesCollection() as $address) {
            if ($address->getAddressType() == $type && !$address->isDeleted()) {
                return $address;
            }
        }

        $address = Mage::getModel('tnw_salesforce/sale_order_create_quote_address')->setAddressType($type);
        $this->addAddress($address);
        return $address;
    }
}