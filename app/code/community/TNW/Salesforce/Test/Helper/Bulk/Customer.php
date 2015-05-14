<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 *
 * Class TNW_Salesforce_Test_Helper_Bulk_Customer
 */

class TNW_Salesforce_Test_Helper_Bulk_Customer extends TNW_Salesforce_Test_Bulkcase
{

    /**
     * @comment test a bug: if 2 customers have same Company name - new account should be assigned for the both
     * @comment at the present time account assign to the fiest one only
     * @comment test based on the fixture data. Customer default mapping should be defined
     *
     * @loadFixture
     */
    public function testProcess()
    {
        $this->_prepareClientData();

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

        $salesforceServerDomain = 'https://localhost:443';

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

        $this->_bulkClient = $this->mockClass('Zend_Http_Client', array('request', 'setRawData'));

        $this->_bulkClient->expects($spy = $this->any())
            ->method('setRawData')
            ->will($this->returnCallback($this->setRawData()));

        $this->_bulkClient->expects($this->any())
            ->method('request')
            ->will($this->returnCallback($this->request()));


        $syncHelper->expects($this->any())
            ->method('getHttpClient')
            ->will($this->returnValue($this->_bulkClient));

        $syncHelper->setIsFromCLI(true);
        $this->assertTrue($syncHelper->reset());

        $syncHelper->massAdd($customerIds);
        $syncHelper->process();

        //check if needed log found
        $found = false;
        $contactPushTransaction = $this->expected('contact-push');
        foreach ($spy->getInvocations() as $invocation) {
            if ($this->trimXmlString($invocation->parameters[0]) == $this->trimXmlString($contactPushTransaction->getRequest())) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

    }

}