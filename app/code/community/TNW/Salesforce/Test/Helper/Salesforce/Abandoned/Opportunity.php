<?php

class TNW_Salesforce_Test_Helper_Salesforce_Abandoned_Opportunity extends TNW_Salesforce_Test_Case
{
    /**
     * Check contact roles set properly
     *
     * @helper tnw_salesforce/salesforce_abandoned_opportunity
     *
     * @loadFixture
     * @dataProvider dataProvider
     *
     * @param string $quoteId
     */
    public function testAbandonedContactRoles($quoteId)
    {
        $opportunityId = 'TESTOPPORTUNITY1';
        $quote = Mage::getModel('sales/quote')->load($quoteId);
        $email = $quote->getCustomer()->getEmail();
        $websiteId = 'TESTWEBSITE1';

        //mock session
        $sessionMock = $this->getModelMock('core/session', array('setFromSalesForce'), false, array(), '', false);
        $this->replaceByMock('singleton', 'core/session', $sessionMock);

        //mock connection and client
        $this->mockClient(array('upsert', 'convertLead'));
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

        //mock lookup contact
        $contactDataHelper = $this->getHelperMock('tnw_salesforce/salesforce_data_contact', array('lookup'));
        $this->replaceByMock('helper', 'tnw_salesforce/salesforce_data_contact', $contactDataHelper);

        //mock lookup lead
        $leadDataHelper = $this->getHelperMock('tnw_salesforce/salesforce_data_lead', array('lookup'));
        $this->replaceByMock('helper', 'tnw_salesforce/salesforce_data_lead', $leadDataHelper);
        $foundLead = $this->getSalesforceFixture('lead', array('Email' => $email), false, true);
        if ($foundLead) {
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
                    'contactId' => $this->expected('quote-' . $quoteId)->getData('salesforce_contact'),
                    'accountId' => 'SOMEACCOUNTID',
                    'success' => true,
                )))))));
        }
        $leadDataHelper->expects($this->any())
            ->method('lookup')
            ->with(array($quote->getCustomerId() => $email), array($quote->getCustomerId() => $websiteId))
            ->will($this->returnValue($foundLead ? array($websiteId => array($email => $foundLead)) : array()));

        //mock lookup account
        $accountDataHelper = $this->getHelperMock('tnw_salesforce/salesforce_data_account', array('lookup'));
        $this->replaceByMock('helper', 'tnw_salesforce/salesforce_data_account', $accountDataHelper);

        //user active
        $userDataHelper = $this->getHelperMock('tnw_salesforce/salesforce_data_user', array('isUserActive'));
        $this->replaceByMock('helper', 'tnw_salesforce/salesforce_data_user', $userDataHelper);
        $userDataHelper->expects($this->any())
            ->method('isUserActive')
            ->willReturn(true);

        //mock website helper
        $websiteHelper = $this->getHelperMock('tnw_salesforce/magento_websites', array('getWebsiteSfId'));
        $websiteHelper->expects($this->any())
            ->method('getWebsiteSfId')
            ->will($this->returnValue($websiteId));
        $this->replaceByMock('helper', 'tnw_salesforce/magento_websites', $websiteHelper);

        //mock getStandardPricebookId and opportunityLookup
        $helperSalesforceData = $this->getHelperMock('tnw_salesforce/salesforce_data',
            array('getStandardPricebookId', 'opportunityLookup'));
        $priceBookId = 'TESTPRICEBOOK1';
        $helperSalesforceData->expects($this->any())
            ->method('getStandardPricebookId')
            ->will($this->returnValue($priceBookId));
        $this->replaceByMock('helper', 'tnw_salesforce/salesforce_data', $helperSalesforceData);

        $closeDate = new Zend_Date($quote->getUpdatedAt(), Varien_Date::DATETIME_INTERNAL_FORMAT);
        $closeDate->addDay(Mage::helper('tnw_salesforce/abandoned')->getAbandonedCloseTimeAfter($quote));

        $this->getClientMock()->expects($this->exactly(2))
            ->method('upsert')
            ->withConsecutive(
                array(
                    Mage::helper('tnw_salesforce/config')->getMagentoIdField(),
                    array($this->arrayToObject(array(
                        'StageName' => 'Committed',
                        Mage::helper('tnw_salesforce/config')->getMagentoWebsiteField()
                            => $websiteId,
                        Mage::helper('tnw_salesforce/config')->getMagentoIdField()
                            => TNW_Salesforce_Helper_Abandoned::ABANDONED_CART_ID_PREFIX . $quoteId,
                        'Pricebook2Id' => null,
                        'CloseDate' => gmdate(DATE_ATOM, $closeDate->getTimestamp()),
                        'AccountId' => $foundLead ? 'SOMEACCOUNTID' : null,
                        'Name' => 'Abandoned Cart #' . $quoteId,
                        'OwnerId' => null,
                    ))), 'Opportunity'), //insert opportunity
                array('Id', array($this->arrayToObject(array(
                    'IsPrimary' => true,
                    'OpportunityId' => $opportunityId,
                    'Role' => Mage::helper('tnw_salesforce/abandoned')->getDefaultCustomerRole(),
                    'ContactId' => $this->expected('quote-' . $quoteId)->getData('salesforce_contact'),
                ))), 'OpportunityContactRole') //insert contact role
            )->will($this->onConsecutiveCalls(
                array(
                    $this->arrayToObject(array(
                        'success' => true,
                        'id' => $opportunityId,
                    ))
                ),
                array(
                    $this->arrayToObject(array(
                        'success' => true,
                    ))
                )
            ));

        $syncHelper = Mage::helper('tnw_salesforce/salesforce_abandoned_opportunity');
        $this->assertTrue($syncHelper->reset());
        $syncHelper->massAdd($quoteId, true);
        $syncHelper->process('full');
    }
}