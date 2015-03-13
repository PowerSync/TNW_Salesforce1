<?php

class TNW_Salesforce_Block_Adminhtml_Abandoned_Opportunitylineitem_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
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
        $fieldset = $form->addFieldset('opportunity_map', array('legend' => Mage::helper('tnw_salesforce')->__('Mapping Information')));
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
        $helper = Mage::helper('tnw_salesforce/salesforce_data');
        $hideFields = array(TNW_Salesforce_Helper_Salesforce::CONNECTOR_ENTERPRISE_PERFIX . 'DisableMagentoSync__c', 'UnitPrice', 'TotalPrice', 'Quantity', 'Description');
        foreach ($helper->getAllFields('OpportunityLineItem') as $key => $field) {
            if (in_array($key, $hideFields)) {
                continue;
            }
            $sfFields[] = array(
                'value' => $key,
                'label' => $field
            );
        }
        if (empty($sfFields)) {
            $sfFields[] = array(
                'value' => '',
                'label' => 'No custom fields found in Salesforce'
            );
        }

        $fieldset->addField('sf_field', 'select', array(
            'label' => Mage::helper('tnw_salesforce')->__('Salesforce Name'),
            'name' => 'sf_field',
            'after_element_html' => '<p class="note">Salesforce API field name.</p>',
            'style' => 'width:400px',
            'required' => true,
            'values' => $sfFields,
        ));

        $fieldset->addField('local_field', 'select', array(
            'label' => Mage::helper('tnw_salesforce')->__('Local Name'),
            'class' => 'required-entry',
            'after_element_html' => '<p class="note">Choose Magento field you wish to map to Salesforce API.</p>',
            'required' => true,
            'style' => 'width:400px',
            'name' => 'local_field',
            'values' => Mage::helper('tnw_salesforce/magento')->getMagentoAttributes(TNW_Salesforce_Model_Config_Objects::ABANDONED_ITEM_OBJECT),
        ));

        /* Custom Value */
        $fieldset = $form->addFieldset('opportunity_map_custom', array('legend' => Mage::helper('tnw_salesforce')->__('Custom Mapping')));

        $fieldset->addField('default_code', 'text', array(
            'label' => Mage::helper('tnw_salesforce')->__('Attribute Code'),
            //'class'     => 'required-entry',
            'after_element_html' => '<p class="note">Unique attribute code.</p>',
            //'required'  => true,
            'style' => 'width:400px',
            'name' => 'default_code',
        ));

        $fieldset->addField('default_value', 'text', array(
            'label' => Mage::helper('tnw_salesforce')->__('Attribute Value'),
            //'class'     => 'required-entry',
            'after_element_html' => '<p class="note">Value to be used when Object is created</p>',
            //'required'  => true,
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