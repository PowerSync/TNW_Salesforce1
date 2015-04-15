<?php

class TNW_Salesforce_Test_Model_Api_Entity_Lead extends TNW_Salesforce_Test_Case
{
    /**
     * @singleton tnw_salesforce/api_client
     *
     * @dataProvider dataProvider
     * @loadFixture
     * @test
     */
    public function loadLead($leadId)
    {
        //prepare expectations
        try {
            $expectation = $this->expected($leadId)->getData();
        } catch (InvalidArgumentException $e) {
            $expectation = array();
        }

        $queryResponse = array();
        $fixtureData = $this->getSalesforceFixture('lead', array('ID' => $leadId));
        if ($fixtureData) {
            $queryResponse[] = $fixtureData;
        }
        $this->mockQueryResponse($queryResponse);

        $entity = Mage::getModel('tnw_salesforce_api_entity/lead');
        $entity->load($leadId);
        $this->assertEquals($expectation, $entity->getData());
    }

    /**
     * @loadFixture
     * @dataProvider dataProvider
     * @test
     */
    public function convert($leadId)
    {
        //mock api function model to check input params
        $mockApiFunction = $this->mockModel('tnw_salesforce/api_function');
        $mockApiFunction->expects($this->once())
            ->method('convertLead')
            ->with(array(
                'convertedStatus' => Mage::helper("tnw_salesforce")->getLeadConvertedStatus(),
                'leadId' => $leadId,
                'doNotCreateOpportunity' => 'true',
                'overwriteLeadSource' => 'false',
                'sendNotificationEmail' => 'false',
            ));
        $this->replaceByMock('singleton', 'tnw_salesforce/api_function', $mockApiFunction);

        /** @var TNW_Salesforce_Model_Api_Entity_Lead $lead */
        $lead = Mage::getModel('tnw_salesforce_api_entity/lead')
            ->setData($this->getSalesforceFixture('lead', array('ID' => $leadId)));

        $lead->convert();
    }
}