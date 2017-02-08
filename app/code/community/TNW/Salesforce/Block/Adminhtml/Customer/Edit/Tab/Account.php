<?php

class TNW_Salesforce_Block_Adminhtml_Customer_Edit_Tab_Account extends Mage_Adminhtml_Block_Customer_Edit_Tab_Account
{
    public function initForm()
    {
        parent::initForm();

        /** @var Varien_Data_Form_Element_Fieldset $baseFieldSet */
        $baseFieldSet = $this->getForm()->getElement('base_fieldset');
        if (!$baseFieldSet) {
            return $this;
        }

        $baseFieldSet->removeField('salesforce_contact_owner_id');
        $baseFieldSet->removeField('salesforce_account_owner_id');
        $baseFieldSet->removeField('salesforce_lead_owner_id');

        if (Mage::helper('tnw_salesforce')->isEnabled() && Mage::helper('tnw_salesforce')->isEnabledCustomerSync()) {
            /** @var Mage_Customer_Model_Customer $customer */
            $customer = Mage::registry('current_customer');

            $attributeName = 'salesforce_sales_person';
            if ($customer->getData('salesforce_id')) {
                $attributeName = $customer->getData('salesforce_is_person')
                    ? 'salesforce_account_owner_id' : 'salesforce_contact_owner_id';
            } elseif ($customer->getData('salesforce_lead_id')) {
                $attributeName = 'salesforce_lead_owner_id';
            }

            $baseFieldSet->addType('owner', Mage::getConfig()->getBlockClassName('tnw_salesforce/adminhtml_widget_form_element_owner'));
            $baseFieldSet->addField($attributeName, 'owner', array(
                'label'    => Mage::helper('customer')->__('Sales Person'),
                'name'     => $attributeName,
                'selector' => 'tnw-sales-person',
                'value'    => $customer->getData($attributeName)
            ));
        }
        return $this;
    }
}