<?php

class TNW_Salesforce_Test_Helper_Abandoned extends EcomDev_PHPUnit_Test_Case
{
    /**
     * @test
     * @dataProvider dataProvider
     */
    public function getDateLimit($abandonedLimit, $currentTimeString)
    {
        $internalFormat = Varien_Date::DATETIME_INTERNAL_FORMAT;
        $expectationsIndex = $abandonedLimit - 1;

        //change current datetime by data provider using mock
        $localeMock = $this->getModelMock('core/locale', array('utcDate'));
        $localeMock->expects($this->any())
            ->method('utcDate')
            ->will($this->returnValue(new Zend_Date($currentTimeString, $internalFormat)));
        $this->replaceByMock('singleton', 'core/locale', $localeMock);

        //replace app locale with mock
        $reflection = new ReflectionProperty(Mage::app(), '_locale');
        $reflection->setAccessible(true);
        $reflection->setValue(Mage::app(), $localeMock);
        $reflection->setAccessible(false);

        //replace abandoned date limit config
        $configPath = TNW_Salesforce_Helper_Abandoned::ABANDONED_SYNC;
        $reflection = new ReflectionProperty(Mage::app()->getStore(), '_configCache');
        $reflection->setAccessible(true);
        $_configCache = $reflection->getValue(Mage::app()->getStore());
        $_configCache[$configPath] = $abandonedLimit;
        $reflection->setValue(Mage::app()->getStore(), $_configCache);
        $reflection->setAccessible(false);

        $expectedDateLimit = new Zend_Date($this->expected($expectationsIndex)->getDateLimit(), $internalFormat);
        $abandonedHelper = Mage::helper('tnw_salesforce/abandoned');

        $this->assertEquals($expectedDateLimit->getIso(), $abandonedHelper->getDateLimit()->getIso());
    }
}