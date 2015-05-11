<?php

class TNW_Salesforce_Block_Adminhtml_Build extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return Mage::helper('tnw_salesforce')->getExtensionVersion();
    }
}