<?php

abstract class TNW_Salesforce_Test_Case extends EcomDev_PHPUnit_Test_Case
{
    /**
     * @var EcomDev_PHPUnit_Mock_Proxy
     */
    protected $_mockConnection;

    /**
     * @var EcomDev_PHPUnit_Mock_Proxy
     */
    protected $_mockClient;

    /**
     * Mock connection to allow using getClient()
     */
    public function mockConnection($methods = array())
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
        $methods[] = 'isConnected';
        $this->_mockConnection = $this->getModelMock('tnw_salesforce/connection', $methods);
        $this->_mockConnection->expects($this->any())
            ->method('isConnected')
            ->will($this->returnValue(true));
        $this->replaceByMock('model', 'tnw_salesforce/connection', $this->_mockConnection);
    }

    public function getConnectionMock()
    {
        if (is_null($this->_mockConnection)) {
            $this->mockConnection();
        }

        return $this->_mockConnection;
    }

    public function mockClass($className, $methods = array(), array $constructorArguments = array(),
                              $callOriginalConstructor = true, $callOriginalClone = true, $callAutoload = true
    ) {

        $mockBuilder = new EcomDev_PHPUnit_Mock_Proxy($this, $className, 'Mock_' . $className);

        if ($callOriginalConstructor === false) {
            $mockBuilder->disableOriginalConstructor();
        }

        if ($callOriginalClone === false) {
            $mockBuilder->disableOriginalClone();
        }

        if ($callAutoload === false) {
            $mockBuilder->disableAutoload();
        }

        $mockBuilder->setMethods($methods);
        $mockBuilder->setConstructorArgs($constructorArguments);

        return $mockBuilder->getMock();
    }

    public function mockClient($methods = array())
    {
        $this->_mockClient = $this->mockClass('Salesforce_SforceEnterpriseClient', $methods);
    }

    public function getClientMock()
    {
        if (is_null($this->_mockClient)) {
            $this->mockClient();
        }

        return $this->_mockClient;
    }

    public function mockApplyClientToConnection()
    {
        $reflection = new ReflectionProperty($this->getConnectionMock(), '_client');
        $reflection->setAccessible(true);
        $reflection->setValue($this->getConnectionMock(), $this->getClientMock());
        $reflection->setAccessible(false);
    }

    public function getSalesforceFixture($key, $filter, $all = false)
    {
        $resultAll = array();
        $data = Mage::registry('_fixture_data');
        if (isset($data[$key])) {
            foreach ($data[$key] as $_row) {
                foreach ($filter as $_key => $_value) {
                    if (isset($_row[$_key]) && $_row[$_key] == $_value) {
                        if (!$all) {
                            return $_row;
                        }

                        $resultAll[] = $_row;
                    }
                }
            }
        }

        return $resultAll;
    }
}