<?php

class TNW_Salesforce_Test_Model_Cached extends TNW_Salesforce_Test_Case
{
    /**
     * @loadFixture
     * @test
     */
    public function loadCached()
    {
        $leadIds = array(
            'LEAD1',
            'LEAD2',
            'LEAD1',
        );

        $modelMock = $this->getModelMock('tnw_salesforce_api_entity/lead', array('load'));

        //ensure that load is called 2 times instead of 3 with correct parameters
        $modelMock->expects($this->exactly(count(array_unique($leadIds))))
            ->method('load')
            ->withConsecutive(
                array('LEAD1', null),
                array('LEAD2', null)
            )
            ->will($this->returnCallback(function ($value) {
                return new Varien_Object($this->getSalesforceFixture('lead', array('ID' => $value)));
            }));
        $this->replaceByMock('model', 'tnw_salesforce_api_entity/lead', $modelMock);

        $this->mockApplyClientToConnection();

        foreach ($leadIds as $_leadId) {
            /** @var TNW_Salesforce_Model_Cached $cached */
            $cached = Mage::getSingleton('tnw_salesforce/cached');
            $data = $cached->load('tnw_salesforce_api_entity/lead', $_leadId);
            $this->assertEquals($this->expected()->getData($_leadId), $data->getData(),
                'Incorrect value on id: ' . $_leadId);
        }
    }
}