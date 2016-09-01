<?php

class TNW_Salesforce_Block_Adminhtml_Sales_Order_Create_Salesforce extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();
        $form->setUseContainer(false);
        $form->setFieldNameSuffix('order');

        $form->addType('owner', Mage::getConfig()->getBlockClassName('tnw_salesforce/adminhtml_widget_form_element_owner'));

        $form->addField('owner_salesforce_id', 'owner', array(
            'name'      => 'owner_salesforce_id',
            'selector'  => 'tnw-ajax-find-select-owner-info'
        ));

        $this->setForm($form);
    }

    protected function _toHtml()
    {
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            return '';
        }

        return parent::_toHtml();
    }
}