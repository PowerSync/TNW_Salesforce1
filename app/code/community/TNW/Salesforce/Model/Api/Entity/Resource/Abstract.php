<?php

abstract class TNW_Salesforce_Model_Api_Entity_Resource_Abstract extends Mage_Core_Model_Resource_Db_Abstract
{
    protected $_columns = array();

    protected $_resourcePrefix = 'tnw_salesforce_api_entity';

    protected function _getConnection($connectionName)
    {
        return Mage::getSingleton('tnw_salesforce/api_entity_adapter');
    }

    public function getDefaultColumns()
    {
        $columns = $this->_columns;
        if (!isset($this->_columns[$this->getIdFieldName()])) {
            array_unshift($columns, $this->getIdFieldName());
        }

        return (array)$columns;
    }

    /**
     * Retrieve select object for load object data
     *
     * @param string $field
     * @param mixed $value
     * @param Mage_Core_Model_Abstract $object
     * @return Zend_Db_Select
     */
    protected function _getLoadSelect($field, $value, $object)
    {
        $field  = $this->_getReadAdapter()->quoteIdentifier($field);
        $select = $this->_getReadAdapter()->select()
            ->from($this->getMainTable(), array_map(function ($value) {
                return new Zend_Db_Expr($value);
            }, $this->getDefaultColumns()))
            ->where($field . '=?', $value);
        return $select;
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
        $data = array();
        $fields = $this->_getWriteAdapter()->describeTable($table);
        foreach ($fields as $field) {
            $fieldName = strtolower($field->name);

            $getter = 'get' . $field->name;
            $value = $object->$getter();

            if ($object->hasData($fieldName) || $object->hasData($field->name) || !empty($value)) {
                $fieldValue = $object->getData($fieldName);
                if (!empty($value)) {
                    $fieldValue = $value;
                } elseif (!$value) {
                    if ($object->hasData($fieldName)) {
                        $fieldValue = $object->getData($fieldName);
                    } elseif ($object->hasData($field->name)) {
                        $fieldValue = $object->getData($field->name);
                    }
                }

                if ($fieldValue instanceof Zend_Db_Expr) {
                    $data[$field->name] = $fieldValue;
                } else {
                    if (null !== $fieldValue) {
                        $fieldValue   = $this->_prepareTableValueForSave($fieldValue, $field);
                        $data[$field->name] = $this->_getWriteAdapter()->prepareColumnValue($field, $fieldValue);
                    } else if ($field->nillable) {
                        $data[$field->name] = null;
                    }
                }

            }
        }
        return $data;
    }

    protected function _prepareTableValueForSave($value, $type)
    {
        return $value;
    }

    /**
     * Prepare data for save
     *
     * @param Mage_Core_Model_Abstract $object
     * @return array
     */
    protected function _prepareDataForSave(Mage_Core_Model_Abstract $object)
    {
        $data = $this->_prepareDataForTable($object, $this->getMainTable());

        $result = new stdClass();
        foreach ($data as $field => $value) {
            $result->$field = $value;
        }

        return array($result);
    }

    /**
     * Save object object data
     *
     * @param Mage_Core_Model_Abstract $object
     * @return Mage_Core_Model_Resource_Db_Abstract
     */
    public function save(Mage_Core_Model_Abstract $object)
    {
        if ($object->isDeleted()) {
            return $this->delete($object);
        }

        $this->_serializeFields($object);
        $this->_beforeSave($object);
        $this->_checkUnique($object);

        $result = Mage::helper('tnw_salesforce/salesforce_data')
            ->getClient()
            ->upsert(
                $this->getIdFieldName(),
                $this->_prepareDataForSave($object),
                $this->getMainTable()
        );

        if (!$object->getId() && isset($result[0]) && property_exists($result[0], 'success')) {
            $object->setId($result[0]->id);
        }

        if (!$object->getId()) {
            throw new Exception('Salesforce object was not saved!');
        }

        $this->unserializeFields($object);
        $this->_afterSave($object);

        return $this;
    }

    public function loadAll(Mage_Core_Model_Abstract $object, $value, $field = null)
    {
        $read = $this->_getReadAdapter();

        $read->setQueryAll(true);
        $result = parent::load($object, $value, $field);
        $read->setQueryAll(false);

        return $result;
    }


}
