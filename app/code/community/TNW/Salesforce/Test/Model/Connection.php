<?php

class TNW_Salesforce_Test_Model_Connection extends EcomDev_PHPUnit_Test_Case
{
    /**
     * @test
     */
    public function testClientClass()
    {
        //do not use session
        $this->replaceByMock('singleton', 'adminhtml/session', $this->getModelMock('adminhtml/session'));

        //return isWorking true
        $helperMock = $this->getHelperMock('tnw_salesforce', array('isWorking'));
        $helperMock->expects($this->any())
            ->method('isWorking')
            ->will($this->returnValue(true));
        $this->replaceByMock('helper', 'tnw_salesforce', $helperMock);

        //return isConnected true
        $connectionMock = $this->getModelMock('tnw_salesforce/connection', array('isConnected'));
        $connectionMock->expects($this->any())
            ->method('isConnected')
            ->will($this->returnValue(true));
        $this->replaceByMock('model', 'tnw_salesforce/connection', $connectionMock);

        $connection = Mage::getModel('tnw_salesforce/connection');
        $this->assertInstanceOf('Salesforce_SforceEnterpriseClient', $connection->getClient());
    }
}