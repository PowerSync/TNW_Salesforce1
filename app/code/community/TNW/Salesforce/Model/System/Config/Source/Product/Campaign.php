<?php

class TNW_Salesforce_Model_System_Config_Source_Product_Campaign extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    /**
     * Retrieve All options
     *
     * @return array
     */
    public function getAllOptions()
    {
        if (!Mage::helper('tnw_salesforce/config_sales')->useProductCampaignAssignment()) {
            return array(array(
                'value' => '',
                'label' => ''
            ));
        }

        /** @var TNW_Salesforce_Model_Api_Entity_Resource_Campaign_Collection $collection */
        $collection = Mage::getResourceModel('tnw_salesforce_api_entity/campaign_collection');
        return $collection->toOptionArray();
    }
}