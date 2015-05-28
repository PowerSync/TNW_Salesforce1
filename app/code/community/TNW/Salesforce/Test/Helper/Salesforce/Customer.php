<?php

class TNW_Salesforce_Test_Helper_Salesforce_Customer extends TNW_Salesforce_Test_Case
{
    /**
     * @loadFixture
     */
    public function testLeadCompanyNameByDomain()
    {
        $customerId = 1;
        $this->mockModel('core/session')->replaceByMock('singleton');

        $this->mockClient(array('upsert', 'query'));
        $this->mockApplyClientToConnection();

        $this->getClientMock()->expects($this->once())
            ->method('upsert')
            ->with(
                Mage::helper('tnw_salesforce/config')->getMagentoIdField(),
                array($this->arrayToObject(array(
                    Mage::helper('tnw_salesforce/config')->getMagentoIdField() => (string)$customerId,
                    'Company' => 'Fishchenko Inc.',
                ))),
                'Lead'
            );

        //mock get website
        $this->mockHelper('tnw_salesforce/magento_websites', array('getWebsiteSfId'))
            ->replaceByMock('helper')
            ->expects($this->any())
            ->method('getWebsiteSfId')
            ->will($this->returnCallback(function ($website) { return 'TEST' . $website->getCode(); }));
        $this->mockHelper('tnw_salesforce/salesforce_data_account', array('lookup', 'lookupByCriteria'))
            ->replaceByMock('helper')
            ->expects($this->any())
            ->method('lookupByCriteria')
            ->will($this->returnCallback(function ($criteria, $index, $key) {
                return array(key($criteria) =>
                    $this->getSalesforceFixture('account', array($key => current($criteria)), false, true));
            }));
        $this->mockHelper('tnw_salesforce/salesforce_data_contact', array('lookup'))->replaceByMock('helper');
        $this->mockHelper('tnw_salesforce/salesforce_data_lead', array('lookup'))->replaceByMock('helper');

        /** @var TNW_Salesforce_Helper_Salesforce_Customer $model */
        $model = Mage::helper('tnw_salesforce/salesforce_customer');

        $this->assertTrue($model->reset());
        $model->massAdd(array($customerId));
        $model->process();
    }
}