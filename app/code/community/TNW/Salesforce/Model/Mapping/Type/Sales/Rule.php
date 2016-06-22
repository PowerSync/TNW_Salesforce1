<?php

class TNW_Salesforce_Model_Mapping_Type_Sales_Rule extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'CatalogRule';

    /**
     * @param $_entity Mage_Sales_Model_Quote
     * @return string
     */
    protected function _prepareValue($_entity)
    {
        $attribute = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attribute) {
            case 'number':
                return $this->convertNumber($_entity);
        }

        return parent::_prepareValue($_entity);
    }

    /**
     * @param Mage_Sales_Model_Quote $_entity
     * @return string
     */
    public function convertNumber($_entity)
    {
        return 'sr_' . $_entity->getId();
    }
}