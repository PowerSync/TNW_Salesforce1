<?php

class TNW_Salesforce_Test_Helper_Shipment extends TNW_Salesforce_Test_Case
{
    /**
     * @loadFixture
     */
    public function testGetAccountByCarrier()
    {
        $expectations = array(
            'flatrate' => '001240000094mfrAAA',
            'ups' => '001240000094mmsAAA',
            'notexist' => null,
        );

        $helper = Mage::helper('tnw_salesforce/shipment');

        foreach ($expectations as $carrier => $accountId) {
            $this->assertEquals($accountId, $helper->getAccountByCarrier($carrier));
        }
    }

    /**
     * @loadFixture
     */
    public function testSyncEnabled()
    {
        $this->assertTrue(Mage::helper('tnw_salesforce/shipment')->syncEnabled());
    }

    /**
     * @loadFixture
     */
    public function testSyncDisabled()
    {
        $this->assertFalse(Mage::helper('tnw_salesforce/shipment')->syncEnabled());
    }
}