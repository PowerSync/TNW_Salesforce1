<?php
/**
 * Created by PhpStorm.
 * User: evgeniy
 * Date: 25.08.16
 * Time: 16:44
 */

class TNW_Salesforce_Model_Tool_Observer
{
    /**
     * @param $observer
     * @return $this
     */
    public function logAvailableAPICalls($observer)
    {

        if (!Mage::registry('salesforce_used_apicalls') || !Mage::helper('tnw_salesforce/config_tool')->getLogApiCallStatistic()) {
            return $this;
        }

        $salesforce_used_apicalls = Mage::registry('salesforce_used_apicalls');

        Mage::getSingleton('tnw_salesforce/tool_log')->saveInfo('-----------------------------------------------------');
        Mage::getSingleton('tnw_salesforce/tool_log')->saveInfo('Statistic: used API calls: ' . $salesforce_used_apicalls);
        Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice('Statistic: used API calls: ' . $salesforce_used_apicalls);

        return $this;
    }
}