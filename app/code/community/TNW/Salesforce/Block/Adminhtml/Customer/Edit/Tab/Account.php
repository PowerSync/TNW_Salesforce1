<?php

class TNW_Salesforce_Block_Adminhtml_Customer_Edit_Tab_Account extends Mage_Adminhtml_Block_Customer_Edit_Tab_Account
{
    protected $fields = array(
        'salesforce_id'         => 'salesforce_contact_owner_id',
        'salesforce_account_id' => 'salesforce_account_owner_id',
        'salesforce_lead_id'    => 'salesforce_lead_owner_id'
    );

    public function initForm()
    {
        parent::initForm();

        /** @var Varien_Data_Form_Element_Fieldset $baseFieldset */
        $baseFieldset = $this->getForm()->getElement('base_fieldset');
        if (!$baseFieldset) {
            return $this;
        }

        if (!Mage::helper('tnw_salesforce')->isEnabled() || !Mage::helper('tnw_salesforce')->isEnabledCustomerSync()) {
            foreach ($this->fields as $field) {
                $baseFieldset->removeField($field);
            }
        }
        else {
            /** @var Mage_Customer_Model_Customer $customer */
            $customer = Mage::registry('current_customer');

            foreach ($this->fields as $check => $field) {
                if (!$customer->getData($check)) {
                    $baseFieldset->removeField($field);
                }

                /** @var Varien_Data_Form_Element_Text $element */
                $element = $this->getForm()->getElement($field);
                if (!$element) {
                    continue;
                }

                $element->setData('selector', 'tnw_field_'.$field);
            }
        }

        return $this;
    }

    /**
     * @param Mage_Customer_Model_Attribute[] $attributes
     * @param Varien_Data_Form_Element_Fieldset $fieldset
     * @param array $exclude
     */
    protected function _setFieldset($attributes, $fieldset, $exclude = array())
    {
        foreach ($this->fields as $field) {
            if (empty($attributes[$field])) {
                continue;
            }

            $attributes[$field]
                ->setData('frontend_input_renderer', 'tnw_salesforce/adminhtml_widget_form_element_owner');
        }

        parent::_setFieldset($attributes, $fieldset, $exclude);
    }


}