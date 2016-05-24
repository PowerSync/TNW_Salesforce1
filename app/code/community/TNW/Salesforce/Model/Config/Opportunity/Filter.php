<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Opportunity_Filter
{
    const FILTER_CUSTOMER = 'customer';
    const FILTER_ACCOUNT  = 'account';

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
        $_cache[] = array(
            'label' => Mage::helper('tnw_salesforce')->__('a Customer'),
            'value' => self::FILTER_CUSTOMER
        );

        $_cache[] = array(
            'label' => Mage::helper('tnw_salesforce')->__('an Account'),
            'value' => self::FILTER_ACCOUNT
        );

        return $_cache;
    }
}