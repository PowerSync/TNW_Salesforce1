<?php

class TNW_Salesforce_Block_Adminhtml_Customer_Edit_Tab_Account extends Mage_Adminhtml_Block_Customer_Edit_Tab_Account
{
    public function initForm()
    {
        parent::initForm();

        if (!Mage::helper('tnw_salesforce')->isEnabled() || !Mage::helper('tnw_salesforce')->isEnabledCustomerSync()) {
            $this->getForm()->removeField('salesforce_contact_owner_id');
        }

        /** @var Varien_Data_Form_Element_Text $element */
        $element = $this->getForm()->getElement('salesforce_contact_owner_id');
        if ($element) {
            $element->setData('selector', 'sdfsdfsdf');
            /** @var TNW_Salesforce_Block_Adminhtml_Widget_Form_Renderer_Fieldset_Owner $renderer */
            $renderer = Mage::getSingleton('core/layout')
                ->createBlock('tnw_salesforce/adminhtml_widget_form_renderer_fieldset_owner');

            $element->setRenderer($renderer);
        }

        return $this;
    }
}