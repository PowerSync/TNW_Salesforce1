<?php

class TNW_Salesforce_Block_Adminhtml_Salesforceversion extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $versions = Mage::helper('tnw_salesforce/data')->getSalesforcePackagesVersion();
        $versions = nl2br($versions);

        return $versions;
    }
}