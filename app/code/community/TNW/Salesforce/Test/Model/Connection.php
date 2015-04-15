<?php

class TNW_Salesforce_Test_Model_Connection extends TNW_Salesforce_Test_Case
{
    /**
     * @test
     */
    public function testClientClass()
    {
        $this->mockConnection();

        $connection = Mage::getModel('tnw_salesforce/connection');
        $this->assertInstanceOf('Salesforce_SforceEnterpriseClient', $connection->getClient());
    }
}