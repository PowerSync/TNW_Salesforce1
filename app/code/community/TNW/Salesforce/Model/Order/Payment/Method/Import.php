<?php

class TNW_Salesforce_Model_Order_Payment_Method_Import extends Mage_Payment_Model_Method_Abstract
{

    protected $_canUseCheckout         = false;
    protected $_canUseForMultishipping = false;

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code  = 'tnw_import';

    /**
     * Get instructions text from config
     *
     * @return string
     */
    public function getInstructions()
    {
        return trim($this->getConfigData('instructions'));
    }

    /**
     * Check whether payment method can be used
     *
     * TODO: payment method instance is not supposed to know about quote
     *
     * @param Mage_Sales_Model_Quote|null $quote
     *
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        return true;
    }

    /**
     * Validate payment method information object
     *
     * @return $this
     */
    public function validate()
    {
        return $this;
    }
}