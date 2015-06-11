<?php

class TNW_Salesforce_Test_Model_Mapping extends TNW_Salesforce_Test_Case
{
    /**
     * @return TNW_Salesforce_Model_Mapping
     */
    protected function getModel()
    {
        return Mage::getModel('tnw_salesforce/mapping');
    }

    /**
     * @loadFixture
     * @dataProvider dataProvider
     */
    public function testLocalFieldsAfterLoad($mappingId)
    {
        $model = $this->getModel()->load($mappingId);
        foreach (array(
                     'getLocalFieldType',
                     'getLocalFieldAttributeCode',
                 ) as $method) {
            $this->assertEquals($this->expected('mapping-%s', $mappingId)->$method(), $model->$method());
        }
    }

    /**
     * @loadFixture testLocalFieldsAfterLoad
     */
    public function testLocalFieldsAfterLoadCollection()
    {
        $collection = $this->getModel()->getCollection();
        foreach ($collection as $model) {
            foreach (array(
                         'getLocalFieldType',
                         'getLocalFieldAttributeCode',
                     ) as $method) {
                $this->assertEquals($this->expected('mapping-%s', $model->getId())->$method(), $model->$method());
            }
        }
    }

    public function testProcessedDefaultValue()
    {
        $model = $this->getModel();
        $expected = array(
            //provided => expected
            'text value' => 'text value',
            '{{url}}' => Mage::helper('core/url')->getCurrentUrl(),
            '{{today}}' => gmdate('Y-m-d'),
            '{{end of month}}' => gmdate('Y-m-d', mktime(0, 0, 0, date('n') + 1, 0, date('Y'))),
            '{{contact id}}' => null,
            '{{store view name}}' => Mage::app()->getStore()->getName(),
            '{{store group name}}' => Mage::app()->getStore()->getGroup()->getName(),
            '{{website name}}' => Mage::app()->getWebsite()->getName(),

        );

        foreach ($expected as $defaultValue => $expectation) {
            $model->setDefaultValue($defaultValue);
            $this->assertEquals($expectation, $model->getProcessedDefaultValue());
        }
    }

    public function testGetCustomValue()
    {
        $model = $this->getModel();
        $expected = array(
            //provided attributeCode => expected
            'text value' => null,
            'current_url' => Mage::helper('core/url')->getCurrentUrl(),
            'todays_date' => gmdate('Y-m-d'),
            'todays_timestamp' => gmdate(DATE_ATOM),
            'end_of_month' => gmdate('Y-m-d', mktime(0, 0, 0, date('n') + 1, 0, date('Y'))),
            'store_view_name' => Mage::app()->getStore()->getName(),
            'store_group_name' => Mage::app()->getStore()->getGroup()->getName(),
            'website_name' => Mage::app()->getWebsite()->getName(),

        );

        foreach ($expected as $attributeCode => $expectation) {
            $model->setLocalFieldAttributeCode($attributeCode);
            $this->assertEquals($expectation, $model->getCustomValue(), 'Incorrect ' . $attributeCode);
        }

        $store = Mage::getModel('core/store');
        $store->setName('Some Test Store');
        $model->setLocalFieldAttributeCode('store_view_name');
        $this->assertEquals('Some Test Store', $model->getCustomValue($store));
    }

    /**
     * @loadFixture
     * @dataProvider dataProvider
     *
     * @param $mappingId
     */
    public function testGetValue($mappingId)
    {
        $order = Mage::getModel('sales/order')->load(1);
        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
        $objectMappings = array(
            'Store' => $order->getStore(),
            'Order' => $order,
            'Payment' => $order->getPayment(),
            'Customer' => $customer,
            'Customer Group' => Mage::getModel('customer/group')->load($customer->getGroupId()),
            'Billing' => $order->getBillingAddress(),
            'Shipping' => $order->getShippingAddress(),
        );
        $value = $this->getModel()->load($mappingId)->getValue($objectMappings);
        $expected = $this->expected('mapping-%s', $mappingId)->getValue();
        if ($expected == '!order_description') {
            $expected = Mage::helper('tnw_salesforce/mapping')->getOrderDescription($order);
        } elseif ($expected == '!order_notes') {
            $allNotes = '';
            foreach ($order->getStatusHistoryCollection() as $historyItem) {
                $comment = trim(strip_tags($historyItem->getComment()));
                if (!$comment || empty($comment)) {
                    continue;
                }
                $allNotes .= Mage::helper('core')->formatTime($historyItem->getCreatedAtDate(), 'medium')
                    . " | " . $historyItem->getStatusLabel() . "\n";
                $allNotes .= strip_tags($historyItem->getComment()) . "\n";
                $allNotes .= "-----------------------------------------\n\n";
            }
            $expected = $allNotes;
        }
        $this->assertEquals($expected, $value);
    }

    /**
     * @loadFixture testGetValue
     */
    public function testGetAllValues()
    {
        $order = Mage::getModel('sales/order')->load(1);
        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
        $objectMappings = array(
            'Store' => $order->getStore(),
            'Order' => $order,
            'Payment' => $order->getPayment(),
            'Customer' => $customer,
            'Customer Group' => Mage::getModel('customer/group')->load($customer->getGroupId()),
            'Billing' => $order->getBillingAddress(),
            'Shipping' => $order->getShippingAddress(),
        );

        $collection = $this->getModel()->getCollection()
            ->addObjectToFilter(TNW_Salesforce_Model_Config_Objects::ORDER_OBJECT);
        //check that fixture loaded properly
        $this->assertNotEquals(0, count($collection));

        $expectation = array();
        foreach ($collection as $item) {
            $expectation[$item->getSfField()] = $item->getValue($objectMappings);
        }

        $this->assertEquals($expectation, $collection->getAllValues($objectMappings));
    }
}