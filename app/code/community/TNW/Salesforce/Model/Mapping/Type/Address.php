<?php

class TNW_Salesforce_Model_Mapping_Type_Address extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    /**
     * @var array
     */
    protected $_regions = array();

    /**
     * @param $_entity Mage_Customer_Model_Address_Abstract
     * @return string
     */
    public function getValue($_entity)
    {
        $attribute = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attribute) {
            case 'region_id':
                $regions = $this->_getRegions($_entity);
                /**
                 * use state region code instead region_id to send data to Salesforce
                 */
                $_value = null;
                if (!empty($regions)) {
                    foreach ($regions as $region) {
                        if ($region->getId() == parent::getValue($_entity)) {
                            $_value = $region->getCode();
                        }
                    }
                }

                return $_value;
            case 'company':
                $_value = parent::getValue($_entity);
                /**
                 * if empty - try load company name from customer data
                 */
                if (
                    empty($_value)
                    && $_entity->getCustomer()
                ) {
                    $_value = $_entity->getCustomer()->getData($attribute);
                }
                return $_value;

        }

        return parent::getValue($_entity);
    }

    /**
     * @param $address Mage_Customer_Model_Address_Abstract
     * @return array
     */
    protected function _getRegions($address = NULL)
    {
        if ($address instanceof Varien_Object) {
            $countryId = $address->getCountryId();
        }

        if (empty($countryId)) {
            return array();
        }

        if (!$this->_regions || !isset($this->_regions[$countryId])) {
            $regionCollection = Mage::getModel('directory/region')->getCollection();
            $regionCollection->addCountryFilter($countryId);
            $this->_regions[$countryId] = $regionCollection;
        }

        return $this->_regions[$countryId];
    }
}