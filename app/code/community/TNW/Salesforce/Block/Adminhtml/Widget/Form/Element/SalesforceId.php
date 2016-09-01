<?php

class TNW_Salesforce_Block_Adminhtml_Widget_Form_Element_SalesforceId extends Varien_Data_Form_Element_Link
{
    /**
     * Return Form Element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $_field = $this->getValue();
        return '<span style="font-family: monospace;">'.Mage::helper('tnw_salesforce/salesforce_abstract')->generateLinkToSalesforce($_field).'</span>';
    }
}