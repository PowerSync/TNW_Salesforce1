<?php

class TNW_Salesforce_Block_Adminhtml_Creditmemostatus_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    /**
     * TNW_Salesforce_Block_Adminhtml_Creditmemostatus_Edit constructor.
     */
    public function __construct()
    {
        $this->_objectId = 'id';
        parent::__construct();

        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_creditmemostatus';

        $this->_updateButton('save', 'label', $this->__('Save Mapping'));
        $this->_updateButton('delete', 'label', $this->__('Delete Mapping'));

        $this->_addButton('saveandcontinue', array(
            'label'     => $this->__('Save And Continue Edit'),
            'onclick'   => 'saveAndContinueEdit()',
            'class'     => 'save',
        ), -100);

        $this->_formScripts[] = "
            function saveAndContinueEdit(){
                editForm.submit($('edit_form').action+'back/edit/');
            }
        ";
    }

    /**
     * @return mixed
     */
    protected function getMapping()
    {
        return Mage::registry('credit_memo_status_mapping_data');
    }

    /**
     * Return translated header text depending on creating/editing action
     *
     * @return string
     */
    public function getHeaderText()
    {
        if ($this->getMapping() && $this->getMapping()->getId()) {
            return $this->__('Credit Memo status Mapping #%s', $this->getMapping()->getId());
        } else {
            return $this->__('New Credit Memo status Mapping');
        }
    }
}
