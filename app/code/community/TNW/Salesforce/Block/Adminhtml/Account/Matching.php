<?php

class TNW_Salesforce_Block_Adminhtml_Account_Matching extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * @internal
     */
    public function __construct()
    {
        /** @var  TNW_Salesforce_Helper_Data $helper */
        $helper             = Mage::helper('tnw_salesforce');

        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_account_matching';
        $this->_headerText = $helper->__('Account Matching');

        parent::__construct();

        /** Add button */
        $this->_updateButton('add', 'label', $helper->__('Add New Matching'));

        /** Upload form */
        $form = new Varien_Data_Form(array(
            'use_container' => true,
            'method'        => 'post',
            'action'        => $this->getUrl('tnw_salesforce/adminhtml_account_matching/matchingImport'),
            'id'            => 'import_form',
            'enctype'       => 'multipart/form-data'
        ));

        $form->addField('fileImport', 'file', array(
            'required'  => false,
            'name'      => 'fileImport',
            'no_span'   => true
        ));

        /** Import button */
        $this->_addButton('synch_matching', array(
            'label'       => $helper->__('Import'),
            'onclick'     => '$(\'import_form\').submit()',
            'class'       => '',
            'before_html' => $form->toHtml()
        ), 0, 1);
    }
}
