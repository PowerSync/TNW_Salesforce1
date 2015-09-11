<?php

/**
 * @method TNW_Salesforce_Model_Api_Entity_Resource_Abstract getResource
 *
 * Class TNW_Salesforce_Model_Api_Entity_Resource_Collection_Abstract
 */
class TNW_Salesforce_Model_Api_Entity_Resource_Collection_Abstract
    extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Init collection select
     *
     * @return Mage_Core_Model_Resource_Db_Collection_Abstract
     */
    protected function _initSelect()
    {
        $this->getSelect()->from(
            $this->getMainTable(),
            array_map(function ($value) {
                return new Zend_Db_Expr($value);
            }, $this->getResource()->getDefaultColumns())
        );

        return $this;
    }

    /**
     * Get SQL for get record count
     *
     * @return Varien_Db_Select
     */
    public function getSelectCountSql()
    {
        $this->_renderFilters();

        $countSelect = clone $this->getSelect();
        $countSelect->reset(Zend_Db_Select::ORDER);
        $countSelect->reset(Zend_Db_Select::LIMIT_COUNT);
        $countSelect->reset(Zend_Db_Select::LIMIT_OFFSET);
        $countSelect->reset(Zend_Db_Select::COLUMNS);

        $countSelect->columns('COUNT(' . $this->getResource()->getIdFieldName() . ')');

        return $countSelect;
    }

    /**
     * Get collection size
     *
     * @return int
     */
    public function getSize()
    {
        return count($this);
    }

    protected function _prepareDataForSave($object)
    {
        $data = $this->_prepareDataForTable($object, $this->getMainTable());

        $result = new stdClass();
        foreach ($data as $field => $value) {
            $result->$field = $value;
        }

        return array($result);
    }

    /**
     * Prepare data for passed table
     *
     * @param Varien_Object $object
     * @param string $table
     * @return array
     */
    protected function _prepareDataForTable(Varien_Object $object, $table)
    {
        /**
         * @comment for some request we should send fields not existing in the main table
         */
        return $object->getData();
    }

    public function getIdFieldName()
    {
        if (!$this->_idFieldName) {
            $this->_setIdFieldName($this->getResource()->getIdFieldName());
        }

        return $this->_idFieldName;
    }


    /**
     * Save all the entities in the collection
     * @return $this
     * @throws Exception
     */
    public function save()
    {
        try {
            $dataForSave = array();

            foreach ($this->getItems() as $item) {
                $dataForSave[] = current($this->_prepareDataForSave($item));
            }

            $chunks = array_chunk($dataForSave, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT);
            unset($itemIds);
            foreach ($chunks as $chunk) {
                $result = Mage::helper('tnw_salesforce/salesforce_data')
                    ->getClient()
                    ->upsert(
                        $this->getIdFieldName(),
                        $chunk,
                        $this->getMainTable()
                    );
            }
        } catch (Exception $e) {
            throw $e;
        }

        return $this;
    }

    /**
     * Proces loaded collection data
     *
     * @return Varien_Data_Collection_Db
     */
    protected function _afterLoadData()
    {
        /**
         * prepare sql expression result if defined
         */
        foreach ($this->_data as &$data) {
            if (isset($data['any'])) {
                foreach ($data['any'] as $key => $expression) {
                    if (is_numeric($key)) {

                        /**
                         * remove "sf:" namespace prefix
                         */
                        $expression = preg_replace('|([<\s]+\/?\s*)\w+:|', '$1', $expression);
                        $expression = "<?xml version='1.0' standalone='yes'?><data>$expression</data>";

                        $xml = new Varien_Simplexml_Element($expression);
                        foreach ($xml->asCanonicalArray() as $subKey => $value) {
                            $data[$subKey] = $value;
                        }

                    } else {
                        $data[$key] = $expression;
                    }
                    unset($data['any'][$key]);
                }
                unset($data['any']);
            }
        }
        return $this;
    }
}