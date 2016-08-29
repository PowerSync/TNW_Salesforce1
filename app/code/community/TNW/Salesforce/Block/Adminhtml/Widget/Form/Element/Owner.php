<?php

class TNW_Salesforce_Block_Adminhtml_Widget_Form_Element_Owner extends Varien_Data_Form_Element_Select
{
    /**
     * Return Form Element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $cIdVal   = array();
        $ownerId  = $this->getValue();
        if (!empty($ownerId)) {
            /** @var TNW_Salesforce_Model_Api_Entity_Resource_User_Collection $collection */
            $collection = Mage::getResourceModel('tnw_salesforce_api_entity/user_collection')
                ->addFieldToFilter('Id', array('eq' => $ownerId));
            $cIdVal = $collection->setFullIdMode(true)->getAllOptions();
        }

        $sfLink = Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_url');

        $this->addData(array(
            'label'    => null,
            'no_span'  => true,
            'values'   => $cIdVal,
            'class'    => $this->getData('selector'),
            'onchange' => "document.getElementById('{$this->getId()}-link').href = '{$sfLink}/'+this.value",
        ));

        return sprintf('<span class="tnw-owner-wrapper">%s<a class="tnw-owner-link" target="_blank" id="%s-link" href="%s">%s</a></span>',
            parent::getElementHtml(),
            $this->getId(),
            !empty($ownerId) ? sprintf('%s/%s', $sfLink, $ownerId) : '#',
            Mage::helper('tnw_salesforce')->__('View in Salesforce')
        );
    }

    /**
     * @return string
     */
    public function getAfterElementHtml()
    {
        /** @var Mage_Adminhtml_Block_Template $block */
        $block = Mage::app()->getLayout()
            ->getBlockSingleton('adminhtml/template');

        $block
            ->setTemplate('salesforce/select2ajax.phtml')
            ->addData(array(
                'url'       => $block->getUrl('*/salesforce_search/user'),
                'page_size' => TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection::PAGE_SIZE,
                'selector'  => sprintf('.%s', $this->getData('selector'))
            ));

        return $block->toHtml().parent::getAfterElementHtml();
    }
}