<?php

/**
 * class for working with mysql table with objects for synchronization with sf
 *
 * Class TNW_Salesforce_Model_Localstorage
 */
class TNW_Salesforce_Model_Localstorage extends TNW_Salesforce_Helper_Abstract
{
    protected $_mageModels = array();

    public function __construct()
    {
        if (empty($this->_mageModels)) {
            $this->_mageModels['order'] = 'sales/order';
            $this->_mageModels['abandoned'] = 'sales/quote';
            $this->_mageModels['customer'] = 'customer/customer';
            $this->_mageModels['product'] = 'catalog/product';
            $this->_mageModels['website'] = 'core/website';
            $this->_mageModels['invoice'] = 'sales/order_invoice';
        }
    }

    /**
     * @comment get entity model name by the alias
     * @return array|string|null
     */
    public function getMageModels($type = null)
    {
        $return = $type;

        if (!$type) {
            $return = $this->_mageModels;
        } elseif (isset($this->_mageModels[$type])) {
            $return = $this->_mageModels[$type];
        }

        return $return;
    }

    public function getAllDependencies()
    {
        $_dependencies = array();
        $_sql = "SELECT object_id, sf_object_type FROM " . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . " WHERE mage_object_type IN ('" . $this->_mageModels['customer'] . "', '" . $this->_mageModels['product'] . "')";
        $_results = $this->getDbConnection('read')->query($_sql)->fetchAll();
        foreach ($_results as $_result) {
            $_dependencies[$_result['sf_object_type']][] = $_result['object_id'];
        }
        return $_dependencies;
    }

    public function updateQueue($_orderIds, $_queueIds, $_results, $_alternativeKeyes = array())
    {
        $_errorsSet = array();
        $_successSet = array();

        foreach ($_results as $_object => $_responses) {
            foreach ($_responses as $_entityKey => $_response) {
                $_key = (!empty($_alternativeKeyes)) ? $_queueIds[array_search(array_search($_entityKey, $_alternativeKeyes), $_orderIds)] : $_queueIds[array_search($_entityKey, $_orderIds)];

                /**
                 * @comment change variable type to avoid problems with stdClass
                 */
                $_response = (array)$_response;

                if (
                    array_key_exists('success', $_response)
                    && (string)$_response['success'] == "false"
                    && array_key_exists('errors', $_response)
                ) {
                    if (!array_key_exists($_key, $_errorsSet)) {
                        $_errorsSet[$_key] = array();
                    }

                    $errorMessage = '';
                    if (is_array($_response['errors']) && isset($_response['errors']['message'])) {
                        $errorMessage = $_response['errors']['message'];
                    } elseif (is_array($_response['errors'])) {
                        foreach ($_response['errors'] as $errorStdClass) {
                            if (is_object($errorStdClass)) {
                                $errorMessage .= $errorStdClass->message;
                            }
                        }
                    }

                    $_errorsSet[$_key][] = '(' . $_object . ') ' . $errorMessage;
                } else {
                    // Reset the status from error back to running so we can delete
                    if (!in_array($_key, $_successSet)) {
                        $_successSet[] = $_key;
                    }
                }
            }
        }

        $_sql = '';

        if (!empty($_successSet)) {
            $_sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . "`"
                . " SET message='', date_sync = '" . gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(time())) . "', status = 'success' WHERE id IN ('" . join("','", $_successSet) . "');";
        }

