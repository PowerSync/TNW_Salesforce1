<?php

class TNW_Salesforce_Test_Helper_Salesforce_Data_Account extends EcomDev_PHPUnit_Test_Case
{
    /**
     * @test
     */
    public function setCustomer()
    {
        //do not connect on initialize helper
        $connectionMock = $this->getModelMock(
            'tnw_salesforce/connection',
            array(),
            false,
            array(),
            '',
            false
        );
        $this->replaceByMock('singleton', 'tnw_salesforce/connection', $connectionMock);

        $testString = 'Uber.Duber cool';

        //set test string and check return value
        $helper = Mage::helper('tnw_salesforce/salesforce_data_account');
        $returnValue = $helper->setCompany($testString);
        $this->assertTrue($helper === $returnValue, 'Helper doesn\'t return yourself on setCompany');

        //make company name in helper accessible
        $reflection = new ReflectionProperty($helper, '_companyName');
        $reflection->setAccessible(true);

        $this->assertEquals($testString, $reflection->getValue($helper));
    }
}