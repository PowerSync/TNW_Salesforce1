<?php

class TNW_Salesforce_Model_Config_Mapping
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => TNW_Salesforce_Model_Mapping::SET_TYPE_UPSERT, 'label'=>Mage::helper('tnw_salesforce')->__('Upsert')),
            array('value' => TNW_Salesforce_Model_Mapping::SET_TYPE_INSERT, 'label'=>Mage::helper('tnw_salesforce')->__('Insert Only')),
            array('value' => TNW_Salesforce_Model_Mapping::SET_TYPE_UPDATE, 'label'=>Mage::helper('tnw_salesforce')->__('Update Only')),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            TNW_Salesforce_Model_Mapping::SET_TYPE_UPSERT => Mage::helper('tnw_salesforce')->__('Upsert'),
            TNW_Salesforce_Model_Mapping::SET_TYPE_INSERT => Mage::helper('tnw_salesforce')->__('Insert Only'),
            TNW_Salesforce_Model_Mapping::SET_TYPE_UPDATE => Mage::helper('tnw_salesforce')->__('Update Only'),
        );
    }
}