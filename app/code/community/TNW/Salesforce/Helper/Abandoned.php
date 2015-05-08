<?php

/**
 * Class TNW_Salesforce_Helper_Abandoned
 */
class TNW_Salesforce_Helper_Abandoned extends TNW_Salesforce_Helper_Abstract
{
    protected $_limits = array();
    protected $_limitsHash = array();

    /* Abandoned Cart */

    const ABANDONED_CLOSE_TIME_AFTER = 'salesforce_order/customer_opportunity/abandoned_close_time_after';
    const ABANDONED_CART_ENABLED = 'salesforce_order/customer_opportunity/abandoned_cart_enabled';
    const DEFAULT_STATE_ABANDONED = 'salesforce_order/customer_opportunity/abandoned_cart_state';
    const ABANDONED_CUSTOMER_ROLE_ENABLED = 'salesforce_order/customer_opportunity/customer_opportunity_role_enable';
    const ABANDONED_SYNC = 'salesforce_order/customer_opportunity/abandoned_cart_limit';
    const ABANDONED_CUSTOMER_ROLE = 'salesforce_order/customer_opportunity/abandoned_customer_integration_opp';

    const NOW = -1;
    const THREE_HOURS = 1;
    const SIX_HOURS = 2;
    const TWELVE_HOURS = 3;
    const ONE_DAY = 4;
    const THREE_DAYS = 5;
    const ONE_WEEK = 6;
    const TWO_WEEKS = 7;
    const ONE_MONTH = 8;

    const ABANDONED_CART_ID_PREFIX = 'ac_';

    function __construct()
    {
        $this->_limits = array(
            array(
                'value' => self::NOW,
                'label' => Mage::helper('adminhtml')->__('Now')
            ),
            array(
                'value' => self::THREE_HOURS,
                'label' => Mage::helper('adminhtml')->__('3 hours')
            ),
            array(
                'value' => self::SIX_HOURS,
                'label' => Mage::helper('adminhtml')->__('6 hours')
            ),
            array(
                'value' => self::TWELVE_HOURS,
                'label' => Mage::helper('adminhtml')->__('12 hours')
            ),
            array(
                'value' => self::ONE_DAY,
                'label' => Mage::helper('adminhtml')->__('1 day')
            ),
            array(
                'value' => self::THREE_DAYS,
                'label' => Mage::helper('adminhtml')->__('3 days')
            ),
            array(
                'value' => self::ONE_WEEK,
                'label' => Mage::helper('adminhtml')->__('1 week')
            ),
            array(
                'value' => self::TWO_WEEKS,
                'label' => Mage::helper('adminhtml')->__('2 weeks')
            ),
            array(
                'value' => self::ONE_MONTH,
                'label' => Mage::helper('adminhtml')->__('1 month')
            ),
        );
    }

    public function isEnabled()
    {
        return
            Mage::helper('tnw_salesforce')->getType() == "PRO"
        && $this->getStroreConfig(TNW_Salesforce_Helper_Abandoned::ABANDONED_CART_ENABLED);
    }

    // is Customer Opportunity Role Enabled
    public function isEnabledCustomerRole()
    {
        return $this->getStroreConfig(self::ABANDONED_CUSTOMER_ROLE_ENABLED);
    }


    // Default Customer Opportunity Role
    public function getDefaultCustomerRole()
    {
        return $this->getStroreConfig(self::ABANDONED_CUSTOMER_ROLE);
    }


    // Default Stage name for abandoned cart
    public function getDefaultAbandonedCartStageName() {
        return $this->getStroreConfig(self::DEFAULT_STATE_ABANDONED);
    }

    public function getLimits()
    {
        return $this->_limits;
    }


    public function setLimits(array $limits)
    {
        return $this->_limits = $limits;
    }

    public function getLimitsHash()
    {
        if (!$this->_limitsHash) {
            foreach ($this->getLimits() as $limit) {
                $this->_limitsHash[$limit['value']] = $limit['label'];
            }
        }

        return $this->_limitsHash;
    }


    public function getAbandonedConfigLimit()
    {
        return $this->getStroreConfig(self::ABANDONED_SYNC);
    }

    /**
     * @return Zend_Date
     */
    public function getDateLimit()
    {
        /**
         * @var $currentDate Zend_Date
         */
        $currentDate = Mage::app()->getLocale()->utcDate(null, Zend_Date::now());

        switch($this->getAbandonedConfigLimit()) {
            case self::NOW:
                break;
            case self::THREE_HOURS:
                $currentDate->subHour(3);
                break;
            case self::SIX_HOURS:
                $currentDate->subHour(6);
                break;
            case self::TWELVE_HOURS:
                $currentDate->subHour(12);
                break;
            case self::ONE_DAY:
                $currentDate->subDay(1);
                break;
            case self::THREE_DAYS:
                $currentDate->subDay(3);
                break;
            case self::ONE_WEEK:
                $currentDate->subWeek(1);
                break;
            case self::TWO_WEEKS:
                $currentDate->subWeek(2);
                break;
            case self::ONE_MONTH:
                $currentDate->subMonth(1);
                break;
        }

        return $currentDate;
    }

    /**
     * @param $quote Mage_Sales_Model_Quote
     * @return mixed
     */
    public function getAbandonedCloseTimeAfter($quote)
    {
        return Mage::getStoreConfig(self::ABANDONED_CLOSE_TIME_AFTER, $quote->getStoreId());
    }
}