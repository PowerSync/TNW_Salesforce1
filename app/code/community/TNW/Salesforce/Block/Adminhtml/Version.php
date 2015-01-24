<?php

class TNW_Salesforce_Block_Adminhtml_Version extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $_return = 'Professional';
        if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
            $_return = 'Enterprise';
        }
        return $_return;
    }

}