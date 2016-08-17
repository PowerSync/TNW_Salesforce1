<?php

class TNW_Salesforce_Block_Adminhtml_System_Config_Form_Field_Visible extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    static public function isVisible(Varien_Data_Form_Element_Abstract $element)
    {
        $extendedDepends = $element->getData('field_config')->extended_depends;
        if (!empty($extendedDepends)) {
            $visible = true;
            foreach ($extendedDepends->children() as $dependent) {
                if (empty($dependent->fieldset) || empty($dependent->section)) {
                    continue;
                }

                $path = $dependent->section
                    . '/' . $dependent->fieldset
                    . '/' . $dependent->getName();

                $visible = $visible && (Mage::getStoreConfig($path) == $dependent->value);
            }

            return $visible;
        }

        return false;
    }

    /**
     * Enter description here...
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        if (!self::isVisible($element)) {
            return '';
        }

        return parent::render($element);
    }
}
