<?php

class TNW_Salesforce_Test_Model_Import_Order extends TNW_Salesforce_Test_Case
{
    /**
     * @loadFixture
     * @dataProvider dataProvider
     *
     * @param string $incrementId
     * @param string $salesforceStatus
     */
    public function testOrderProcess($incrementId, $salesforceStatus)
    {
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

    }

    /**
     * @loadFixture
     * @dataProvider dataProvider
     *
     * @param string $salesforceStatus
     */
    public function testStatusLogs($salesforceStatus)
    {
        $object = new stdClass();
        $object->Status = $salesforceStatus;
        $object->attributes = new stdClass();
        $object->attributes->type = 'Order';
        $object->BillingAddress = new stdClass();
        $object->BillingAddress->city = 'Novorossiysk';
        $object->BillingAddress->country = 'RU';
        $object->BillingAddress->postalCode = '353912';
        $object->BillingAddress->state = 'Krasnodar';
        $object->BillingAddress->street = 'Lenina 11';
        $object->ShippingAddress = new stdClass();
        $object->ShippingAddress->city = 'Taganrog';
        $object->ShippingAddress->country = 'RU';
        $object->ShippingAddress->postalCode = '347922';
        $object->ShippingAddress->state = 'Rostov';
        $object->ShippingAddress->street = 'Petrovskaya 11';

        $magentoId = Mage::helper('tnw_salesforce/config')->getMagentoIdField();
        $object->$magentoId = '100000100';

        $observerMock = $this->mockModel('tnw_salesforce/sale_notes_observer', array('notesPush'));
        $this->replaceByMock('singleton', 'tnw_salesforce/sale_notes_observer', $observerMock);

        $helperMock = $this->mockHelper('tnw_salesforce', array('log', 'canPush'));

        $expectedLog = $this->expected('%s', $salesforceStatus)->getData('log');
        if (is_array($expectedLog)) {
            $expectedLog = current($expectedLog);
        }

        if ($expectedLog) {
            $helperMock->expects($this->once())
                ->method('log')
                ->with($expectedLog, null, 'sf-trace');
        } else {
            $helperMock->expects($this->never())
                ->method('log');
        }

        $this->replaceByMock('helper', 'tnw_salesforce', $helperMock);

        Mage::getModel('tnw_salesforce/import_order')
            ->setObject($object)
            ->process();
    }
}