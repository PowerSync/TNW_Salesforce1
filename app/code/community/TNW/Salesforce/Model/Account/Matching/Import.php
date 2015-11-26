<?php

class TNW_Salesforce_Model_Account_Matching_Import
{
    /**
     * CSV delimiter
     */
    const CSV_SEPARATOR = ',';

    /**
     * @var array
     */
    protected $errorItems = array();

    /**
     * @var array
     */
    protected $fieldMapping = array();

    /**
     * @param $fileName
     * @throws Exception
     */
    public function importByFilename($fileName)
    {
        if ($this->_validateFile($fileName)) {
            Mage::throwException(sprintf('Import file (%s) not found', str_replace(BP, '', $fileName)));
        }

        $parser = new Varien_File_Csv();
        $parser->setDelimiter(self::CSV_SEPARATOR);
        $data = $parser->getData($fileName);

        $columnName = array_map(array($this, 'fieldMapping'), array_shift($data));
        if (empty($columnName)) {
            Mage::throwException(sprintf('Import file (%s) empty', str_replace(BP, '', $fileName)));
        }

        foreach($data as $row) {
            try {
                $this->_importEntity(array_combine($columnName, $row));
            } catch(InvalidArgumentException $e) {
                //todo
            } catch(Exception $e) {
                $this->addErrorItem('', $e->getMessage());
            }
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
     * @param array $data
     * @return TNW_Salesforce_Model_Account_Matching
     * @throws Exception
     */
    protected function _importEntity(array $data)
    {
        $model = self::_createEntityObject();
        $model->setData($data);
        $model->validate();
        return $model->save();
    }

    /**
     * @param $entity
     * @param $error
     */
    public function addErrorItem($entity, $error)
    {
        $this->errorItems[$entity] = $error;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errorItems;
    }

    /**
     * @return array
     */
    public function getStatus()
    {
        return array(
            'success' => array('count'=>0),
            'error' => array('count'=>0),
        );
    }
}