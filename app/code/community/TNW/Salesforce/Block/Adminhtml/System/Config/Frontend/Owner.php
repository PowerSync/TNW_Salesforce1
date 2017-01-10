<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_System_Config_Frontend_Owner
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $cIdVal = array();
        $value = $element->getData('value');
        if (!empty($value) && strlen($value) >= TNW_Salesforce_Helper_Abstract::MIN_LEN_SF_ID) {
            $cIdVal = Mage::getSingleton('tnw_salesforce/sforce_entity_cache')
                ->toArraySearchById($value, TNW_Salesforce_Model_Sforce_Entity_Cache::CACHE_TYPE_USER);
        }

        $websiteCode = Mage::app()->getWebsite()->getCode();
        if ($websiteCode == 'admin') {
            $websiteCode = Mage::app()->getRequest()->getParam('website');
            $websiteCode = Mage::app()->getWebsite($websiteCode)->getCode();
        }

        /** @var Mage_Core_Block_Template $block */
        $block = $this->getLayout()
            ->getBlockSingleton('core/template')
            ->setTemplate('salesforce/select2ajax.phtml')
            ->addData(array(
                'selector'  => sprintf('.%s', $element->getData('class')),
                'url'       => $this->getUrl('*/salesforce_search/user', array('website'=>$websiteCode)),
                'page_size' => TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection::PAGE_SIZE
            ));

        $element->addData(array(
            'values'                => $cIdVal,
            'after_element_html'    => $block->toHtml(),
        ));

        return $element->getElementHtml();
    }
}
