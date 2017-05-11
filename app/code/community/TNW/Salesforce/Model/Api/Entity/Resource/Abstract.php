<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

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
     * @param Mage_Core_Model_Abstract $object
     * @return $this|Mage_Core_Model_Resource_Db_Abstract
     * @throws Exception
     */
    public function save(Mage_Core_Model_Abstract $object)
    {
        if ($this->isTableAvailable($this->getMainTable())) {

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
        }

        return $this;
    }

    /**
     * check connection load before
     *
     * @param Mage_Core_Model_Abstract $object
     * @param mixed $value
     * @param null $field
     * @return $this
     */
    public function load(Mage_Core_Model_Abstract $object, $value, $field = null)
    {
        if ($this->isTableAvailable($this->getMainTable())) {
            parent::load($object, $value, $field);
        }

        return $this;
    }

    /**
     * try to load deleted too
     *
     * @param Mage_Core_Model_Abstract $object
     * @param $value
     * @param null $field
     * @return Mage_Core_Model_Resource_Db_Abstract
     */
    public function loadAll(Mage_Core_Model_Abstract $object, $value, $field = null)
    {
        if ($this->isTableAvailable($this->getMainTable())) {

            $read = $this->_getReadAdapter();

            $read->setQueryAll(true);
            parent::load($object, $value, $field);
            $read->setQueryAll(false);
        }

        return $this;
    }


    /**
     * Get table name for the entity, validated by db adapter
     *
     * @param string $entityName
     * @return string
     */
    public function getTable($entityName)
    {
        if (is_array($entityName)) {
            $cacheName    = join('@', $entityName);
            list($entityName, $entitySuffix) = $entityName;
        } else {
            $cacheName    = $entityName;
            $entitySuffix = null;
        }

        if (isset($this->_tables[$cacheName])) {
            return $this->_tables[$cacheName];
        }

        if (strpos($entityName, '/')) {
            if (!is_null($entitySuffix)) {
                $modelEntity = array($entityName, $entitySuffix);
            } else {
                $modelEntity = $entityName;
            }
            $this->_tables[$cacheName] = $this->getTableName($modelEntity);
        } else if (!empty($this->_resourceModel)) {
            $entityName = sprintf('%s/%s', $this->_resourceModel, $entityName);
            if (!is_null($entitySuffix)) {
                $modelEntity = array($entityName, $entitySuffix);
            } else {
                $modelEntity = $entityName;
            }
            $this->_tables[$cacheName] = $this->getTableName($modelEntity);
        } else {
            if (!is_null($entitySuffix)) {
                $entityName .= '_' . $entitySuffix;
            }
            $this->_tables[$cacheName] = $entityName;
        }
        return $this->_tables[$cacheName];
    }


    /**
     * Get resource table name, validated by db adapter
     *
     * @param   string|array $modelEntity
     * @return  string
     */
    public function getTableName($modelEntity)
    {
        $tableSuffix = null;
        if (is_array($modelEntity)) {
            list($modelEntity, $tableSuffix) = $modelEntity;
        }

        $parts = explode('/', $modelEntity);
        if (isset($parts[1])) {
            list($model, $entity) = $parts;
            $entityConfig = false;
            if (!empty(Mage::getConfig()->getNode()->global->models->{$model}->resourceModel)) {
                $resourceModel = (string)Mage::getConfig()->getNode()->global->models->{$model}->resourceModel;
                $entityConfig  = Mage::getSingleton('core/resource')->getEntity($resourceModel, $entity);
            }

            if ($entityConfig && !empty($entityConfig->table)) {
                $tableName = (string)$entityConfig->table;
            } else {
                Mage::throwException(Mage::helper('core')->__('Can\'t retrieve entity config: %s', $modelEntity));
            }
        } else {
            $tableName = $modelEntity;
        }

        Mage::dispatchEvent('resource_get_tablename', array(
            'resource'      => Mage::getSingleton('core/resource'),
            'model_entity'  => $modelEntity,
            'table_name'    => $tableName,
            'table_suffix'  => $tableSuffix
        ));

        $mappedTableName = Mage::getSingleton('core/resource')->getMappedTableName($tableName);
        if ($mappedTableName) {
            $tableName = $mappedTableName;
        } else {
            $tableName =  $tableName;
        }

        if (!is_null($tableSuffix)) {
            $tableName .= '_' . $tableSuffix;
        }
        return Mage::getSingleton('core/resource')->getConnection(Mage_Core_Model_Resource::DEFAULT_READ_RESOURCE)->getTableName($tableName);
    }

    /**
     * is table exists?
     *
     * @param null $tableName
     * @return bool
     */
    public function isTableAvailable($tableName = null)
    {
        $tableDescription = null;

        if (empty($tableName)) {
            $tableName = $this->getMainTable();
        }

        if (Mage::helper('tnw_salesforce/salesforce_data')->getClient()) {

            $tableDescription = $this->_getReadAdapter()->describeTable($tableName);
        }

        return !empty($tableDescription);
    }
}
