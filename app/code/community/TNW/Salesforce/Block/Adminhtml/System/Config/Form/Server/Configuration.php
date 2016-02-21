<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_System_Config_Form_Server_Configuration extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Enter description here...
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {

        $settingName = $element->getFieldConfig()->getName();

        $settingValue = Mage::helper('tnw_salesforce/config_server')->getOriginSetting($settingName);

        $element->setComment($element->getComment() . $settingValue);

        return parent::render($element);
    }

}