<?php
/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 * Date: 22.09.15
 * Time: 13:27
 */

class TNW_Salesforce_Block_Adminhtml_System_Config_Form_Additional_Condition extends Mage_Adminhtml_Block_System_Config_Form_Field
{


    /**
     * Enter description here...
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $fieldConfig = $element->getFieldConfig();
        /**
         * try to find helper condition
         */
        if ($fieldConfig->custom_depends) {
            foreach ($fieldConfig->custom_depends->children() as $dependent) {
                if (!$dependent->getName() == 'helper') {
                    continue;
                }

                $helper = Mage::helper($dependent->class);
                $method = (string)$dependent->method;
                $value = (string)$dependent->value;

                if($helper->$method() != $value) {
                    return '';
                }
            }
        }

        return parent::render($element);
    }

}