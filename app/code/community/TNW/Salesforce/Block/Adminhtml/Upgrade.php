<?php

class TNW_Salesforce_Block_Adminhtml_Upgrade extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return "Functionality is unavailable in Professional version!";
    }
}