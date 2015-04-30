<?php

class TNW_Salesforce_Test_Model_Import extends TNW_Salesforce_Test_Case
{
    /**
     * @loadFixture
     */
    public function testImportModel()
    {
        $entityId = 1;
        $model = Mage::getModel('tnw_salesforce/import')->load($entityId);
        $this->assertEquals($entityId, $model->getId());
        $collection = $model->getCollection()->addFieldToFilter($model->getIdFieldName(), $entityId);
        $this->assertEquals($entityId, $collection->getFirstItem()->getId());
    }
}