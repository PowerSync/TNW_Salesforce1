<?php

/**
 * Class TNW_Salesforce_Model_Config_No
 */
class TNW_Salesforce_Model_Config_No
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
        }

        return $this->_cache;
    }
}