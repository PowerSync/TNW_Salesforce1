<?php

/**
 * @method Varien_Data_Form_Element_Text getElement()
 */
class TNW_Salesforce_Block_Adminhtml_Catalog_Product_Renderer_Pricebooks
    extends Mage_Adminhtml_Block_Catalog_Form_Renderer_Fieldset_Element
{
    /**
     * Retrieve element html
     *
     * @return string
     */
    public function getElementHtml()
    {
        $books = array_filter(explode("\n", $this->getElement()->getValue()));
        if (empty($books)) {
            return '<span style="font-family: monospace;">N/A</span>';
        }

        return implode('', array_map(function ($book) {
            $currency = $id = null;
            if (strpos($book, ':') !== false) {
                list($currency, $id) = explode(':', $book, 2);
            } else {
                $id = $book;
            }

            $link = Mage::helper('tnw_salesforce/salesforce_abstract')->generateLinkToSalesforce($id);
            $html = '<span style="font-family: monospace;">'.str_replace('<br />', '', $link) .'</span>';
            if (!empty($currency)) {
                $html .= " <span>($currency)</span>";
            }
            $html .= '<br />';

            return $html;
        }, $books));
    }
}