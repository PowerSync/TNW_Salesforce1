<?php

/**
 * Created by PhpStorm.
 * User: evgeniy
 * Date: 05.02.15
 * Time: 0:42
 */
class TNW_Salesforce_Model_Config_Abandoned_Cart_Limit
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return Mage::helper('tnw_salesforce/abandoned')->getLimits();
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return Mage::helper('tnw_salesforce/abandoned')->getLimitsHash();;
    }

}