<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 * Date: 27.05.15
 * Time: 15:22
 */
class TNW_Salesforce_Block_Adminhtml_Tool_Console_Form extends Mage_Adminhtml_Block_Widget_Form
{

    /**
     * This method is called before rendering HTML
     *
     * @return Mage_Adminhtml_Block_Widget_Form
     */
    protected function _beforeToHtml()
    {
        $this->setChild('form_after', $this->getLayout()->createBlock('tnw_salesforce/adminhtml_tool_console_form_grid'), 'sql_result');

        return parent::_beforeToHtml();
    }


    /**
     * @return Mage_Adminhtml_Block_Widget_Form
     */
    protected function _prepareForm()
    {
        $urlParams = array();

        $sql = Mage::registry('sql');

        $form = new Varien_Data_Form(array(
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/query', $urlParams),
            'method' => 'post'
        ));

        $fieldset = $form->addFieldset('base_fieldset', array(
            'legend' => $this->__("SF Information"),
            'class' => 'fieldset-wide',
        ));


        $fieldset->addField('sql', 'text' /* select | multiselect | hidden | password | ...  */, array(
            'name' => 'sql',
            'label' => $this->__('Query'),
            'title' => $this->__('Query'),
            'required' => true,
            'value' => $sql
        ));

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }

}
