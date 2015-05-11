<?php
/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 27.04.15
 * Time: 14:45
 *
 * Class TNW_Salesforce_Model_Config_Sync_Currency
 */
class TNW_Salesforce_Model_Config_Sync_Currency
{
    /**
     * @comment possible values
     */
    const BASE_CURRENCY = 'base';
    const CUSTOMER_CURRENCY = 'customer';

    /**
     * @var array
     */
    protected $_options = array();

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
        if (!$this->_options) {
            $this->_options[] = array(
                'label' => Mage::helper('tnw_salesforce')->__('Currency selected by the customer'),
                'value' => self::CUSTOMER_CURRENCY
            );

            $this->_options[] = array(
                'label' => Mage::helper('tnw_salesforce')->__('Base store currency'),
                'value' => self::BASE_CURRENCY
            );
        }

        return $this->_options;
    }

}