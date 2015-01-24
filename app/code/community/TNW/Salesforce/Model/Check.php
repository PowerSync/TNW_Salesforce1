<?php

/**
 * Class TNW_Salesforce_Model_Check
 */
class TNW_Salesforce_Model_Check extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/check');
    }
}
