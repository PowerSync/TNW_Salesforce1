<?php

class TNW_Salesforce_Test_Helper_Config extends TNW_Salesforce_Test_Case
{
    /**
     * @test
     */
    public function getDisableSyncField()
    {
        $expected = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . 'disableMagentoSync__c';
        $actual = Mage::helper('tnw_salesforce/config')->getDisableSyncField();
        $this->assertEquals($expected, $actual);
    }
}