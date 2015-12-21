<?php

class TNW_Salesforce_Block_Adminhtml_Account_Matching_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    /**
     * @internal
     */
    public function __construct()
    {
        if (!Mage::helper('tnw_salesforce')->isWorking()) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('tnw_salesforce')->__('There is an issue with integration, please make sure all tests are successful!'));

            Mage::app()->getResponse()->setRedirect(
                Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit", array('section' => 'salesforce')));

            return;
        }

        $this->_objectId = 'matching_id';
        parent::__construct();

        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_account_matching';

        $this->_updateButton('save', 'label', Mage::helper('tnw_salesforce')->__('Save Matching'));
        $this->_updateButton('delete', 'label', Mage::helper('tnw_salesforce')->__('Delete Matching'));

        $this->_addButton('saveandcontinue', array(
            'label' => Mage::helper('adminhtml')->__('Save And Continue Edit'),
            'onclick' => 'saveAndContinueEdit()',
            'class' => 'save',
        ), -100);

        $this->_formScripts[] = "
            function saveAndContinueEdit(){
                editForm.submit($('edit_form').action+'back/edit/');
            }
        ";
    }

    /**
     * @return TNW_Salesforce_Model_Account_Matching
     */
    protected function getMatching()
    {
        return Mage::registry('salesforce_account_matching_data');
    }

    /**
     * Return translated header text depending on creating/editing action
     *
     * @return string
     */
    public function getHeaderText()
    {
        if ($this->getMatching()->getId()) {
            return Mage::helper('tnw_salesforce')->__('%s Object Matching #%s', $this->htmlEscape($this->getMatching()->getAccountId()), $this->getMatching()->getId());
        } else {
            return Mage::helper('tnw_salesforce')->__('New Account Matching');
        }
    }
}
