<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

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

        $returnTrueMethods = array(
            'isConnected',
            'isLoggedIn',
            'tryWsdl',
            'tryToConnect',
            'tryToLogin',
        );

        $methods = array_merge($methods, $returnTrueMethods, array('initConnection'));

        $this->_mockConnection = $this->getModelMock('tnw_salesforce/connection', array_unique($methods));
        $this->replaceByMock('model', 'tnw_salesforce/connection', $this->_mockConnection);

        foreach ($returnTrueMethods as $method) {
            $this->_mockConnection->expects($this->any())
                ->method($method)
                ->willReturn(true);
        }
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

    /**
     * @param $key
     * @param array $filter
     * @param bool $all
     * @return array|mixed
     */
    public function getSalesforceFixture($key, $filter = array(), $all = false, $object = false)
    {
        $resultAll = array();
        $data = Mage::registry('_fixture_data');
        if (isset($data[$key])) {
            foreach ($data[$key] as $_row) {
                if (!empty($filter)) {
                    foreach ($filter as $_key => $_value) {
                        if (isset($_row[$_key]) && $_row[$_key] == $_value) {
                            $resultAll[] = $object ? $this->arrayToObject($_row) : $_row;
                        }
                    }
                } else {
                    $resultAll[] = $object ? $this->arrayToObject($_row) : $_row;
                }
            }
        }

        if ($all) {
            return $resultAll;
        } else {
            return current($resultAll);
        }
    }

    /**
     * @param $array
     *
     * @return stdClass
     */
    public function arrayToObject($array)
    {
        $result = new stdClass();
        foreach ($array as $key => $value) {
            $result->$key = $value;
        }

        return $result;
    }

    public function mockQueryResponse($response)
    {
        $mock = $this->getModelMock('tnw_salesforce/api_client', array('query'));
        $mock->expects($this->any())
            ->method('query')
            ->will($this->returnValue($response));
        $this->replaceByMock('singleton', 'tnw_salesforce/api_client', $mock);
    }
}