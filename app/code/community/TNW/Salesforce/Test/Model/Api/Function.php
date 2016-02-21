<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Test_Model_Api_Function extends TNW_Salesforce_Test_Case
{
    /**
     * @loadFixture
     * @dataProvider dataProvider
     * @test
     */
    public function convertLead($leadId)
    {
        $lead = $this->getSalesforceFixture('lead', array('ID' => $leadId));

        //mock connection
        $this->mockConnection(/*array('initConnection')*/);

        //mock client
        $apiClientMock = $this->getModelMock('tnw_salesforce/api_client', array('convertLead'));
        $apiClientMock->expects($this->once())
            ->method('convertLead')
            ->with($this->equalTo(array($this->arrayToObject(array(
                'leadId' => $leadId
            )))))
            ->will($this->returnValue(array(array('leadId' => $leadId))));
        $this->replaceByMock('singleton', 'tnw_salesforce/api_client', $apiClientMock);

        //prepare model
        $api = Mage::getModel('tnw_salesforce/api_function');

        $response = $api->convertLead(array('leadId' => $leadId));

        $this->assertEquals($leadId, $response[0]['leadId']);
    }
}