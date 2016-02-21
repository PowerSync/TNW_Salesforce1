<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Test_Model_Abandoned extends EcomDev_PHPUnit_Test_Case
{
    /**
     * @test
     */
    public function getAbandonedCollectionInstance()
    {
        $abandonedQuotes = Mage::getModel('tnw_salesforce/abandoned')->getAbandonedCollection();
        $this->assertInstanceOf('Mage_Reports_Model_Resource_Quote_Collection', $abandonedQuotes);
    }

    /**
     * @test
     * @loadFixture
     * @dataProvider dataProvider
     */
    public function getAbandonedCollection($abandonedLimit, $currentTimeString, $dateLimit)
    {
        $expectationsIndex = $abandonedLimit - 1;
        //change limit config by data provider using mock
        $abandonedHelperMock = $this->getHelperMock('twn_salesforce/abandoned', array('getDateLimit'));
        $abandonedHelperMock->expects($this->any())
            ->method('getDateLimit')
            ->will($this->returnValue(new Zend_Date($dateLimit, Varien_Date::DATETIME_INTERNAL_FORMAT)));
        $this->replaceByMock('helper', 'tnw_salesforce/abandoned', $abandonedHelperMock);

        //change current datetime by data provider using mock
        $localeMock = $this->getModelMock('core/locale', array('utcDate'));
        $localeMock->expects($this->any())
            ->method('utcDate')
            ->will($this->returnValue(new Zend_Date($currentTimeString, Varien_Date::DATETIME_INTERNAL_FORMAT)));
        $this->replaceByMock('singleton', 'core/locale', $localeMock);

        $abandonedQuotes = Mage::getModel('tnw_salesforce/abandoned')->getAbandonedCollection();
        $this->assertEquals($this->expected($expectationsIndex)->getCount(), count($abandonedQuotes));
    }
}