<?php

class TNW_Salesforce_Test_Model_Observer extends TNW_Salesforce_Test_Case
{
    public function testOrderPushOnce()
    {
        $iterations = 2;
        $iterator = 0;

        $orderId = 100;
        $type = 'salesforce';
        $helperAlias = 'tnw_salesforce/' . $type . '_order';

        //mock test_authentication
        $testAuthHelperMock = $this->getHelperMock('tnw_salesforce/test_authentication', array('getStorage'));
        $this->replaceByMock('helper', 'tnw_salesforce/test_authentication', $testAuthHelperMock);

        $mockSyncHelper = $this->getHelperMock($helperAlias, array('reset', 'massAdd', 'process'));
        $this->replaceByMock('helper', $helperAlias, $mockSyncHelper);

        $mockSyncHelper->expects($this->once())
            ->method('reset')
            ->willReturn(true);

        $mockSyncHelper->expects($this->once())
            ->method('massAdd')
            ->willReturn(true);

        //main checking - observer will be called twice, method will be executed only once
        $mockSyncHelper->expects($this->once())
            ->method('process');

        while (++$iterator <= $iterations) {
            $observerModel = Mage::getSingleton('tnw_salesforce/observer');
            $observer = new Varien_Event_Observer();
            $observer->setEvent(new Varien_Event(array(
                'orderIds' => array($orderId),
                'type' => $type,
                'message' => null,
                'isQueue' => false,
            )));
            $observerModel->pushOrder($observer);
        }
    }
}