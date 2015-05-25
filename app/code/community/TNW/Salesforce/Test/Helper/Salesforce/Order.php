<?php

class TNW_Salesforce_Test_Helper_Salesforce_Order extends TNW_Salesforce_Test_Case
{
    /**
     * @dataProvider dataProvider
     * @loadFixture
     *
     * @param int $orderId
     * @param string $type Sync type: bulk or salesforce
     */
    public function testLogOnProcess($orderId, $type)
    {
        $order = Mage::getModel('sales/order')->load($orderId);
        $email = $order->getCustomerEmail();

        $this->mockClient(array('upsert', 'query', 'convertLead'));
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

        //mock lead data helper
        $leadDataHelper = $this->getHelperMock('tnw_salesforce/salesforce_data_lead', array('lookup'));
        $this->replaceByMock('helper', 'tnw_salesforce/salesforce_data_lead', $leadDataHelper);

        //find lead by lookup
        $foundLead = $this->getSalesforceFixture('lead', array('Email' => $email), false, true);
        $leadDataHelper->expects($this->any())
            ->method('lookup')
            ->with(array('guest-0' => $email), array('guest-0' => $websiteId))
            ->will($this->returnValue($foundLead ? array($websiteId => array($email => $foundLead)) : array()));

        //convert lead and return account id
        $accountId = 'SOMEACCOUNTID';
        $this->getClientMock()->expects($this->once())
            ->method('convertLead')
            ->with(array($this->arrayToObject(array(
                'leadId' => $foundLead->Id,
                'convertedStatus' => Mage::getStoreConfig('salesforce_customer/lead_config/customer_lead_status'),
                'doNotCreateOpportunity' => 'true',
                'overwriteLeadSource' => 'false',
                'sendNotificationEmail' => 'false',
                'ownerId' => null,
            ))))
            ->will($this->returnValue($this->arrayToObject(array('result' => array($this->arrayToObject(array(
                'contactId' => 'SOMECONTACTID',
                'accountId' => $accountId,
                'success' => true,
            )))))));

        //always return isWorking = true
        $helperMock = $this->getHelperMock('tnw_salesforce', array('log', 'isWorking'));
        $this->replaceByMock('helper', 'tnw_salesforce', $helperMock);
        $helperMock->expects($this->any())
            ->method('isWorking')
            ->willReturn(true);

        //write all logs to check after
        $helperMock->expects($spy = $this->any())
            ->method('log');

        // Execute test code here
        /** @var TNW_Salesforce_Helper_Salesforce_Order $syncHelper */
        $syncHelper = Mage::helper('tnw_salesforce/' . $type . '_order');
        $syncHelper->setIsFromCLI(true);
        $this->assertTrue($syncHelper->reset());
        $syncHelper->massAdd($orderId);
        $syncHelper->process('full');

        //check if needed log found
        $found = false;
        foreach ($spy->getInvocations() as $invocation) {
            if ($invocation->parameters[0] == "Order Object: AccountId = '$accountId'") {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }
}