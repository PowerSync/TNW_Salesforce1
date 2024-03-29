<?php

class TNW_Salesforce_Block_Adminhtml_Account_Matching_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * Prepare form before rendering HTML
     *
     * @return TNW_Salesforce_Block_Adminhtml_Account_Matching_Edit_Form
     */
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form(array(
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', array('matching_id' => $this->getRequest()->getParam('matching_id'))),
            'method' => 'post',
            'enctype' => 'multipart/form-data'
        ));
        $form->setUseContainer(true);
        $this->setForm($form);

        $formValues = array();
        if (Mage::getSingleton('adminhtml/session')->getAccountData()) {
            $formValues = Mage::getSingleton('adminhtml/session')->getAccountData();
            Mage::getSingleton('adminhtml/session')->getAccountData(null);
        } elseif (Mage::registry('salesforce_account_matching_data')) {
            $formValues = Mage::registry('salesforce_account_matching_data')->getData();
        }

        $fieldset = $form->addFieldset('account_matching', array('legend' => $this->__('Rule Information')));
        $fieldset->addType('account', Mage::getConfig()->getBlockClassName('tnw_salesforce/adminhtml_widget_form_element_account'));

        $fieldset->addField('account_id', 'account', array(
            'label' => $this->__('Account Name'),
            'name' => 'account_id',
            'style' => 'width:273px',
            'selector' => 'tnw-ajax-find-select',
            'required' => true
        ));

        $fieldset->addField('email_domain', 'text', array(
            'label' => $this->__('Email Domain'),
            'style' => 'width:273px',
            'name' => 'email_domain',
            'required' => true,
        ));

        $form->setValues($formValues);
        return parent::_prepareForm();
    }

}
