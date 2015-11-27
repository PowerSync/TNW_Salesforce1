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

        /** @var TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection $collection */
        $collection = Mage::getModel('tnw_salesforce_api_entity/account')->getCollection();

        $fieldset = $form->addFieldset('account_matching', array('legend' => $this->__('Matching Information')));
        $fieldset->addField('account_id', 'select', array(
            'label' => $this->__('Account Name'),
            'name' => 'account_id',
            'style' => 'width:273px',
            'values' => $collection->setFullIdMode(true)->getAllOptions(),
            'class' => 'chosen-select',
        ));

        $fieldset->addField('email_domain', 'text', array(
            'label' => $this->__('Email Domain'),
            'style' => 'width:273px',
            'name' => 'email_domain',
        ));

        if (Mage::getSingleton('adminhtml/session')->getAccountData()) {
            $form->setValues(Mage::getSingleton('adminhtml/session')->getAccountData());
            Mage::getSingleton('adminhtml/session')->getAccountData(null);
        } elseif (Mage::registry('salesforce_account_matching_data')) {
            $form->setValues(Mage::registry('salesforce_account_matching_data')->getData());
        }

        return parent::_prepareForm();
    }

}
