<?php

class TNW_Salesforce_Test_Helper_Bulk_Customer extends TNW_Salesforce_Test_Case
{
    /**
     * @loadFixture
     */
    public function testProcess()
    {
        $this->mockClient(array('upsert', 'query'));
        $this->mockConnection(array('initConnection', 'isLoggedIn', 'tryWsdl', 'tryToConnect', 'tryToLogin'));
        $this->mockApplyClientToConnection();

        //return true by isLoggedIn / tryWsdl / tryToConnect / tryToLogin
        foreach (array(
                     'isLoggedIn',
                     'tryWsdl',
                     'tryToConnect',
                     'tryToLogin',
                 ) as $method) {
            $this->getConnectionMock()->expects($this->any())
                ->method($method)
                ->will($this->returnValue(true));
        }

        //make website helper stub
        $websiteId = 'TESTWEBSITE';
        $websiteHelper = $this->getHelperMock('tnw_salesforce/magento_websites', array('getWebsiteSfId'));
        $websiteHelper->expects($this->any())
            ->method('getWebsiteSfId')
            ->will($this->returnValue($websiteId));
        $this->replaceByMock('helper', 'tnw_salesforce/magento_websites', $websiteHelper);

        //make core/session dummy
        $this->replaceByMock('singleton', 'core/session', $this->getModelMock('core/session'));


        $eavData = $this->getFixture()->getStorage()->getData('local_fixture', 'eav');
        $customersData = $eavData['customer'];

        $customerIds = array();
        foreach ($customersData as $customerData) {
            $customerIds[] = $customerData['entity_id'];
        }


//        //$instance_url = explode('/', $this->getConnectionMock()->getServerUrl());
        $salesforceServerDomain = Mage::app()->getStore()->getBaseUrl() . '/';

        /**
         * @var $syncHelper TNW_Salesforce_Helper_Bulk_Customer|EcomDev_PHPUnit_Mock_Proxy
         */
        $syncHelper = $this->getHelperMock('tnw_salesforce/bulk_customer', array('getSalesforceSessionId', 'getSalesforceServerDomain', 'getHttpClient'));

        $syncHelper->expects($this->any())
            ->method('getSalesforceSessionId')
            ->will($this->returnValue(true));

        $syncHelper->expects($this->any())
            ->method('getSalesforceServerDomain')
            ->will($this->returnValue($salesforceServerDomain));

        $syncHelper->expects($this->any())
            ->method('getHttpClient')
            ->will($this->returnCallback(function() {
                $bulkClient = $this->mockClass('Zend_Http_Client', array('request'));

                $response = new Varien_Object();
                $response->setBody('12321321312');

                $bulkClient->expects($this->any())
                    ->method('request')
                    ->will($this->returnValue($response));

                return $bulkClient;
            }));

        $class = new ReflectionClass($syncHelper);
        $method = $class->getMethod('getHttpClient');

        $syncHelper->setIsFromCLI(true);
        $this->assertTrue($syncHelper->reset());

        $syncHelper->massAdd($customerIds);
        $syncHelper->process();


    }

    public function getClient()
    {

    }
}