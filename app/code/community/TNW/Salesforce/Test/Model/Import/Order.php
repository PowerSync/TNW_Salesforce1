<?php

class TNW_Salesforce_Test_Model_Import_Order extends TNW_Salesforce_Test_Case
{
    /**
     * @singleton core/session
     *
     * @loadFixture
     * @dataProvider dataProvider
     *
     * @param string $incrementId
     * @param string $salesforceStatus
     */
    public function testOrderProcess($incrementId, $salesforceStatus)
    {
        $sessionMock = $this->getModelMock('core/session', array('getFromSalesForce'), false, array(), '', false);
        $sessionMock->expects($this->any())
            ->method('getFromSalesForce')
            ->will($this->returnValue(true));
        $this->replaceByMock('singleton', 'core/session', $sessionMock);

        $object = $this->arrayToObject(array(
            'Status' => $salesforceStatus,
            'attributes' => $this->arrayToObject(array(
                'type' => 'Order',
            )),
            'BillingStreet' => 'Lenina 11',
            'BillingCity' => 'Novorossiysk',
            'BillingState' => 'Krasnodar',
            'BillingCountry' => 'RU',
            'BillingPostalCode' => '353912',
            'ShippingStreet' => 'Petrovskaya 11',
            'ShippingCity' => 'Taganrog',
            'ShippingState' => 'Rostov',
            'ShippingCountry' => 'RU',
            'ShippingPostalCode' => '347922',
            'PoNumber' => '12345',
            'Description' => 'Some description',
            'Type' => 'Magento',
            //custom fields to check additional entities
            'Coupon' => 'TEST-COUPON',
            'CustomerEmail' => 'test@example.com',
        ));

        $magentoId = Mage::helper('tnw_salesforce/config')->getMagentoIdField();
        $object->$magentoId = $incrementId;

        Mage::getModel('tnw_salesforce/import_order')
            ->setObject($object)
            ->process();

        $order = Mage::getModel('sales/order')->loadByIncrementId($object->$magentoId);

        $expectedStatus = $this->expected('%s-%s', $incrementId, $salesforceStatus)->getData('status');
        $this->assertEquals($expectedStatus, $order->getStatus());

        //check updated billing address
        $billingAddress = $order->getBillingAddress();
        $this->assertEquals(array(
            'street' => $object->BillingStreet,
            'city' => $object->BillingCity,
            'state' => $object->BillingState,
            'country' => $object->BillingCountry,
            'postcode' => $object->BillingPostalCode,
        ), array(
            'street' => $billingAddress->getStreet(-1),
            'city' => $billingAddress->getCity(),
            'state' => $billingAddress->getData('region'),
            'country' => $billingAddress->getCountryId(),
            'postcode' => $billingAddress->getPostcode(),
        ));

        //check updated shipping address
        $shippingAddress = $order->getShippingAddress();
        $this->assertEquals(array(
            'street' => $object->ShippingStreet,
            'city' => $object->ShippingCity,
            'state' => $object->ShippingState,
            'country' => $object->ShippingCountry,
            'postcode' => $object->ShippingPostalCode,
        ), array(
            'street' => $shippingAddress->getStreet(-1),
            'city' => $shippingAddress->getCity(),
            'state' => $shippingAddress->getData('region'),
            'country' => $shippingAddress->getCountryId(),
            'postcode' => $shippingAddress->getPostcode(),
        ));

        //check payment field
        $this->assertEquals($object->PoNumber, $order->getPayment()->getPoNumber());

        //check order field
        $this->assertEquals($object->Coupon, $order->getCouponCode());

        //check customer field
        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
        $this->assertEquals($object->CustomerEmail, $customer->getEmail());
    }

    /**
     * @loadFixture
     * @dataProvider dataProvider
     *
     * @param string $salesforceStatus
     * @param string $orderIncrementId
     */
    public function testStatusLogs($salesforceStatus, $orderIncrementId)
    {
        $object = $this->arrayToObject(array(
            'Status' => $salesforceStatus,
            'attributes' => $this->arrayToObject(array(
                'type' => 'Order',
            )),
        ));

        $magentoId = Mage::helper('tnw_salesforce/config')->getMagentoIdField();
        $object->$magentoId = $orderIncrementId;

        //check that addStatusHistoryComment is executed
        $orderMock = $this->getModelMock('sales/order', array('addStatusHistoryComment', 'save'));
        $this->replaceByMock('model', 'sales/order', $orderMock);

        $expectedLog = $this->expected('%s-%s', $salesforceStatus, $orderIncrementId)->getData('log');
        if (is_array($expectedLog)) {
            $expectedLog = current($expectedLog);
        }

        if ($expectedLog) {
            $orderMock->expects($this->once())
                ->method('addStatusHistoryComment')
                ->with($expectedLog);
        } else {
            $orderMock->expects($this->never())
                ->method('addStatusHistoryComment');
        }

        Mage::getModel('tnw_salesforce/import_order')
            ->setObject($object)
            ->process();
    }

    /**
     * Yes it's a test of magento core method,
     * but it used in logic and it's better to make sure that it works properly
     *
     * @loadFixture
     */
    public function testSaveComment()
    {
        $orderId = 2000;
        $commentText = 'Some test comment';

        $order = Mage::getModel('sales/order')->load($orderId);
        $order->addStatusHistoryComment($commentText);
        $order->save();

        $reloadedOrder = Mage::getModel('sales/order')->load($orderId);

        //try to find added comment
        $found = false;
        foreach ($reloadedOrder->getAllStatusHistory() as $comment) {
            if ($comment->getComment() == $commentText) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }
}