        if (!empty($_errorsSet)) {
            foreach ($_errorsSet as $_id => $_errors) {
                $_sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . "`"
                    . " SET message='" . urlencode(serialize($_errors)) . "', date_sync = '" . gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(time())) . "', sync_attempt = sync_attempt + 1, status = 'sync_error' WHERE id = '" . $_id . "';";
            }
        }

        if (!empty($_sql)) {
            Mage::helper('tnw_salesforce')->log("SQL: " . $_sql);
            $this->getDbConnection()->query($_sql);
        }

        // Delete Successful
        $sql = "DELETE FROM `" . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . "` WHERE status = 'success';";
        Mage::helper('tnw_salesforce')->getDbConnection('delete')->query($sql);
        Mage::helper('tnw_salesforce')->log("Synchronized records removed from the queue ...", 1, 'sf-cron');
    }

    /**
     * iterate array by reference and add commas to element
     * handy for sql queries
     *
     * @param array $data
     * @return array
     */
    public function addComma(array $data = array())
    {
        foreach ($data as &$value) {
            $value = "'$value'";
        }

        return $data;
    }

    /**
     * count object number by type
     *
     * @param array $sfObjectType
     * @return int|mixed
     */
    public function countObjectBySfType(array $sfObjectType = array())
    {
        $table = Mage::getSingleton('core/resource')->getTableName('tnw_salesforce_queue_storage');
        $typeSet = $this->addComma($sfObjectType);
        $typeList = empty($typeSet) ? '' : implode(", ", $typeSet);
        $sql = "SELECT count(*) as total from $table where sf_object_type in ($typeList)";
        try {
            $res = $this->getDbConnection('read')->query($sql)->fetchAll();
            $res = array_pop($res);
            $res = intval($res['total']);
        } catch (Exception $e) {
            $res = 0;
        }

        return $res;
    }

    /**
     * update object status by id in one sql query
     *
     * @param array $idSet
     * @param string $status
     * @return mixed
     */
    public function updateObjectStatusById(array $idSet = array(), $status = 'sync_running')
    {
        $idLine = empty($idSet) ? "" : "'" . join("', '", $idSet) . "'";

        $additinalUpdate = '';
        if ($status == 'sync_running') {
            $additinalUpdate = ', sync_attempt = sync_attempt + 1 ';
        } elseif ($status == 'new') {
            $additinalUpdate = ', sync_attempt = 1 ';
        }

        $sql = "UPDATE " . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . " SET status = '" . $status . "' $additinalUpdate where id in (" . $idLine . ")";
        $res = $this->getDbConnection()->query($sql);

        return $res;
    }

    /**
     * remove objects by id
     * plain sql used to avoid magento collection foreach loop
     *
     * @param array $objectId
     * @return bool
     */
    public function deleteObject($objectId = array(), $_isForced = false)
    {
        try {
            if (empty($objectId)) {
                Mage::helper('tnw_salesforce')->log("Deletion Objects are empty!");
                return true;
            }

            $sql = "DELETE FROM `" . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . "` WHERE id IN ('" . join("', '", $objectId) . "')";
            if (!$_isForced) {
                $sql .= " AND status = 'success'";
            }
            Mage::helper('tnw_salesforce')->log("SQL: " . $sql, 1, 'sf-cron');
            $this->getDbConnection('delete')->query($sql);

        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("ERROR quote from queue: " . $e->getMessage(), 1, 'sf-cron');
        }

        return true;
    }

    public function getObject($_objectId = NULL)
    {
        // ToDo: read from Queue
        // return false assuming nothing is found in the queue
        return false;
    }

    /**
     * insert / update object in table for future sf synchronization
     *
     * @param array $idSet
     * @param $sfObType
     * @param $mageObType
     * @return bool
     */
    public function addObject(array $idSet = array(), $sfObType, $mageObType)
    {
        // TODO: Need to rewrite to use insertMultiple
        // TODO: (Trello) https://trello.com/c/mJkQlYv3/144-performance-rewrite-addobject-to-insert-multiple-rows-with-1-query-to-optimize-performance
        // save to table

        $entityModelAlias = $this->getMageModels($mageObType);

        /**
         * @var $entityModel Mage_Sales_Model_Order|Mage_Catalog_Model_Product
         */
        $entityModel = Mage::getModel($entityModelAlias);

        /**
         * @var $collection Mage_Sales_Model_Resource_Order_Collection|Mage_Catalog_Model_Resource_Product_Collection
         */
        $collection = $entityModel->getCollection();

        $_chunks = array_chunk($idSet, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT);
        unset($itemIds);
        try {
            foreach ($_chunks as $_chunk) {

                $collection->resetData();

                $select = $collection->getSelect();
                $inCond = $collection->getConnection()->prepareSqlCondition($entityModel->getIdFieldName(), array('in' => $_chunk));

                $select->where($inCond);

                $select->reset(Zend_Db_Select::COLUMNS);

                $columns = array(
                    'object_id' => $entityModel->getIdFieldName(),
                    'mage_object_type' => new Zend_Db_Expr('"' . $entityModelAlias . '"'),
                    'sf_object_type' => new Zend_Db_Expr('"' . $sfObType . '"'),
                    'date_created' => new Zend_Db_Expr('"' . Mage::helper('tnw_salesforce')->getDate() . '"')
                );

                $select->columns($columns);

                $query = $select->insertFromSelect(
                    Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage'),
                    array_keys($columns)
                );

                Mage::helper('tnw_salesforce')->getDbConnection('write')->query($query);
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * pattern decorator used
     * this method should be used if we add product to localstorage due to the fact of special filters when we add new products
     * because we should filter grouped and configurable products which are not allowed to be synced with sf
     *
     * @param array $idSet
     * @param $sfObType
     * @param $mageObType
     * @return bool
     */
    public function addObjectProduct(array $idSet = array(), $sfObType, $mageObType)
    {
        // we filter grouped and configurable products
        $productsCollection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToFilter('entity_id', array('in' => $idSet))
            ->addAttributeToSelect('salesforce_disable_sync')
            ->addAttributeToFilter(
                array(
                    array('attribute'=> 'salesforce_disable_sync', 'neq' => '1'),
                    array('attribute'=> 'salesforce_disable_sync', 'null' => true),
                ),
                null,
                'left'
            );

        $idSetFiltered = $productsCollection->getAllIds();

        return $this->addObject($idSetFiltered, $sfObType, $mageObType);
    }
}
