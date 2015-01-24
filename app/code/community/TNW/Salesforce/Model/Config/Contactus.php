<?php

/**
 * Class TNW_Salesforce_Model_Config_Contactus
 */
class TNW_Salesforce_Model_Config_Contactus
{
    /**
     * @var array
     */
    protected $_cache = array();

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
        if (!$this->_cache) {
            $this->_cache[] = array(
                'label' => 'No',
                'value' => '0'
            );
            if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
                $this->_cache[] = array(
                    'label' => 'Yes',
                    'value' => '1'
                );
            }
        }

        return $this->_cache;
    }
}