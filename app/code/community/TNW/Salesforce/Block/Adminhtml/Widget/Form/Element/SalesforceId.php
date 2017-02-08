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
        $value = $this->getValue();
        $link = Mage::helper('tnw_salesforce/config')->wrapEmulationWebsiteDifferentConfig($this->getWebsite(), function () use($value) {
            return Mage::helper('tnw_salesforce/salesforce_abstract')
                ->generateLinkToSalesforce($value);
        });

        return sprintf('<span style="font-family: monospace;">%s</span>', $link);
    }

    /**
     * @return Mage_Core_Model_Website|null
     */
    protected function getWebsite()
    {
        return Mage::helper('tnw_salesforce/config')
            ->getWebsiteDifferentConfig($this->getData('website'));
    }
}