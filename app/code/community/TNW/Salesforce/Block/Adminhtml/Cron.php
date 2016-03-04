<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Cron extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * return formatted timestamp
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return bool|string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $_lastTimestamp = (int)$element->getValue();
        return ($_lastTimestamp === 0) ? 'UNKNOWN' : date("l jS \of F Y h:i:s A", Mage::helper('tnw_salesforce')->getMagentoTime($_lastTimestamp));
    }
}