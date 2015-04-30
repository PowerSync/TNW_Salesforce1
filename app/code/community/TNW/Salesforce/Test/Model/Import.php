<?php

class TNW_Salesforce_Test_Model_Import extends TNW_Salesforce_Test_Case
{
    /**
     * @return TNW_Salesforce_Model_Import
     */
    protected function getModel()
    {
        return Mage::getModel('tnw_salesforce/import');
    }

    /**
     * @loadFixture
     */
    public function testImportModel()
    {
        $entityId = 1;
        $model = $this->getModel()->load($entityId);
        $this->assertEquals($entityId, $model->getId());
        $collection = $model->getCollection()->addFieldToFilter($model->getIdFieldName(), $entityId);
        $this->assertEquals($entityId, $collection->getFirstItem()->getId());
    }

    /**
     * @singleton core/session
     *
     * @dataProvider dataProvider
     *
     * @param string $type
     * @param int $isPersonAccount
     * @param string $personEmail
     * @param string $email
     */
    public function testProcessImport($type, $isPersonAccount, $personEmail, $email)
    {
        if (empty($personEmail)) {
            $personEmail = '';
        }
        if (empty($email)) {
            $email = '';
        }

        $object = $this->arrayToObject(array(
            'attributes' => $this->arrayToObject(array(
                'type' => $type,
            )),
            'IsPersonAccount' => $isPersonAccount,
            'PersonEmail' => $personEmail,
            'Email' => $email,
        ));

        $expectations = $this->expected('%s-%s-%s-%s', $type, $isPersonAccount, $personEmail, $email);
        $process = intval($expectations->getData('process'));
        if ($expectations->getData('helper')) { //if helper is used to process
            $helperAlias = $expectations->getData('helper');
            $mockHelper = $this->getHelperMock($helperAlias, array('process'), false, array(), '', false);
            $mockHelper->expects($this->exactly($process))
                ->method('process')
                ->with($this->equalTo($object));
            $this->replaceByMock('helper', $helperAlias, $mockHelper);

        } elseif ($expectations->getData('model')) { //if model is used to process
            $modelAlias = $expectations->getData('model');
            $mockModel = $this->getModelMock($modelAlias, array('process', 'setObject'));
            $mockModel->expects($this->exactly($process))
                ->method('setObject')
                ->with($this->equalTo($object))
                ->will($this->returnSelf());
            $mockModel->expects($this->exactly($process))
                ->method('process');
            $this->replaceByMock('model', $modelAlias, $mockModel);
        }

        $sessionMock = $this->getModelMock('core/session', array('setFromSalesForce'), false, array(), '', false);
        $sessionMock->expects($this->exactly($process * 2)) // set true and false
            ->method('setFromSalesForce')
            ->withConsecutive(
                array($this->equalTo(true)),
                array($this->equalTo(false))
            );
        $this->replaceByMock('singleton', 'core/session', $sessionMock);

        $this->getModel()
            ->setObject($object)
            ->process();
    }

    public function testPrimaryKey()
    {
        //save one model before to check autogenerate primary keys and do not duplicate them
        $firstModel = $this->getModel()->addData(array(
            'json' => '{}',
        ))->save();
        //reload model to check id
        $firstModel = $this->getModel()->load($firstModel->getId());

        $this->assertNotEmpty($firstModel->getId());
        $this->assertEquals(28, strlen($firstModel->getId()));

        $secondModel = $this->getModel()->save();
        //reload model to check id
        $secondModel = $this->getModel()->load($secondModel->getId());
        $this->assertNotEquals($firstModel->getId(), $secondModel->getId());
    }
}