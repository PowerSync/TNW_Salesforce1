<?php

/**
 * Class TNW_Salesforce_Model_Sale_Order_Create
 *
 * @method TNW_Salesforce_Model_Sale_Order_Create_Session_Quote getSession()
 */
class TNW_Salesforce_Model_Sale_Order_Create extends Mage_Adminhtml_Model_Sales_Order_Create
{
    public function __construct()
    {
        $this->_session = Mage::getModel('tnw_salesforce/sale_order_create_session_quote');
    }

    /**
     * @return $this
     */
    protected function _validate()
    {
        $customerId = $this->getSession()->getCustomerId();
        if (null === $customerId && $this->getQuote()->getCustomerIsGuest()) {
            // Disable Error "Please select a customer."
            $this->getSession()->setCustomerId(0);
        }

        parent::_validate();

        $this->getSession()->setCustomerId($customerId);
        return $this;
    }
}