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

        if (Mage::helper('tnw_salesforce')->isEnabled() && Mage::helper('tnw_salesforce')->isEnabledCustomerSync()) {
            $fields = array(
                'salesforce_id'         => 'salesforce_contact_owner_id',
                'salesforce_lead_id'    => 'salesforce_lead_owner_id'
            );

            $value = '';
            /** @var Mage_Customer_Model_Customer $customer */
            $customer = Mage::registry('current_customer');
            foreach ($fields as $check => $field) {
                if (!$customer->getData($check)) {
                    continue;
                }

                /** @var Varien_Data_Form_Element_Text $element */
                $element = $this->getForm()->getElement($field);
                if (!$element) {
                    continue;
                }

                $value = $element->getValue();
                break;
            }

            $baseFieldSet->addType('owner', Mage::getConfig()->getBlockClassName('tnw_salesforce/adminhtml_widget_form_element_owner'));
            $baseFieldSet->addField('salesforce_sales_person', 'owner', array(
                'label'    => Mage::helper('customer')->__('Sales Person'),
                'name'     => 'salesforce_sales_person',
                'selector' => 'tnw-sales-person',
                'value'    => $value,
            ));
        }

        $baseFieldSet->removeField('salesforce_contact_owner_id');
        $baseFieldSet->removeField('salesforce_account_owner_id');
        $baseFieldSet->removeField('salesforce_lead_owner_id');
        return $this;
    }
}