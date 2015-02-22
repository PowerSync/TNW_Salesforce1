<?php
/**
 * Class TNW_Salesforce_Block_Adminhtml_Cron
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
        $_mageCache = Mage::app()->getCache();
        $_useCache = Mage::app()->useCache('tnw_salesforce');

        $_lastTimestamp = 0;
        if ($_useCache) {
            $_lastTimestamp = ($_mageCache->load('tnw_salesforce_cron_timestamp')) ? unserialize($_mageCache->load('tnw_salesforce_cron_timestamp')) : 0;
        }

        return ($_lastTimestamp === 0) ? 'UNKNOWN' : date("l jS \of F Y h:i:s A", $_lastTimestamp);
    }
}