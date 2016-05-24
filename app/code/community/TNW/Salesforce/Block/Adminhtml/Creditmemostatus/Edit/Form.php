<?php

class TNW_Salesforce_Block_Adminhtml_Creditmemostatus_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form(array(
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', array('status_id' => $this->getRequest()->getParam('status_id'))),
            'method' => 'post',
            'enctype' => 'multipart/form-data'
        ));
        $form->setUseContainer(true);

        $fieldSet = $form->addFieldset('credit_memo_status_map', array(
            'legend' => $this->__('Mapping Information')
        ));

        $fieldSet->addField('magento_stage', 'select', array(
            'label'     => $this->__('Credit Memo status'),
            'class'     => 'required-entry',
            'required'  => true,
            'style'     => 'width:400px',
            'name'      => 'magento_stage',
            'values'    => Mage::getModel('sales/order_creditmemo')->getStates(),
        ));

        $sfFields = array();
        $_sfData = Mage::helper('tnw_salesforce/salesforce_data');
        $sfFields[] = array(
            'value' => '',
            'label' => 'Choose Salesforce Status ...'
        );

        $states = $_sfData->getPicklistValues('Order', 'Status');
        if (!is_array($states)) {
            $states = array();
        }
        foreach ($states as $key => $field) {
            $sfFields[] = array(
                'value' => $field->label,
                'label' => $field->label
            );
        }

        $fieldSet->addField('salesforce_status', 'select', array(
            'label'     => $this->__('Salesforce Order status'),
            'class'     => 'required-entry',
            'required'  => true,
            'style'     => 'width:400px',
            'name'      => 'salesforce_status',
            'values'    => $sfFields
        ));

        $statusModel = Mage::registry('credit_memo_status_mapping_data');
        if ($statusModel instanceof Varien_Object) {
            $form->setValues($statusModel->getData());
        }

        $this->setForm($form);
        return parent::_prepareForm();
    }
}