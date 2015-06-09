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
}