<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

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