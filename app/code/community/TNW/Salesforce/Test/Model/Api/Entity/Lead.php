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
}