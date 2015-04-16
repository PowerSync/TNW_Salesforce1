<?php

class TNW_Salesforce_Block_Adminhtml_Versionelement extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $_version = 'Professional';
        if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
            $_version = 'Enterprise';
        }

        $element->setValue($_version);


        return $_version . $element->getElementHtml();
    }
}