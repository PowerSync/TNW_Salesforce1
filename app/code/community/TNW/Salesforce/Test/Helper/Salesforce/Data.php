<?php

class TNW_Salesforce_Test_Helper_Salesforce_Data extends TNW_Salesforce_Test_Case
{
    /**
     * @test
     * @dataProvider dataProvider
     * @loadFixture
     */
    public function isLeadConverted($email)
    {
        //prepare data provider data
        if ($email == 'null') {
            $email = null;
        }

        //prepare expected value
        $expected = $this->expected()->getData($email)['isConverted'];
        $expected = $expected === 'null' ? null : $expected;
        $expected = is_int($expected) ? (bool)$expected : $expected;

        $this->mockApplyClientToConnection();

        //$response = new stdClass();
        //$response->records = $this->getSalesforceFixture('lead', array('Email' => $email), true, true);

        //$this->getClientMock()->expects($this->once())
        //    ->method('query')
        //    ->will($this->returnValue($response));
        $this->mockQueryResponse($this->getSalesforceFixture('lead', array('Email' => $email), true));

        $converted = Mage::helper('tnw_salesforce/salesforce_data')->isLeadConverted($email);

        $this->assertTrue($expected === $converted);
    }

    /**
     * @test
     * @dataProvider dataProvider
     * @loadFixture
     */
    public function getAccountName($id)
    {
        //prepare data provider data
        if ($id == 'null') {
            $id = null;
        }

        //prepare expected value
        $expected = $this->expected()->getData($id)['Name'];
        $expected = $expected === 'null' ? null : $expected;

        $this->mockApplyClientToConnection();

        //$response = new stdClass();
        //$response->records = $this->getSalesforceFixture('account', array('Id' => $id), true, true);

        //$this->getClientMock()->expects($this->once())
        //    ->method('query')
        //    ->will($this->returnValue($response));
        $this->mockQueryResponse($this->getSalesforceFixture('account', array('Id' => $id), true));

        $name = Mage::helper('tnw_salesforce/salesforce_data')->getAccountName($id);

        $this->assertTrue($expected === $name);
    }
}