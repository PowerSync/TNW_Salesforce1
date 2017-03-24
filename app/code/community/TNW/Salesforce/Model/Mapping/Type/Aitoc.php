<?php

class TNW_Salesforce_Model_Mapping_Type_Aitoc extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Aitoc';

    /**
     * @param $_entity
     * @return string
     */
    protected function _prepareValue($_entity)
    {
        $value = NULL;
        foreach ($_entity as $_type => $_object) {
            if (is_object($_entity[$_type]) && is_array($_entity[$_type]->getData())) {
                $value = $this->getAitocValue($_entity[$_type], $this->_mapping->getLocalFieldAttributeCode());
                if ($value) {
                    break;
                }
            }
        }

        return $value;
    }

    protected function getAitocValue($aitocValueCollection, $attributeCode)
    {
        $value = NULL;
        foreach ($aitocValueCollection->getData() as $_key => $_data) {
            if ($_data['code'] == $attributeCode) {
                $value = $_data['value'];
                if ($_data['type'] == "date") {
                    $value = date("Y-m-d", strtotime($value));
                }
                break;
            }
        }

        return $value;
    }

    /**
     * Aitoc has custom logic
     * @param $entity Mage_Core_Model_Abstract
     * @param $code
     * @return bool|Mage_Eav_Model_Entity_Attribute_Abstract
     */
    protected function _getAttribute($entity, $code)
    {
        return false;
    }
}