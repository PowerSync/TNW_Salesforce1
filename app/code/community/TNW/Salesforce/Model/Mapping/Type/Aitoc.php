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
     * return the fake Attribute for Aitoc
     * @param Mage_Core_Model_Abstract $_entity
     * @param $attributeCode
     * @return $attributeCode
     */
    protected function _getAttribute($_entity, $attributeCode)
    {
        $attributeArray = array();
        foreach ($_entity as $_type => $_object) {
            if (is_object($_entity[$_type]) && is_array($_entity[$_type]->getData())) {
                $aitocValueCollection = $_entity[$_type];
                $attributeCode = $this->_mapping->getLocalFieldAttributeCode();

                foreach ($aitocValueCollection->getData() as $_key => $_data) {
                    if ($_data['code'] == $attributeCode) {
                        $attributeArray = $_data;
                        break;
                    }
                }
                if ($attributeArray) {
                    break;
                }
            }
        }
        $attribute = new Varien_Object();
        $attribute->setData($attributeArray);
        $attribute->setFrontend(new Varien_Object(array('frontend_input' => $attribute->getType())));

        return $attribute;
    }
}