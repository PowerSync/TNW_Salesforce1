<?php

/**
 * @method Varien_Data_Form_Element_Text getElement()
 */
class TNW_Salesforce_Block_Adminhtml_Catalog_Product_Renderer_Owner
    extends Mage_Adminhtml_Block_Catalog_Form_Renderer_Fieldset_Element
{
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

        $field = new TNW_Salesforce_Block_Adminhtml_Catalog_Product_Helper_Chosen($this->getElement()->getData());
        $field->setId($this->getElement()->getId())
            ->setForm($this->getElement()->getForm())
            ->addData(array(
                'label'              => null,
                'no_span'            => true,
                'values'             => $cIdVal,
                'class'              => $selector,
                'after_element_html' => $block->toHtml()
            ));

        return $field->toHtml();
    }
}