<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_System_Config_Frontend_Account
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        /** @var TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection $collection */
        $collection = Mage::getResourceModel('tnw_salesforce_api_entity/account_collection')
            ->addFieldToFilter('Id', array('eq' => $element->getData('value')));

        /** @var Mage_Core_Block_Template $block */
        $block = $this->getLayout()
            ->getBlockSingleton('core/template')
            ->setTemplate('salesforce/select2ajax.phtml')
            ->addData(array(
                'url'       => $this->getUrl('*/salesforce_account_matching/search'),
                'page_size' => TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection::PAGE_SIZE
            ));

        $element->addData(array(
            'values'                => $collection->setFullIdMode(true)->getAllOptions(),
            'after_element_html'    => $block->toHtml(),
        ));

        return $element->getElementHtml();
    }
}
