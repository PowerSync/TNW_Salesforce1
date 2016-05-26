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

            case 'sf_email_opt_out':
                return $this->convertEmailOptOut($_entity);

            case 'sf_record_type':
                return $this->convertSfRecordType($_entity);
        }

        $attribute = $this->_getAttribute($_entity, $attributeCode);
        if ($attribute) {
            if($_entity->hasData($attributeCode)) {
                return $this->_convertValueForAttribute($_entity, $attribute);
            }

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice(sprintf('Attribute customer "%s" is missing. Customer email: "%s"', $attributeCode, $_entity->getEmail()));
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
        $_website = Mage::app()
            ->getWebsite($_entity->getWebsiteId());

        return $websiteHelper->getWebsiteSfId($_website);
    }

    /**
     * @param Mage_Customer_Model_Customer $_entity
     * @return string
     */
    public function convertEmailOptOut($_entity)
    {
        /** @var Mage_Newsletter_Model_Subscriber $subscriber */
        $subscriber = Mage::getModel('newsletter/subscriber')
            ->loadByCustomer($_entity);
        return (int)!$subscriber->isSubscribed();
    }

    /**
     * @param Mage_Customer_Model_Customer $_entity
     * @return string
     */
    public function convertSfRecordType($_entity)
    {
        $_websiteId = $_entity->getData('website_id');
        $_forceRecordType = Mage::app()->getWebsite($_websiteId)
            ->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_FORCE_RECORDTYPE);

        switch($_forceRecordType) {
            case TNW_Salesforce_Model_Config_Account_Recordtypes::B2B_ACCOUNT:
                return Mage::app()->getWebsite($_websiteId)
                    ->getConfig(TNW_Salesforce_Helper_Data::BUSINESS_RECORD_TYPE);

            case TNW_Salesforce_Model_Config_Account_Recordtypes::B2C_ACCOUNT:
                return Mage::app()->getWebsite($_websiteId)
                    ->getConfig(TNW_Salesforce_Helper_Data::PERSON_RECORD_TYPE);

            default:
                $_companyFill = $_entity->getDefaultBillingAddress()
                    && $_entity->getDefaultBillingAddress()->getData('company');

                return Mage::app()->getWebsite($_websiteId)
                    ->getConfig(($_companyFill)
                        ? TNW_Salesforce_Helper_Data::BUSINESS_RECORD_TYPE
                        : TNW_Salesforce_Helper_Data::PERSON_RECORD_TYPE
                    );
        }
    }
}