<?php

class TNW_Salesforce_Test_Model_Api_Function extends EcomDev_PHPUnit_Test_Case
{
    /**
     * @test
     */
    public function convertLead()
    {
        $apiClientMock = $this->getModelMock('tnw_salesforce/api_client', array('convertLead'));
        $apiClientMock->expects($this->once())
            ->method('convertLead')
            ->will($this->returnValue(new Varien_Object()));
        $this->replaceByMock('singleton', 'tnw_salesforce/api_client', $apiClientMock);

        //prepare model
        $api = Mage::getModel('tnw_salesforce/api_function');

        $response = $api->convertLead(array());

        $this->assertInstanceOf('Varien_Object', $response);
    }
}