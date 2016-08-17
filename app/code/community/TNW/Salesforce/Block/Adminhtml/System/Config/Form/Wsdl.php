<?php

class TNW_Salesforce_Block_Adminhtml_System_Config_Form_Wsdl extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Enter description here...
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {

        $html = <<<HTML
<span class="fileUpload">
    <input type="button" class="form-button" value="{$this->__('Browse...')}" />
    <input id="uploadBtn" type="file" class="upload" name="{$element->getData('name')}" style="width:80px;" />
</span>
<button title="{$this->__('Save')}" type="button" class="scalable" onclick="configForm.submit()" style="">
    <span><span><span>{$this->__('Save')}</span></span></span>
</button>
HTML;

        $element->addData(array(
            'after_element_html' => $html,
            'style'              => 'width: 149px;'
        ));

        return sprintf('<div id="import_form">%s</div>', $element->getElementHtml());
    }
}