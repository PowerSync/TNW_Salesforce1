<?php

class TNW_Salesforce_Model_Mapping_Type_Customer extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Customer';

    /**
     * @param $_entity Mage_Customer_Model_Customer
     * @return string
     */
    public function getValue($_entity)
    {
        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attributeCode) {
            case 'website_id':
                return $this->convertWebsite($_entity);

            case 'email_opt_out':
                return $this->convertEmailOptOut($_entity);
        }

        /** @var mage_customer_model_resource_customer $_resource */
        $_resource = Mage::getResourceSingleton('customer/customer');
        $attribute = $_resource->getAttribute($attributeCode);
        if ($attribute)
        {
            if(!$_entity->hasData($attributeCode)) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveNotice(sprintf('Attribute customer "%s" is missing. Customer email: "%s"', $attributeCode, $_entity->getEmail()));
            }

            $_value = $_entity->getData($attributeCode);
            switch($attribute->getFrontend()->getInputType())
            {
                case 'select':
                case 'multiselect':
                    return $attribute->getSource()->getOptionText($_value);

                default:
                    return $_value;
            }
        }

        return parent::getValue($_entity);
    }

    /**
     * @param Mage_Customer_Model_Customer $_entity
     * @return string
     */
    public function convertWebsite($_entity)
    {
        /** @var tnw_salesforce_helper_magento_websites $websiteHelper */
        $websiteHelper = Mage::helper('tnw_salesforce/magento_websites');
        $_website = Mage::getModel('core/store')
            ->load($_entity->getStoreId())
            ->getWebsite();

        return $websiteHelper->getWebsiteSfId($_website);
    }

    /**
     * @param Mage_Customer_Model_Customer $_entity
     * @return string
     */
    public function convertEmailOptOut($_entity)
    {

    }
}