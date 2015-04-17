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
        $expected = $expected === 'null' ? null : (bool)$expected;

        $this->mockApplyClientToConnection();
        $this->mockQueryResponse($this->getSalesforceFixture('lead', array('Email' => $email), true));

        $converted = Mage::helper('tnw_salesforce/salesforce_data')->isLeadConverted($email);

        $this->assertTrue($expected === $converted);
    }
}