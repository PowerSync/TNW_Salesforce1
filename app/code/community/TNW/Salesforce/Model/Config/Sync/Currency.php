<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
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