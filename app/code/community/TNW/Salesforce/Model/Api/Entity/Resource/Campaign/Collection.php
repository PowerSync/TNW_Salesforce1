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


}