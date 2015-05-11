<?php

/**
 * @method string getLocalField
 * @method string getSfField
 * @method string getAttributeId
 * @method string getBackendType
 * @method string getSfObject
 * @method string getDefaultValue
 *
 * Class TNW_Salesforce_Model_Mapping
 */
class TNW_Salesforce_Model_Mapping extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        parent::_construct();

        $this->_init('tnw_salesforce/mapping');
    }
}