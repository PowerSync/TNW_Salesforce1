<?php

class TNW_Salesforce_Test_Model_Api_Entity_Account extends TNW_Salesforce_Test_Case
{
    /**
     * @singleton tnw_salesforce/connection
     *
     * @dataProvider dataProvider
     * @loadFixture
     * @test
     */
    public function loadAccount($accountId)
    {
        //prepare expectations - if no expectations defined use empty array
        try {
            $expectation = $this->expected($accountId)->getData();
        } catch (InvalidArgumentException $e) {
            $expectation = array();
        }

        $queryResponse = array();
        if ($fixture = $this->getSalesforceFixture('account', array('Id' => $accountId))) {
            $queryResponse[] = $fixture;
        }
        $this->mockQueryResponse($queryResponse);

        $entity = Mage::getModel('tnw_salesforce_api_entity/account');
        $entity->load($accountId);
        $this->assertEquals($expectation, $entity->getData());
    }

    /**
     * @singleton tnw_salesforce/connection
     *
     * @test
     * @loadFixture loadAccount
     */
    public function getCollection()
    {
        $queryResponse = new stdClass();
        $queryResponse->done = true;
        $queryResponse->records = $this->getSalesforceFixture('account', array(), true, true);
        $queryResponse->size = count($queryResponse->records);

        $this->mockClient(array('query'));
        $this->getClientMock()->expects($this->any())
            ->method('query')
            ->with('SELECT Id, OwnerId, Name FROM Account')
            ->will($this->returnValue($queryResponse));
        $this->mockApplyClientToConnection();

        $entity = Mage::getModel('tnw_salesforce_api_entity/account');
        /** @var TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection $collection */
        $collection = $entity->getCollection();

        //check qty of items
        $this->assertEquals(2, count($collection));

        //check getSize method
        $this->assertEquals(2, $collection->getSize());

        //check data of collection
        $this->assertEquals($this->expected('account')->getData(), $collection->getData());
    }

    /**
     * @test
     * @dataProvider dataProvider
     * @loadFixture loadAccount
     */
    public function idFilter($id)
    {
        $entity = Mage::getModel('tnw_salesforce_api_entity/account');

        $queryResponse = new stdClass();
        $queryResponse->done = true;
        $queryResponse->records = $this->getSalesforceFixture(
            'account', array($entity->getIdFieldName() => $id), true, true);
        $queryResponse->size = count($queryResponse->records);

        $this->mockClient(array('query'));
        $this->getClientMock()->expects($this->once())
            ->method('query')
            ->with('SELECT Id, OwnerId, Name FROM Account WHERE (Id = \'' . $id . '\')')
            ->will($this->returnValue($queryResponse));
        $this->mockApplyClientToConnection();

        $entity = Mage::getModel('tnw_salesforce_api_entity/account');
        /** @var TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection $collection */
        $collection = $entity->getCollection();
        $collection->addFieldToFilter($collection->getResource()->getIdFieldName(), $id);

        //check qty of items
        $this->assertEquals($queryResponse->size, count($collection));

        $this->assertEquals($id, $collection->getFirstItem()->getId());
    }
}