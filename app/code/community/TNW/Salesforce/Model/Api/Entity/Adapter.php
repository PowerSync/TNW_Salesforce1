<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Api_Entity_Adapter
    extends Zend_Db_Adapter_Abstract
    /*implements Varien_Db_Adapter_Interface*/
{
    protected $_autoQuoteIdentifiers = false;

    /**
     * @var bool
     */
    protected $_queryAll = false;

    /**
     * Returns the symbol the adapter uses for delimited identifiers.
     *
     * @return string
     */
    public function getQuoteIdentifierSymbol()
    {
        return '';
    }

    /**
     * @return TNW_Salesforce_Model_Api_Client
     */
    protected function _getClient()
    {
        return Mage::getSingleton('tnw_salesforce/api_client');
    }

    /**
     * Fetches the first row of the SQL result.
     * Uses the current fetchMode for the adapter.
     *
     * @param string|Zend_Db_Select $sql An SQL SELECT statement.
     * @param mixed $bind Data to bind into SELECT placeholders.
     * @param mixed $fetchMode Override current fetch mode.
     * @return array
     */
    public function fetchRow($sql, $bind = array(), $fetchMode = null)
    {
        if ($sql instanceof Zend_Db_Select) {
            $sql = $sql->assemble();
        }

        try {
            if (!$this->isQueryAll()) {
                $response = $this->_getClient()->query($sql);
            } else {
                $response = $this->_getClient()->queryAll($sql);
            }
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Salesforce connection failed: " . $e->getMessage());
        }

        if (isset($response[0])) {
            return $response[0];
        } else {
            return array();
        }
    }

    /**
     * Fetches all SQL result rows as a sequential array.
     * Uses the current fetchMode for the adapter.
     *
     * @param string|Zend_Db_Select $sql An SQL SELECT statement.
     * @param mixed $bind Data to bind into SELECT placeholders.
     * @param mixed $fetchMode Override current fetch mode.
     * @return array
     */
    public function fetchAll($sql, $bind = array(), $fetchMode = null)
    {
        try {
            if (!$this->isQueryAll()) {
                $response = $this->_getClient()->query($sql);
            } else {
                $response = $this->_getClient()->queryAll($sql);
            }
        } catch (Exception $e) {
            $response = array();
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Salesforce connect failed: " . $e->getMessage());
        }

        return $response;
    }

    /**
     * Fetches the first column of the first row of the SQL result.
     *
     * @param string|Zend_Db_Select $sql An SQL SELECT statement.
     * @param mixed $bind Data to bind into SELECT placeholders.
     * @return string
     */
    public function fetchOne($sql, $bind = array())
    {
        $row = $this->fetchRow($sql, $bind);

        if (isset($row['any'])) {
            $row = $row['any'];
        }

        return is_array($row) ? current($row) : null;
    }

    /**
     * Check for config options that are mandatory.
     * Throw exceptions if any are missing.
     *
     * @param array $config
     * @throws Zend_Db_Adapter_Exception
     */
    protected function _checkRequiredOptions(array $config)
    {
    }

    public function getTransactionLevel()
    {
        return 0;
    }

    /**
     * Returns a list of the tables in the database.
     *
     * @return array
     */
    public function listTables()
    {
        return array();
    }

    public function describeTable($tableName, $schemaName = null)
    {
        try {
            $data = Mage::helper('tnw_salesforce/salesforce_data')->getClient()->describeSObject($tableName);
        } catch (Exception $e) {
            /**
             * some tables can be not available for our module, so, return empty array in this case
             */
            $data = array();
        }

        return $data;
    }

    /**
     * Creates a connection to the database.
     *
     * @return void
     */
    protected function _connect()
    {
    }

    /**
     * Test if a connection is active
     *
     * @return boolean
     */
    public function isConnected()
    {
        return true;
    }

    /**
     * Force the connection to close.
     *
     * @return void
     */
    public function closeConnection()
    {
    }

    /**
     * Prepare a statement and return a PDOStatement-like object.
     *
     * @param string|Zend_Db_Select $sql SQL query
     * @return Zend_Db_Statement|PDOStatement
     */
    public function prepare($sql)
    {
        return new Zend_Db_Statement_Pdo($this, $sql);
    }


    /**
     * Gets the last ID generated automatically by an IDENTITY/AUTOINCREMENT column.
     *
     * As a convention, on RDBMS brands that support sequences
     * (e.g. Oracle, PostgreSQL, DB2), this method forms the name of a sequence
     * from the arguments and returns the last id generated by that sequence.
     * On RDBMS brands that support IDENTITY/AUTOINCREMENT columns, this method
     * returns the last value generated for such a column, and the table name
     * argument is disregarded.
     *
     * @param string $tableName OPTIONAL Name of table.
     * @param string $primaryKey OPTIONAL Name of primary key column.
     * @return string
     */
    public function lastInsertId($tableName = null, $primaryKey = null)
    {
        return '';
    }

    /**
     * Begin a transaction.
     */
    protected function _beginTransaction()
    {

    }

    /**
     * Commit a transaction.
     */
    protected function _commit()
    {

    }

    /**
     * Roll-back a transaction.
     */
    protected function _rollBack()
    {

    }

    /**
     * Set the fetch mode.
     *
     * @param integer $mode
     * @return void
     * @throws Zend_Db_Adapter_Exception
     */
    public function setFetchMode($mode)
    {
    }

    /**
     * Adds an adapter-specific LIMIT clause to the SELECT statement.
     *
     * @param mixed $sql
     * @param integer $count
     * @param integer $offset
     * @return string
     */
    public function limit($sql, $count, $offset = 0)
    {
        $count = intval($count);
        if ($count <= 0) {
            /**
             * @see Zend_Db_Adapter_Mysqli_Exception
             */
            #require_once 'Zend/Db/Adapter/Mysqli/Exception.php';
            throw new Zend_Db_Adapter_Mysqli_Exception("LIMIT argument count=$count is not valid");
        }

        $offset = intval($offset);
        if ($offset < 0) {
            /**
             * @see Zend_Db_Adapter_Mysqli_Exception
             */
            #require_once 'Zend/Db/Adapter/Mysqli/Exception.php';
            throw new Zend_Db_Adapter_Mysqli_Exception("LIMIT argument offset=$offset is not valid");
        }

        $sql .= " LIMIT $count";
        if ($offset > 0) {
            $sql .= " OFFSET $offset";
        }

        return $sql;

    }

    /**
     * Check if the adapter supports real SQL parameters.
     *
     * @param string $type 'positional' or 'named'
     * @return bool
     */
    public function supportsParameters($type)
    {
        return false;
    }

    /**
     * Retrieve server version in PHP style
     *
     * @return string
     */
    public function getServerVersion()
    {
        return '1';
    }

    /**
     * Creates and returns a new Zend_Db_Select object for this adapter.
     *
     * @return Zend_Db_Select
     */
    public function select()
    {
        return new Varien_Db_Select($this);
    }

    public function supportStraightJoin()
    {
        return false;
    }

    /**
     * Build SQL statement for condition
     *
     * If $condition integer or string - exact value will be filtered ('eq' condition)
     *
     * If $condition is array is - one of the following structures is expected:
     * - array("eq" => $equalValue)
     * - array("neq" => $notEqualValue)
     * - array("like" => $likeValue)
     * - array("in" => array($inValues))
     * - array("nin" => array($notInValues))
     * - array("notnull" => $valueIsNotNull)
     * - array("null" => $valueIsNull)
     * - array("gt" => $greaterValue)
     * - array("lt" => $lessValue)
     * - array("gteq" => $greaterOrEqualValue)
     * - array("lteq" => $lessOrEqualValue)
     * - array("finset" => $valueInSet)
     * - array("regexp" => $regularExpression)
     *
     * If non matched - sequential array is expected and OR conditions
     * will be built using above mentioned structure
     *
     * @param string|array $fieldName
     * @param integer|string|array $condition
     * @return string
     */
    public function prepareSqlCondition($fieldName, $condition)
    {
        $conditionKeyMap = array(
            'eq' => "{{fieldName}} = ?",
            'neq' => "{{fieldName}} != ?",
            'like' => "{{fieldName}} LIKE ?",
            'nlike' => "{{fieldName}} NOT LIKE ?",
            'in' => "{{fieldName}} IN(?)",
            'nin' => "{{fieldName}} NOT IN(?)",
            'is' => "{{fieldName}} IS ?",
            'notnull' => "{{fieldName}} IS NOT NULL",
            'null' => "{{fieldName}} IS NULL",
            'gt' => "{{fieldName}} > ?",
            'lt' => "{{fieldName}} < ?",
            'gteq' => "{{fieldName}} >= ?",
            'lteq' => "{{fieldName}} <= ?",
            'finset' => "FIND_IN_SET(?, {{fieldName}})",
            'regexp' => "{{fieldName}} REGEXP ?",
            'from' => "{{fieldName}} >= ?",
            'to' => "{{fieldName}} <= ?",
        );

        $query = '';
        if (is_array($condition)) {
            if (isset($condition['field_expr'])) {
                $fieldName = str_replace('#?', $this->quoteIdentifier($fieldName), $condition['field_expr']);
                unset($condition['field_expr']);
            }
            $key = key(array_intersect_key($condition, $conditionKeyMap));

            if (isset($condition['from']) || isset($condition['to'])) {
                if (isset($condition['from'])) {
                    $from = $this->_prepareSqlDateCondition($condition, 'from');
                    $query = $this->_prepareQuotedSqlCondition($conditionKeyMap['from'], $from, $fieldName);
                }

                if (isset($condition['to'])) {
                    $query .= empty($query) ? '' : ' AND ';
                    $to = $this->_prepareSqlDateCondition($condition, 'to');
                    $query = $this->_prepareQuotedSqlCondition($query . $conditionKeyMap['to'], $to, $fieldName);
                }
            } elseif (array_key_exists($key, $conditionKeyMap)) {
                $value = $condition[$key];
                $query = $this->_prepareQuotedSqlCondition($conditionKeyMap[$key], $value, $fieldName);
            } else {
                $queries = array();
                foreach ($condition as $orCondition) {
                    $queries[] = sprintf('(%s)', $this->prepareSqlCondition($fieldName, $orCondition));
                }

                $query = sprintf('(%s)', implode(' OR ', $queries));
            }
        } else {
            $query = $this->_prepareQuotedSqlCondition($conditionKeyMap['eq'], (string)$condition, $fieldName);
        }

        return $query;
    }

    /**
     * Prepare Sql condition
     *
     * @param  $text Condition value
     * @param  mixed $value
     * @param  string $fieldName
     * @return string
     */
    protected function _prepareQuotedSqlCondition($text, $value, $fieldName)
    {
        $sql = $this->quoteInto($text, $value);
        $sql = str_replace('{{fieldName}}', $fieldName, $sql);
        return $sql;
    }

    public function prepareColumnValue($column, $fieldValue)
    {
        return $fieldValue;
    }

    /**
     * @return boolean
     */
    public function isQueryAll()
    {
        return $this->_queryAll;
    }

    /**
     * @param boolean $queryAll
     * @return $this
     */
    public function setQueryAll($queryAll)
    {
        $this->_queryAll = $queryAll;

        return $this;
    }

    /**
     * Prepare sql date condition
     *
     * @param array $condition
     * @param string $key
     * @return string
     */
    protected function _prepareSqlDateCondition($condition, $key)
    {
        if (empty($condition['date'])) {
            if (empty($condition['datetime'])) {
                $result = $condition[$key];
            } else {
                $result = $this->formatDate($condition[$key], isset($condition['datetime']));
            }
        } else {
            $result = $this->formatDate($condition[$key], isset($condition['datetime']));
        }

        return $result;
    }

    /**
     * Format Date to internal database date format
     *
     * @param int|string|Zend_Date $date
     * @param boolean $includeTime
     * @return Zend_Db_Expr
     */
    public function formatDate($date, $includeTime = true)
    {
        if ($includeTime) {
            $date = gmdate(DATE_ATOM, strtotime($date));
            return new Zend_Db_Expr($date);
        }

        $date = date('Y-m-d', strtotime($date));
        return new Zend_Db_Expr($date);
    }
}