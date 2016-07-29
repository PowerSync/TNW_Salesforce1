<?php

class TNW_Salesforce_Model_Sforce_Client extends Salesforce_SforceEnterpriseClient
{
    /**
     * @param string $ext_Id
     * @param array $sObjects
     * @param string $type
     * @return stdClass
     */
    public function upsert($ext_Id, $sObjects, $type = 'Contact')
    {
        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf("UPSERT: %s (%s) \n%s", $type, $ext_Id, print_r($sObjects, true)));

        $return = parent::upsert($ext_Id, $sObjects, $type);

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf("UPSERT Result: %s (%s) \n%s", $type, $ext_Id, print_r($return, true)));
        return $return;
    }
}