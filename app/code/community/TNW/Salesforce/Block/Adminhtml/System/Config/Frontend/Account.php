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
        /** @var Mage_Core_Model_Website $website */
        $website = Mage::helper('tnw_salesforce/config')
            ->getWebsiteDifferentConfig($this->getRequest()->getParam('website'));

        $value = $element->getData('value');
        $aIdVal = Mage::helper('tnw_salesforce/config')->wrapEmulationWebsiteDifferentConfig($website, function () use($value) {
            if (empty($value) || strlen($value) < TNW_Salesforce_Helper_Abstract::MIN_LEN_SF_ID) {
                return array();
            }

            return Mage::getSingleton('tnw_salesforce/sforce_entity_cache')
                ->toArraySearchById($value, TNW_Salesforce_Model_Sforce_Entity_Cache::CACHE_TYPE_ACCOUNT);
        });

        /** @var Mage_Core_Block_Template $block */
        $block = $this->getLayout()
            ->getBlockSingleton('core/template')
            ->setTemplate('salesforce/select2ajax.phtml')
            ->addData(array(
                'selector'  => sprintf('.%s', $element->getData('class')),
                'url'       => $this->getUrl('*/salesforce_search/account', array('website'=>$website->getCode())),
                'page_size' => TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection::PAGE_SIZE
            ));

        $element->addData(array(
            'values'                => $aIdVal,
            'after_element_html'    => $block->toHtml(),
        ));

        return $element->getElementHtml();
    }
}
