<?php

class TNW_Salesforce_Model_Account_Matching_Import
{
    /**
     * CSV delimiter
     */
    const CSV_SEPARATOR = ',';

    /**
     * Size chunk
     */
    const SIZE_CHUNK = 20;

    /**
     * @var array
     */
    protected $errorItems = array();

    /**
     * @var array
     */
    protected $successItems = array();

    /**
     * @var array
     */
    protected $accountIds = array();

    /**
     * @var array
     */
    protected $fieldMapping = array(
        'Account Name'  => 'account_name',
        'Account Id'    => 'account_id',
        'Email Domain'  => 'email_domain',
    );

    /**
     * @param $fileName
     * @throws Exception
     */
    public function importByFilename($fileName)
    {
        if (!$this->_validateFile($fileName)) {
            Mage::throwException(sprintf('Import file (%s) not found', str_replace(BP, '', $fileName)));
        }

        $parser = new Varien_File_Csv();
        $parser->setDelimiter(self::CSV_SEPARATOR);
        $data = $parser->getData($fileName);
        $columnName = array_map(array($this, 'fieldMapping'), array_shift($data));
        if (empty($columnName)) {
            Mage::throwException(sprintf('Import file (%s) empty', str_replace(BP, '', $fileName)));
        }

        // Validate
        foreach(array_chunk($data, self::SIZE_CHUNK, true) as $chunk) {
            $this->_validateChunk($chunk, $columnName);
        }

        if (count($this->errorItems) > 0) {
            return;
        }

        // Import
        foreach(array_chunk($data, self::SIZE_CHUNK, true) as $chunk) {
            $this->_importChunk($chunk, $columnName);
        }
    }

    /**
     * @param string $fieldName
     * @return string
     */
    public function fieldMapping($fieldName)
    {
        if (array_key_exists($fieldName, $this->fieldMapping)) {
            return $this->fieldMapping[$fieldName];
        }

        return $fieldName;
    }

    /**
     * @param $fileName
     * @return bool
     */
    protected function _validateFile($fileName)
    {
        return file_exists($fileName);
    }

    /**
     * @return TNW_Salesforce_Model_Account_Matching
     */
    protected static function _createEntityObject()
    {
        return Mage::getModel('tnw_salesforce/account_matching');
    }

    /**
     * @param array $chunk
     * @param array $columnName
     */
    protected function _importChunk(array $chunk, array $columnName)
    {
        foreach($chunk as $key => $row) {
            try {
                $this->_importEntity(array_combine($columnName, $row));
                $this->addSuccessItem('Success Import', $key+1);
            } catch(Exception $e) {
                $this->addErrorItem($e->getMessage(), $key+1);
            }
        }
    }

    /**
     * @param array $data
     * @return TNW_Salesforce_Model_Account_Matching
     * @throws Exception
     */
    protected function _importEntity(array $data)
    {
        $entity = self::_createEntityObject();
        $this->_prepareEntity($entity, $data);
        return $entity->save();
    }

    /**
     * @param TNW_Salesforce_Model_Account_Matching $entity
     * @param array $data
     * @return mixed
     */
    protected function _prepareEntity($entity, array $data)
    {
        if (isset($this->accountIds[$data['account_id']])){
            $data['account_name'] = $this->accountIds[$data['account_id']];
        }

        $entity->load($data['email_domain'], 'email_domain');
        return $entity->addData($data);
    }

    /**
     * @param array $chunk
     * @param array $columnName
     */
    protected function _validateChunk(array $chunk, array $columnName)
    {
        $accountId = array_filter(array_map(function($_item) use($columnName) {
            $_item = array_combine($columnName, $_item);
            return @$_item['account_id'];
        }, $chunk));

        $findId = array_diff($accountId, array_keys($this->accountIds));
        if (!empty($findId)) {
            /** @var TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection $collection */
            $collection = Mage::getModel('tnw_salesforce_api_entity/account')->getCollection();
            $collection->addFieldToFilter('Id', array('in' => $findId));

            $this->accountIds = array_merge($this->accountIds, $collection->setFullIdMode(true)->toOptionHashCustom());
        }

        foreach ($chunk as $key => $row) {
            try {
                $this->_validateEntity(array_combine($columnName, $row));
            } catch(InvalidArgumentException $e) {
                $this->addErrorItem('Inconsistency header data', $key+1);
            } catch(Exception $e) {
                $this->addErrorItem($e->getMessage(), $key+1);
            }
        }
    }

    /**
     * @param array $data
     * @return bool
     */
    protected function _validateEntity(array &$data)
    {
        $first =
            isset($data['account_id'])     &&
            !empty($data['account_id'])    &&
            isset($data['email_domain'])   &&
            !empty($data['email_domain']);

        if (!$first) {
            Mage::throwException('Missing or empty required fields: Account Id and Email Domain');
        }

        if (!isset($this->accountIds[$data['account_id']])){
            Mage::throwException('Account ID is not found to Salesforce');
        }
    }

    /**
     * @param $error
     * @param $errorLine
     */
    public function addErrorItem($error, $errorLine)
    {
        if (!isset($this->errorItems[$error])) {
            $this->errorItems[$error] = array();
        }

        $this->errorItems[$error][] = $errorLine;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errorItems;
    }

    /**
     * @param $success
     * @param $successLine
     */
    public function addSuccessItem($success, $successLine)
    {
        if (!isset($this->successItems[$success])) {
            $this->successItems[$success] = array();
        }

        $this->successItems[$success][] = $successLine;
    }

    /**
     * @return array
     */
    public function getSuccess()
    {
        return $this->successItems;
    }

    /**
     * @return array
     */
    public function getStatus()
    {
        return array(
            'success' => count($this->successItems),
            'error'   => count($this->errorItems),
        );
    }
}