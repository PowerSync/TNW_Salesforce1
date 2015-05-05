<?php

class TNW_Salesforce_Test_Helper_Salesforce_Abandoned_Opportunity extends TNW_Salesforce_Test_Case
{
    /**
     * Check contact roles set properly
     *
     * @loadFixture
     * @dataProvider dataProvider
     *
     * @param string $quoteId
     */
    public function testAbandonedContactRoles($quoteId)
    {
        //mock session
        $sessionMock = $this->getModelMock('core/session', array('setFromSalesForce'), false, array(), '', false);
        $this->replaceByMock('singleton', 'core/session', $sessionMock);

        //mock connection and client
        $this->mockClient(array('upsert'));
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
        $websiteId = 'TESTWEBSITE1';
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

        $opportunityId = 'TESTOPPORTUNITY1';
        $quote = Mage::getModel('sales/quote')->load($quoteId);
        $closeDate = new Zend_Date($quote->getUpdatedAt(), Varien_Date::DATETIME_INTERNAL_FORMAT);
        $closeDate->addDay(Mage::helper('tnw_salesforce/abandoned')->getAbandonedCloseTimeAfter($quote));

        $this->getClientMock()->expects($this->exactly(2))
            ->method('upsert')
            ->withConsecutive(
                array(
                    Mage::helper('tnw_salesforce/config')->getMagentoIdField(),
                    array($this->arrayToObject(array(
                        Mage::helper('tnw_salesforce/config')->getMagentoWebsiteField()
                            => $websiteId,
                        Mage::helper('tnw_salesforce/config')->getMagentoIdField()
                            => TNW_Salesforce_Helper_Abandoned::ABANDONED_CART_ID_PREFIX . $quoteId,
                        'Pricebook2Id' => null,
                        'Name' => 'Abandoned Cart #1',
                        'AccountId' => null,
                        'OwnerId' => null,
                        'StageName' => 'Committed',
                        'CloseDate' => gmdate(DATE_ATOM, $closeDate->getTimestamp()),
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