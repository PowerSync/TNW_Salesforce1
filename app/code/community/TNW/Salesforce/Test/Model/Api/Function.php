<?php

class TNW_Salesforce_Test_Model_Api_Function extends TNW_Salesforce_Test_Case
{
    /**
     * Reset cached singleton
     * @singleton tnw_salesforce/cached
     *
     * @loadFixture
     * @dataProvider dataProvider
     * @test
     */
    public function convertLead($leadId, $returnedAccountId)
    {
        if (!$returnedAccountId) {
            $returnedAccountId = rand();
        }

        $lead = $this->getSalesforceFixture('lead', array('ID' => $leadId));
        $email = $lead['Email'];

        //mock connection
        $this->mockConnection(array('initConnection'));

        //mock lead load
        $this->mockClient(array('query'));
        $this->getClientMock()->expects($this->any())
            ->method('query')
            ->will($this->returnValue($this->getSalesforceFixture('lead', array('ID' => $leadId))));
        $this->mockApplyClientToConnection();

        //mock account lookup
        $accountLookupMock = $this->mockHelper('tnw_salesforce/salesforce_data_account', array('lookup'));
        $accountLookupMock->expects($this->once())
            ->method('lookup')
            ->with($this->equalTo(array($email)), $this->equalTo(array()))
            ->will($this->returnValue(array(0 => array($email => array('ID' => $returnedAccountId)))));
        $this->replaceByMock('helper', 'tnw_salesforce/salesforce_data_account', $accountLookupMock);

        //mock client
        $apiClientMock = $this->getModelMock('tnw_salesforce/api_client', array('convertLead'));
        $apiClientMock->expects($this->once())
            ->method('convertLead')
            ->with($this->equalTo(array(
                'leadId' => $leadId,
                'accountId' => $returnedAccountId,
            )))
            ->will($this->returnCallback(function ($value) { return new Varien_Object($value); }));
        $this->replaceByMock('singleton', 'tnw_salesforce/api_client', $apiClientMock);

        //check lead load executes not more than 1 time
        $leadModel = Mage::getModel('tnw_salesforce_api_entity/lead');
        $leadMock = $this->mockModel('tnw_salesforce_api_entity/lead');
        $leadMock->expects($this->once())
            ->method('load')
            ->will($this->returnCallback(function ($id) use ($leadModel) {
                return $leadModel->load($id);
            }));
        $this->replaceByMock('model', 'tnw_salesforce_api_entity/lead', $leadMock);
        //execute first time here
        Mage::getSingleton('tnw_salesforce/cached')->load('tnw_salesforce_api_entity/lead', $leadId);

        //prepare model
        $api = Mage::getModel('tnw_salesforce/api_function');

        $response = $api->convertLead(array('leadId' => $leadId));

        $this->assertInstanceOf('Varien_Object', $response);
        $this->assertEquals($leadId, $response->getData('leadId'));
        if ($returnedAccountId) {
            $this->assertEquals($returnedAccountId, $response->getData('accountId'));
        } else {
            $this->assertNotEquals($returnedAccountId, $response->getData('accountId'));
        }
    }
}