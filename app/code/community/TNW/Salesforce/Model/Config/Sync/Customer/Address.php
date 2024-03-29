<?php
/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Model_Config_Sync_Customer_Address
 */

class TNW_Salesforce_Model_Config_Sync_Customer_Address
{
    /**
     * @comment possible values
     */
    const SAVED_DATA_ONLY = 0;
    const ORDER_ADDRESS = 1;

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
                'label' => Mage::helper('tnw_salesforce')->__('Guest and Saved Addresses only'),
                'value' => self::SAVED_DATA_ONLY
            );

            $this->_options[] = array(
                'label' => Mage::helper('tnw_salesforce')->__('Use address info from the order'),
                'value' => self::ORDER_ADDRESS
            );
        }

        return $this->_options;
    }

}
