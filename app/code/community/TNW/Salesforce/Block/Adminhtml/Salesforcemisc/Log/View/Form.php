<?php

class TNW_Salesforce_Block_Adminhtml_Salesforcemisc_Log_View_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * @return Mage_Adminhtml_Block_Widget_Form
     */
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();

        $this->setForm($form);

        $fieldset = $form->addFieldset('log', array('legend' => Mage::helper('tnw_salesforce')->__('Log view'), 'class' => 'fieldset-wide'));

        $fieldset->addField('content', 'textarea', array(
            'label' => Mage::helper('tnw_salesforce')->__('Log content'),
            'name' => 'content',
            'style' => 'height:36em',
        ));

        if (Mage::registry('tnw_salesforce_log_file')) {
            $form->setValues(Mage::registry('tnw_salesforce_log_file')->getData());

        }

        return parent::_prepareForm();
    }
}