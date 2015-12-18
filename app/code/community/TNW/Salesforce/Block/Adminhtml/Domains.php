<?php

class TNW_Salesforce_Block_Adminhtml_Domains
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return sprintf('<div style=\'background: url("%s") no-repeat scroll 2px 0; padding-left: 23px;\'>%s</div>',
            Mage::getDesign()->getSkinUrl('images/warning_msg_icon.gif'),
            Mage::helper('catalog')->__('This functionality has moved <a href="%s">here</a>.', $this->getUrl('adminhtml/salesforce_account_matching')));
    }
}
