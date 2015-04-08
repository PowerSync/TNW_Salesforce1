<?php

class TNW_Salesforce_Test_Model_Api_Client extends TNW_Salesforce_Test_Case
{
    public function providerConvertLead()
    {
        return array(
            array(array(
                'leadId' => '1',
                )),
            array(array(
                'leadId' => '2',
            )),
        );
    }

    /**
     * @param array $lead
     *
     * @test
     * @dataProvider providerConvertLead
     */
    public function convertLead($lead)
    {
        $this->mockClient(array('convertLead'));
        $response = new stdClass();
        $response->leadId = $lead['leadId'];
        $this->getClientMock()->expects($this->once())
            ->method('convertLead')
            ->will($this->returnValue($response));

        $this->mockApplyClientToConnection();


        $client = Mage::getModel('tnw_salesforce/api_client');
        $result = $client->convertLead($lead);

        $this->assertEquals($lead['leadId'], $result->getData('leadId'));
    }

    /**
     * @param array $lead
     *
     * @test
     * @dataProvider providerConvertLead
     */
    public function query($lead)
    {
        $this->mockClient(array('query'));
        $response = new stdClass();
        $response->ID = $lead['leadId'];
        $this->getClientMock()->expects($this->once())
            ->method('query')
            ->will($this->returnValue($response));

        $this->mockApplyClientToConnection();

        $client = Mage::getModel('tnw_salesforce/api_client');
        $result = $client->query('SELECT ID From Lead WHERE ID = ' . $lead['leadId']);
        $this->assertEquals($lead['leadId'], $result->getData('ID'));
    }
}