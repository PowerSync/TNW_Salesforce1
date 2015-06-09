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
            '{{today}}' => date('Y-m-d', Mage::getModel('core/date')->timestamp(time())),
            '{{end of month}}' => date('Y-m-d', mktime(0, 0, 0, date('n') + 1, 0, date('Y'))),
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
}