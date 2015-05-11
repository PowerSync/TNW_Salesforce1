<?php

class TNW_Salesforce_Block_Adminhtml_Product_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
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
        $fieldset = $form->addFieldset('product_map', array('legend' => Mage::helper('tnw_salesforce')->__('Mapping Information')));
        $formData = Mage::registry('salesforce_product_data')->getData();

        if (array_key_exists('default_value', $formData) && $formData['default_value']) {
            $locAttr = explode(" : ", $formData['local_field']);
            $formData['default_code'] = end($locAttr);
            array_pop($locAttr);
            array_push($locAttr, "field");
            $formData['local_field'] = join(" : ", $locAttr);
            Mage::registry('salesforce_product_data')->setData($formData);
        }

        $sfFields = array();
        $helper = Mage::helper('tnw_salesforce/salesforce_data');
        foreach ($helper->getAllFields('Product2') as $key => $field) {
            $sfFields[] = array(
                'value' => $key,
                'label' => $field
            );
        }

        $fieldset->addField('sf_field', 'select', array(
            'label' => Mage::helper('tnw_salesforce')->__('Salesforce Name'),
            'name' => 'sf_field',
            'after_element_html' => '<p class="note">Salesforce API field name.</p>',
            'style' => 'width:400px',
            'values' => $sfFields,
        ));

        $fieldset->addField('local_field', 'select', array(
            'label' => Mage::helper('tnw_salesforce')->__('Local Name'),
            'class' => 'required-entry',
            'after_element_html' => '<p class="note">Choose Magento field you wish to map to Salesforce API.</p>',
            'style' => 'width:400px',
            'name' => 'local_field',
            'values' => Mage::helper('tnw_salesforce/magento')->getMagentoAttributes('Product'),
        ));

        /* Custom Value */
        $fieldset = $form->addFieldset('product_map_custom', array('legend' => Mage::helper('tnw_salesforce')->__('Custom Mapping')));

        $fieldset->addField('default_code', 'text', array(
            'label' => Mage::helper('tnw_salesforce')->__('Attribute Code'),
            'after_element_html' => '<p class="note">Unique attribute code.</p>',
            'style' => 'width:400px',
            'name' => 'default_code',
        ));

        $fieldset->addField('default_value', 'text', array(
            'label' => Mage::helper('tnw_salesforce')->__('Attribute Value'),
            'after_element_html' => '<p class="note">Value to be used when Object is created</p>',
            'style' => 'width:400px',
            'name' => 'default_value',
        ));

        if (Mage::getSingleton('adminhtml/session')->getProductData()) {
            $form->setValues(Mage::getSingleton('adminhtml/session')->getProductData());
            Mage::getSingleton('adminhtml/session')->getProductData(null);
        } elseif (Mage::registry('salesforce_product_data')) {
            $form->setValues(Mage::registry('salesforce_product_data')->getData());
        }
        return parent::_prepareForm();
    }

}
