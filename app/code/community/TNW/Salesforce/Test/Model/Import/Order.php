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
        $object = new stdClass();
        $object->Id = '80124000000Cc2bAAC';
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
        $object->$magentoId = $incrementId;

        Mage::getModel('tnw_salesforce/import_order')
            ->setObject($object)
            ->process();

        $order = Mage::getModel('sales/order')->loadByIncrementId($object->$magentoId);

        $expectedStatus = $this->expected('%s-%s', $incrementId, $salesforceStatus)->getData('status');
        $this->assertEquals($expectedStatus, $order->getStatus());

        //check updated billing address
        $billingAddress = $order->getBillingAddress();
        $actualBillingAddress = array(
            'city' => $billingAddress->getCity(),
            'country' => $billingAddress->getCountryId(),
            'postalCode' => $billingAddress->getPostcode(),
            'state' => $billingAddress->getData('region'),
            'street' => $billingAddress->getStreet(-1),
        );
        $this->assertEquals($object->BillingAddress, $this->arrayToObject($actualBillingAddress));

        //check updated shipping address
        $shippingAddress = $order->getShippingAddress();
        $actualShippingAddress = array(
            'city' => $shippingAddress->getCity(),
            'country' => $shippingAddress->getCountryId(),
            'postalCode' => $shippingAddress->getPostcode(),
            'state' => $shippingAddress->getData('region'),
            'street' => $shippingAddress->getStreet(-1),
        );
        $this->assertEquals($object->ShippingAddress, $this->arrayToObject($actualShippingAddress));
    }
}