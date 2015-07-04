<?php

class TNW_Salesforce_Model_Config_Order_Object
{
    protected $_data = array();

    public function toOptionArray()
    {
        if (empty($this->_data)) {
            $this->_setOptions();
        }

        return $this->_getOptions();
    }

    protected function _setOptions() {
        $this->_data[TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_OBJECT] = TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_OBJECT;
        $this->_data[TNW_Salesforce_Model_Config_Objects::ORDER_OBJECT] = TNW_Salesforce_Model_Config_Objects::ORDER_OBJECT;
    }

    /**
     * @return array
     */
    protected function _getOptions() {
        $_result = array();
        foreach ($this->_data as $key => $value) {
            $_result[] = array(
                'value' => $key,
                'label' => $value,
            );
        }

        return $_result;
    }
}
