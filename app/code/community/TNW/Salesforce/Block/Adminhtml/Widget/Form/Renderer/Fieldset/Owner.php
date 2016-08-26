<?php

/**
 * @method Varien_Data_Form_Element_Text getElement()
 */
class TNW_Salesforce_Block_Adminhtml_Widget_Form_Renderer_Fieldset_Owner
    extends Mage_Adminhtml_Block_Widget_Form_Renderer_Fieldset_Element
{
    protected function _construct()
    {
        $this->setTemplate('salesforce/form/renderer/fieldset/element.phtml');
        Mage_Core_Block_Template::_construct();
    }

    /**
     * Retrieve element html
     *
     * @return string
     */
    public function getElementHtml()
    {
        $cIdVal   = array();
        $ownerId  = $this->getElement()->getValue();
        $selector = $this->getElement()->getData('selector');
        if (!empty($ownerId)) {
            /** @var TNW_Salesforce_Model_Api_Entity_Resource_User_Collection $collection */
            $collection = Mage::getResourceModel('tnw_salesforce_api_entity/user_collection')
                ->addFieldToFilter('Id', array('eq' => $ownerId));
            $cIdVal = $collection->setFullIdMode(true)->getAllOptions();
        }

        /** @var Mage_Core_Block_Template $block */
        $block = $this->getLayout()
            ->getBlockSingleton('core/template')
            ->setTemplate('salesforce/select2ajax.phtml')
            ->addData(array(
                'url'       => $this->getUrl('*/salesforce_search/user'),
                'page_size' => TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection::PAGE_SIZE,
                'selector'  => sprintf('.%s', $selector)
            ));

        $sfLink = Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_url');

        $field = new TNW_Salesforce_Block_Adminhtml_Catalog_Product_Helper_Chosen($this->getElement()->getData());
        $field->setId($this->getElement()->getId())
            ->setForm($this->getElement()->getForm())
            ->addData(array(
                'label'              => null,
                'no_span'            => true,
                'values'             => $cIdVal,
                'class'              => $selector,
                'onchange'           => "document.getElementById('{$this->getElement()->getId()}-link').href = '{$sfLink}/'+this.value",
                'after_element_html' => $block->toHtml()
            ));

        $value = $this->getElement()->getValue();
        $currentLink = !empty($value) ? sprintf('%s/%s', $sfLink, $value) : '#';
        return sprintf('<span style="position: relative;">%s<div style="position: absolute; right: -108px; top: -2px;"><a target="_blank" id="%s-link" href="%s">%s</a></div></span>', $field->toHtml(), $field->getId(), $currentLink, $this->__('View in Salesforce'));
    }
}