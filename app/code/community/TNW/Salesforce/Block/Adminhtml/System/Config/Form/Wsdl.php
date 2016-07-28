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
        $element->setData('after_element_html', '<div id="import_form" style="display: inline;"><span class="fileUpload">
    <input type="button" class="form-button" value="Browse..." />
    <input id="uploadBtn" type="file" class="upload" name="fileImport" />
</span><button title="Save" type="button" class="scalable" onclick="configForm.submit()" style=""><span><span><span>Save</span></span></span></button></div>');

        $element->setData('style', 'width: 149px;');
        return $element->getElementHtml();
    }
}