<?php

/**
 * Created by PhpStorm.
 * User: evgeniy
 * Date: 25.08.16
 * Time: 17:31
 */
class TNW_Salesforce_Model_Sforce_Soapclient extends SoapClient
{
    /**
     * @param string $request
     * @param string $location
     * @param string $action
     * @param int $version
     * @param int $one_way
     * @return string
     */
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        if (Mage::helper('tnw_salesforce/config_tool')->getLogApiCallStatistic()) {

            $salesforce_used_apicalls = Mage::registry('salesforce_used_apicalls');
            $salesforce_used_apicalls++;
            Mage::unregister('salesforce_used_apicalls');
            Mage::register('salesforce_used_apicalls', $salesforce_used_apicalls);

            Mage::getSingleton('tnw_salesforce/tool_log')->saveInfo($request);
        }

        return parent::__doRequest($request, $location, $action, $version, $one_way);
    }


}