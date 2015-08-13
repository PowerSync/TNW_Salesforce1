<?php

/**
 * drop down list
 *
 * Class TNW_Salesforce_Model_Config_Orderbundle
 */
class TNW_Salesforce_Model_Config_Order_Bundleitem
{
    /**
     * @var array
     */
    protected $_bundleItemSyncOption = array();

    /**
     * Drop down list method
     *
     * @return array
     */
    public function toOptionArray()
    {
        $this->_bundleItemSyncOption[0] = 'Bundled product Only';
        $this->_bundleItemSyncOption[1] = 'Bundled product including all products from the bundle';
        return $this->_bundleItemSyncOption;
    }
}