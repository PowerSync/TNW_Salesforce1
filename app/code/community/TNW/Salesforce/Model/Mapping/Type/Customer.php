<?php

class TNW_Salesforce_Model_Mapping_Type_Customer extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Customer';

    /**
     * @param $_entity Mage_Customer_Model_Customer
     * @return string
     */
    protected function _prepareValue($_entity)
    {
        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attributeCode) {
            case 'website_id':
                return $this->convertWebsite($_entity);

            case 'sf_email_opt_out':
                return $this->convertEmailOptOut($_entity);

            case 'sf_record_type':
                return $this->convertSfRecordType($_entity);

            case 'sf_company':
                return $this->convertSfCompany($_entity);

            case 'id':
                $value = $_entity->getId();
                /**
                 * skip guest id
                 */
                if (!is_numeric($value)) {
                    return null;
                }
                break;

            case 'salesforce_contact_owner_id':
                return $this->convertSalesforceContactOwnerId($_entity);

            case 'salesforce_account_owner_id':
                return $this->convertSalesforceAccountOwnerId($_entity);

            case 'salesforce_lead_owner_id':
                return $this->convertSalesforceLeadOwnerId($_entity);

            case 'disable_auto_group_change':
                return $_entity->getData($attributeCode);
        }

        return parent::_prepareValue($_entity);
    }

    /**
     * @param $_entity Mage_Customer_Model_Customer
     * @param $value
     * @return mixed
     */
    protected function _prepareReverseValue($_entity, $value)
    {
        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attributeCode) {
            case 'website_id':
                return $this->reverseConvertWebsite($value);

            case 'website_ids':
                return $this->reverseConvertWebsiteIds($value);
        }

        return parent::_prepareReverseValue($_entity, $value);
    }

    /**
     * @param Mage_Customer_Model_Customer $_entity
     * @return string
     * @throws Exception
     */
    public function convertWebsite($_entity)
    {
        return Mage::helper('tnw_salesforce/magento_websites')
            ->getWebsiteSfId($this->getWebsiteId($_entity));
    }

    /**
     * @param Mage_Customer_Model_Customer $entity
     * @return string
     */
    public function getWebsiteId($entity)
    {
        $websiteId = $entity->getData('website_id');
        if (null === $websiteId) {
            $websiteId = Mage::app()->getWebsite('admin')->getId();
        }

        return $websiteId;
    }

    /**
     * @param $value
     * @return mixed|null
     */
    public function reverseConvertWebsite($value)
    {
        /** @var Mage_Core_Model_Website $website */
        foreach (Mage::app()->getWebsites(true) as $website) {
            if ($website->getData('salesforce_id') !== $value) {
                continue;
            }

            return $website->getId();
        }

        return null;
    }

    /**
     * @param $value
     * @return array
     */
    public function reverseConvertWebsiteIds($value)
    {
        return explode(',', $value);
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
     * @throws Exception
     */
    public function convertSfRecordType($_entity)
    {
        $currentHelper = $this->getHelperInstance('tnw_salesforce/salesforce_customer');
        if (
            $currentHelper instanceof TNW_Salesforce_Helper_Salesforce_Customer
            && isset($currentHelper->_cache['contactsLookup'][$this->convertWebsite($_entity)][$currentHelper->getEntityNumber($_entity)])
        ) {
            return Mage::helper('tnw_salesforce')->getBusinessAccountRecordType();
        }

        $customerConfig = Mage::helper('tnw_salesforce');
        switch ($customerConfig->customerTypeRecordType()) {
            case TNW_Salesforce_Model_Config_Account_Recordtypes::B2B_ACCOUNT:
                return $customerConfig->getBusinessAccountRecordType();

            case TNW_Salesforce_Model_Config_Account_Recordtypes::B2C_ACCOUNT:
                return $customerConfig->getPersonAccountRecordType();

            default:
                $_companyFill = $_entity->getDefaultBillingAddress()
                    && $_entity->getDefaultBillingAddress()->getData('company');

                if ($_companyFill) {
                    return $customerConfig->getBusinessAccountRecordType();
                }

                return $customerConfig->getPersonAccountRecordType();
        }
    }

    /**
     * @param Mage_Customer_Model_Customer $_entity
     * @return string
     */
    public function convertSfCompany($_entity)
    {
        return self::companyByCustomer($_entity);
    }

    /**
     * @param Mage_Customer_Model_Customer $_entity
     * @return string
     */
    static public function companyByCustomer($_entity)
    {
        $company = self::getCompanyByCustomer($_entity);
        return empty($company)
            ? self::generateCompanyByCustomer($_entity)
            : $company;
    }

    /**
     * @param Mage_Customer_Model_Customer $_entity
     * @return string
     */
    static public function getCompanyByCustomer($_entity)
    {
        $companyName = $_entity->getCompany();

        if (empty($companyName)) {
            $address = $_entity->getDefaultBillingAddress();
            if ($address instanceof Mage_Customer_Model_Address_Abstract) {
                $companyName = $address->getCompany();
            }
        }

        if (empty($companyName)) {
            $address = $_entity->getBillingAddress();
            if ($address instanceof Mage_Customer_Model_Address_Abstract) {
                $companyName = $address->getCompany();
            }
        }

        return trim($companyName);
    }

    /**
     * @param Mage_Customer_Model_Customer $_entity
     * @return string
     */
    static public function generateCompanyByCustomer($_entity)
    {
        return trim(sprintf('%s %s', trim($_entity->getFirstname()), trim($_entity->getLastname())));
    }

    /**
     * @param Mage_Customer_Model_Customer $_entity
     * @return string
     */
    public function convertSalesforceContactOwnerId($_entity)
    {
        $availableOwners = array();

        $result = null;

        /**
         * Owner already assigned
         */
        $availableOwners[] = $_entity->getData('salesforce_contact_owner_id');

        if (Mage::helper('tnw_salesforce/config_customer')->useAccountOwner()) {

            $currentHelper = $this->getHelperInstance('tnw_salesforce/salesforce_customer');

            if (!empty($currentHelper)) {

                /**
                 * Account owner prepared to push
                 */
                if (isset($currentHelper->_cache['accountsToUpsert']['Id'][$currentHelper->getEntityId($_entity)]->OwnerId)) {
                    /**
                     * Account owner
                     */
                    $availableOwners[] = $currentHelper->_cache['accountsToUpsert']['Id'][$currentHelper->getEntityId($_entity)]->OwnerId;
                    /**
                     * if no data in ToUpsert - try to find it in lookup
                     */
                } elseif (isset($currentHelper->_cache['accountLookup'][0][$currentHelper->getEntityNumber($_entity)]->OwnerId)) {
                    $availableOwners[] = $currentHelper->_cache['accountLookup'][0][$currentHelper->getEntityNumber($_entity)]->OwnerId;
                }
            }
        }

        /**
         * Default owner
         */
        $availableOwners[] = Mage::helper('tnw_salesforce')->getDefaultOwner();

        $result = $this->getFirstAvailableOwner($availableOwners);

        return $result;
    }

    /**
     * @param Mage_Customer_Model_Customer $_entity
     * @return string
     * @throws Exception
     */
    public function convertSalesforceAccountOwnerId($_entity)
    {
        $result = null;

        /**
         * Owner already assigned
         */
        $availableOwners[] = $_entity->getData('salesforce_account_owner_id');

        $currentHelper = $this->getHelperInstance('tnw_salesforce/salesforce_customer');

        if (!empty($currentHelper)) {

            $websiteKey = $this->convertWebsite($_entity);

            /**
             * Lead owner prepared to push
             */
            if (isset($currentHelper->_cache['leadsToUpsert']['Id'][$currentHelper->getEntityId($_entity)]->OwnerId)) {
                $availableOwners[] = $currentHelper->_cache['leadsToUpsert']['Id'][$currentHelper->getEntityId($_entity)]->OwnerId;

                /**
                 * if no data in ToUpsert - try to find it in lookup
                 */
            } elseif (isset($currentHelper->_cache['leadLookup'][$websiteKey][$currentHelper->getEntityNumber($_entity)]->OwnerId)) {
                $availableOwners[] = $currentHelper->_cache['leadLookup'][$websiteKey][$currentHelper->getEntityNumber($_entity)]->OwnerId;
            }
        }

        /**
         * Default owner
         */
        $availableOwners[] = Mage::helper('tnw_salesforce')->getDefaultOwner();

        $result = $this->getFirstAvailableOwner($availableOwners);

        return $result;
    }

    /**
     * @param Mage_Customer_Model_Customer $_entity
     * @return string
     */
    public function convertSalesforceLeadOwnerId($_entity)
    {
        $availableOwners[] = $_entity->getData('salesforce_lead_owner_id');
        $availableOwners[] = Mage::helper('tnw_salesforce')->getLeadDefaultOwner();

        $result = $this->getFirstAvailableOwner($availableOwners);

        return $result;
    }
}