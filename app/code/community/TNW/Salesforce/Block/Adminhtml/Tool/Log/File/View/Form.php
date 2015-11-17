<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Block_Adminhtml_Tool_Log_File_View_Form
 */

class TNW_Salesforce_Block_Adminhtml_Tool_Log_File_View_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * @return Mage_Adminhtml_Block_Widget_Form
     */
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();

        $logData = array();
        if (Mage::registry('tnw_salesforce_log_file')) {
            $logData = Mage::registry('tnw_salesforce_log_file')->getData();
        }

        $this->setForm($form);

        $fieldset = $form->addFieldset('log', array('legend' => Mage::helper('tnw_salesforce')->__('Log view'), 'class' => 'fieldset-wide'));

        $fieldset->addField('filename', 'link', array(
            'label' => Mage::helper('tnw_salesforce')->__('File name'),
            'name' => 'filename',
            'style' => 'height:36em',
            'href' => $this->getUrl('*/*/download', array('filename' => $logData['filename']))

        ));

        $fieldset->addType('raw', 'TNW_Salesforce_Block_Varien_Data_Form_Element_Raw');

        $fieldset->addField('content', 'raw', array(
            'label' => Mage::helper('tnw_salesforce')->__('Log content'),
            'name' => 'content',
            'nl2br' => true
        ));

        if ($logData) {
            $form->setValues($logData);
        }

        return parent::_prepareForm();
    }
}