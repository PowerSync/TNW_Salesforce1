<?php

abstract class TNW_Salesforce_Model_Mapping_Type_Abstract
{
    /**
     * @var TNW_Salesforce_Model_Mapping
     */
    protected $_mapping = null;

    /**
     * @param $_entity Mage_Core_Model_Abstract
     * @return string
     */
    public function getValue($_entity)
    {
        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();

        $method = 'get' . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
        $value = call_user_func(array($_entity, $method));

        if (is_array($value)) {
            $value = implode(' ', $value);
        } elseif ($this->_mapping->getBackendType() == 'datetime' || $this->_mapping->getBackendType() == 'timestamp' || $attributeCode == 'created_at') {
            $value = gmdate(DATE_ATOM, strtotime($value));
        } else {
            //check if get option text required
            if (is_object($_entity->getResource()) && method_exists($_entity->getResource(), 'getAttribute')
                && is_object($_entity->getResource()->getAttribute($attributeCode))
                && $_entity->getResource()->getAttribute($attributeCode)->getFrontendInput() == 'select'
            ) {
                $value = $_entity->getResource()->getAttribute($attributeCode)->getSource()->getOptionText($value);
            }
        }

        return $value;
    }

    /**
     * @param $_mapping TNW_Salesforce_Model_Mapping
     * @return $this
     */
    public function setMapping($_mapping)
    {
        $this->_mapping = $_mapping;
        return $this;
    }
}