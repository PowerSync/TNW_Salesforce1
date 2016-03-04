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

        $fieldset->addType('raw', Mage::getConfig()->getBlockClassName('tnw_salesforce/varien_data_form_element_raw'));
        $fieldset->addField('content', 'raw', array(
            'id' => 'tnw_salesforce_load_content',
            'label' => Mage::helper('tnw_salesforce')->__('Content'),
            'name' => 'content'
        ));

        $url = $this->getUrl('adminhtml/tool_log_file/fileContent', array('filename' => $logData['filename']));

        /** @var Mage_Adminhtml_Block_Widget_Button $headerBar */
        $headerBar = $this->getLayout()->createBlock('adminhtml/widget_button');
        $headerBar->setId('tnw_salesforce_load_more');
        $headerBar->setData(array(
            'label'      => Mage::helper('tnw_salesforce')->__('Load more'),
            'class'      => 'add',
            'onclick'    => 'tnwSalesforceLoadMore.loadMode()',
            'after_html' => Mage::helper('adminhtml/js')->getScript(
                sprintf('tnwSalesforceLoadMore.setConfig({url: "%s", container: "%s"})', $url, 'tnw_salesforce_load_content'))
        ));

        $fieldset->setHeaderBar($headerBar->toHtml());

        if ($logData) {
            $form->setValues($logData);
        }

        return parent::_prepareForm();
    }
}