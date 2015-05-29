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
        $item->Id = $lead['leadId'];
        $response = new stdClass();
        $response->records[] = $item;
        $this->getClientMock()->expects($this->once())
            ->method('query')
            ->will($this->returnValue($response));

        $this->mockApplyClientToConnection();

        $client = Mage::getModel('tnw_salesforce/api_client');
        $result = $client->query('SELECT Id From Lead WHERE ID = ' . $lead['leadId']);
        $this->assertEquals($lead['leadId'], $result[0]['Id']);
    }

    /**
     * upsert method checking
     */

    public function testUpsertReturnResult()
    {
        $expectedResult = 'SOME RESULT';

        $this->getConnectionMock(array('initConnection'))
            ->expects($this->any())
            ->method('initConnection')
            ->willReturn(true);

        $this->mockClient(array('upsert'));
        $this->mockApplyClientToConnection();
        $this->getClientMock()->expects($this->any())
            ->method('upsert')
            ->willReturn($expectedResult);

        $result = Mage::getModel('tnw_salesforce/api_client')->upsert('Id',
            array($this->arrayToObject(array('TEST' => 'TEST'))), 'Lead');
        $this->assertEquals($expectedResult, $result);
    }

    public function testUpsertObjectAsArray()
    {
        $id = 'Id';
        $inputData = array(
            $id => 'SOMEID',
            'Value' => 'SOMEVALUE',
        );
        $entity = 'Lead';

        $this->getConnectionMock(array('initConnection'))
            ->expects($this->any())
            ->method('initConnection')
            ->willReturn(true);

        $this->mockClient(array('upsert'));
        $this->mockApplyClientToConnection();
        $this->getClientMock()->expects($this->once())
            ->method('upsert')
            ->with($id, array($this->arrayToObject($inputData)), $entity);

        Mage::getModel('tnw_salesforce/api_client')->upsert($id, $inputData, $entity);
    }

    public function testUpsertObject()
    {
        $id = 'Id';
        $inputData = array(
            $id => 'SOMEID',
            'Value' => 'SOMEVALUE',
        );
        $entity = 'Lead';

        $this->getConnectionMock(array('initConnection'))
            ->expects($this->any())
            ->method('initConnection')
            ->willReturn(true);

        $this->mockClient(array('upsert'));
        $this->mockApplyClientToConnection();
        $this->getClientMock()->expects($this->once())
            ->method('upsert')
            ->with($id, array($this->arrayToObject($inputData)), $entity);

        Mage::getModel('tnw_salesforce/api_client')->upsert($id, $this->arrayToObject($inputData), $entity);
    }

    public function testUpsertArrayOfObjects()
    {
        $id = 'Id';
        $inputData1 = array(
            $id => 'SOMEID1',
            'Value' => 'SOMEVALUE1',
        );
        $inputData2 = array(
            $id => 'SOMEID2',
            'Value' => 'SOMEVALUE2',
        );
        $entity = 'Lead';
        $data = array($this->arrayToObject($inputData1), $this->arrayToObject($inputData2));

        $this->getConnectionMock(array('initConnection'))
            ->expects($this->any())
            ->method('initConnection')
            ->willReturn(true);

        $this->mockClient(array('upsert'));
        $this->mockApplyClientToConnection();
        $this->getClientMock()->expects($this->once())
            ->method('upsert')
            ->with($id, $data, $entity);

        Mage::getModel('tnw_salesforce/api_client')->upsert($id, $data, $entity);
    }

    public function testUpsertArrayOfArrays()
    {
        $id = 'Id';
        $inputData1 = array(
            $id => 'SOMEID1',
            'Value' => 'SOMEVALUE1',
        );
        $inputData2 = array(
            $id => 'SOMEID2',
            'Value' => 'SOMEVALUE2',
        );
        $entity = 'Lead';

        $this->getConnectionMock(array('initConnection'))
            ->expects($this->any())
            ->method('initConnection')
            ->willReturn(true);

        $this->mockClient(array('upsert'));
        $this->mockApplyClientToConnection();
        $this->getClientMock()->expects($this->once())
            ->method('upsert')
            ->with($id, array($this->arrayToObject($inputData1), $this->arrayToObject($inputData2)), $entity);

        Mage::getModel('tnw_salesforce/api_client')->upsert($id, array($inputData1, $inputData2), $entity);
    }

    /**
     * @expectedException Mage_Core_Exception
     */
    public function testUpsertIncorrectInput()
    {
        $this->getConnectionMock(array('initConnection'))
            ->expects($this->any())
            ->method('initConnection')
            ->willReturn(true);

        $this->mockClient(array('upsert'));
        $this->mockApplyClientToConnection();

        Mage::getModel('tnw_salesforce/api_client')->upsert('Id', 'INCORRECT VALUE', 'Lead');
    }

    public function testUpsertInitConnectionBefore()
    {
        $this->getConnectionMock(array('initConnection'))
            ->expects($this->once())
            ->method('initConnection')
            ->willReturn(true);

        $this->mockClient(array('upsert'));
        $this->mockApplyClientToConnection();

        Mage::getModel('tnw_salesforce/api_client')->upsert('Id', array('test' => 'test'), 'Lead');
    }

    /**
     * @expectedException Mage_Core_Exception
     */
    public function testUpsertInitConnectionBeforeError()
    {
        $this->getConnectionMock(array('initConnection'))
            ->expects($this->once())
            ->method('initConnection')
            ->willReturn(false);

        $this->mockClient(array('upsert'));
        $this->mockApplyClientToConnection();

        Mage::getModel('tnw_salesforce/api_client')->upsert('Id', array('test' => 'test'), 'Lead');
    }
}