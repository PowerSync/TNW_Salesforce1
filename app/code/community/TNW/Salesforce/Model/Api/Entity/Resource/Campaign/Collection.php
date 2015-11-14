<?php

class TNW_Salesforce_Model_Api_Entity_Resource_Campaign_Collection
    extends TNW_Salesforce_Model_Api_Entity_Resource_Collection_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce_api_entity/campaign');
    }

    /**
     * Convert items array to array for select options
     */
    protected function _toOptionArray($valueField = 'Id', $labelField = 'Name', $additional = array())
    {
        if (Mage::helper('tnw_salesforce/config_sales')->useProductCampaignAssignment()) {
            return parent::_toOptionArray($valueField, $labelField, $additional);
        } else {
            return  array(
                'value' => '',
                'label' => ''
            );
        }
    }



    /**
     * Get a text for option value
     *
     * @param  string|int $value
     * @return string|bool
     */
    public function getOptionText($value)
    {
        $options = $this->getAllOptions();
        // Fixed for tax_class_id and custom_design
        if (sizeof($options) > 0) foreach($options as $option) {
            if (isset($option['value']) && $option['value'] == $value) {
                return isset($option['label']) ? $option['label'] : $option['value'];
            }
        } // End
        if (isset($options[$value])) {
            return $options[$value];
        }
        return false;
    }

    /**
     * Returns Id to save in DB
     * @param $value
     * @return null
     */
    public function getOptionId($value)
    {
        foreach ($this->getAllOptions() as $option) {
            if (strcasecmp($option['label'], $value)==0 || $option['value'] == $value) {
                return $option['value'];
            }
        }
        return null;
    }

}