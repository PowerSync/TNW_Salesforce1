<?php

/**
 * @method Varien_Data_Form_Element_Text getElement()
 */
class TNW_Salesforce_Block_Adminhtml_Catalog_Product_Renderer_Product2
    extends Mage_Adminhtml_Block_Catalog_Form_Renderer_Fieldset_Element
{
    /**
     * Retrieve element html
     *
     * @return string
     */
    public function getElementHtml()
    {
        $_field = $this->getElement()->getValue();
        return '<span style="font-family: monospace;">'.Mage::helper('tnw_salesforce/salesforce_abstract')->generateLinkToSalesforce($_field).'</span>';
    }
}