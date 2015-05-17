<?php

class TNW_Salesforce_Block_Adminhtml_Abandoned_Opportunity_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form(array(
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', array('mapping_id' => $this->getRequest()->getParam('mapping_id'))),
            'method' => 'post',
            'enctype' => 'multipart/form-data'
        ));
        $form->setUseContainer(true);
        $this->setForm($form);
        $fieldset = $form->addFieldset('opportunity_map', array('legend' => $this->__('Mapping Information')));
        $formData = Mage::registry('salesforce_opportunity_data')->getData();

        if (array_key_exists('default_value', $formData) && $formData['default_value']) {
            $locAttr = explode(" : ", $formData['local_field']);
            $formData['default_code'] = end($locAttr);
            array_pop($locAttr);
            array_push($locAttr, "field");
            $formData['local_field'] = join(" : ", $locAttr);
            Mage::registry('salesforce_opportunity_data')->setData($formData);
        }

        $sfFields = array();
        try {
            $helper = Mage::helper('tnw_salesforce/salesforce_data');
            foreach ($helper->getAllFields('Opportunity') as $key => $field) {
                $sfFields[] = array(
                    'value' => $key,
                    'label' => $field
                );
            }
        } catch (Exception $e) {
            $sfFields[] = array(
                'value' => '',
                'label' => $this->__('Could not retrieve Salesforce fields'),
            );
        }


        $fieldset->addField('sf_field', 'select', array(
            'label' => $this->__('Salesforce Name'),
            'name' => 'sf_field',
            'note' => $this->__('Salesforce API field name.'),
            'style' => 'width:400px',
            'values' => $sfFields,
            'class' => 'chosen-select',
        ));

        $mageFields = array();
        try {
            $mageFields = Mage::helper('tnw_salesforce/magento')->getMagentoAttributes(TNW_Salesforce_Model_Config_Objects::ABANDONED_OBJECT);
        } catch (Exception $e) {
            $mageFields[] = array(
                'value' => '',
                'label' => $this->__('Could not retrieve Magento fields'),
            );
        }

        $fieldset->addField('local_field', 'select', array(
            'label' => $this->__('Local Name'),
            'class' => 'required-entry chosen-select',
            'note' => $this->__('Choose Magento field you wish to map to Salesforce API.'),
            'style' => 'width:400px',
            'name' => 'local_field',
            'values' => $mageFields,
        ));

        /* Custom Value */
        $fieldset = $form->addFieldset('opportunity_map_custom', array('legend' => $this->__('Custom Mapping')));

        $fieldset->addField('default_code', 'text', array(
            'label' => $this->__('Attribute Code'),
            'note' => $this->__('Unique attribute code.'),
            'style' => 'width:400px',
            'name' => 'default_code',
        ));

        $fieldset->addField('default_value', 'text', array(
            'label' => $this->__('Attribute Value'),
            'note' => $this->__('Value to be used when Object is created'),
            'style' => 'width:400px',
            'name' => 'default_value',
        ));

        if (Mage::getSingleton('adminhtml/session')->getOpportunityData()) {
            $form->setValues(Mage::getSingleton('adminhtml/session')->getOpportunityData());
            Mage::getSingleton('adminhtml/session')->getOpportunityData(null);
        } elseif (Mage::registry('salesforce_opportunity_data')) {
            $form->setValues(Mage::registry('salesforce_opportunity_data')->getData());
        }
        return parent::_prepareForm();
    }

}
