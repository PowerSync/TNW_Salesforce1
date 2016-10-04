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
        $value  = $this->getValue();
        if (!empty($value) && strlen($value) >= TNW_Salesforce_Helper_Abstract::MIN_LEN_SF_ID) {
            /** @var TNW_Salesforce_Model_Api_Entity_Resource_User_Collection $collection */
            $collection = Mage::getResourceModel('tnw_salesforce_api_entity/user_collection')
                ->addFieldToFilter('Id', array('eq' => $value));
            $cIdVal = $collection->setFullIdMode(true)->getAllOptions();
        }

        $sfLink = Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_url');

        $this->addData(array(
            'label'    => null,
            'values'   => $cIdVal,
            'class'    => $this->getData('selector'),
            'onchange' => "$$('.{$this->getId()}-link').each((function(e){e.href = '{$sfLink}/'+this.value; e.style.display = 'inline';}).bind(this));",
        ));

        return sprintf('<span class="tnw-owner-wrapper">%s<a class="tnw-owner-link %s-link" target="_blank" href="%s" style="display: %s">%s</a></span>',
            parent::getElementHtml(),
            $this->getId(),
            !empty($value) ? sprintf('%s/%s', $sfLink, $value) : '#',
            !empty($value) ? 'inline' : 'none',
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