<?php

class TNW_Salesforce_Test_Model_Api_Client extends TNW_Salesforce_Test_Case
{
    public function providerConvertLead()
    {
        return array(
            array(array( //first data set
                array('leadId' => '1',),
            )),
            array(array( //another data set
                array('leadId' => '2',),
            )),
            array(array( //more then one data set
                array('leadId' => '3',),
                array('leadId' => '4',),
            )),
        );
    }

    /**
     * @singleton tnw_salesforce/connection
     *
     * @param array $lead
     *
     * @test
     * @dataProvider providerConvertLead
     */
    public function convertLead($leadArray)
    {
        //prepare params
        $params = array_map(function ($value) { return $this->arrayToObject($value); }, $leadArray);

        $response = new stdClass();
        foreach ($leadArray as $_lead) {
            $item = new stdClass();
            $item->leadId = $_lead['leadId'];
            $response->result[] = $item;
        }

        $this->mockClient(array('convertLead'));
        $this->getClientMock()->expects($this->once())
            ->method('convertLead')
            ->with($params)
            ->will($this->returnValue($response));

        $this->mockApplyClientToConnection();


        $client = Mage::getModel('tnw_salesforce/api_client');
        $result = $client->convertLead($params);

        $this->assertEquals($leadArray, $result);
    }

    /**
     * @singleton tnw_salesforce/connection
     *
     * @param array $lead
     *
     * @test
     * @dataProvider providerConvertLead
     */
    public function query($leadArray)
    {
        $lead = $leadArray[0];
        $this->mockClient(array('query'));
        $item = new stdClass();
        $item->ID = $lead['leadId'];
        $response = new stdClass();
        $response->records[] = $item;
        $this->getClientMock()->expects($this->once())
            ->method('query')
            ->will($this->returnValue($response));

        $this->mockApplyClientToConnection();

        $client = Mage::getModel('tnw_salesforce/api_client');
        $result = $client->query('SELECT ID From Lead WHERE ID = ' . $lead['leadId']);
        $this->assertEquals($lead['leadId'], $result[0]['ID']);
    }
}