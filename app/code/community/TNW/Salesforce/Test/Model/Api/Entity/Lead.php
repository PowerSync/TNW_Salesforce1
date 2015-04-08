<?php

class TNW_Salesforce_Test_Model_Api_Entity_Lead extends TNW_Salesforce_Test_Case
{
    /**
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

        $this->mockClient(array('query'));
        $this->getClientMock()->expects($this->any())
            ->method('query')
            ->will($this->returnValue($this->getSalesforceFixture('lead', array('ID' => $leadId))));
        $this->mockApplyClientToConnection();

        $entity = Mage::getModel('tnw_salesforce_api_entity/lead');
        $entity->load($leadId);
        $this->assertEquals($expectation, $entity->getData());
    }
}