<?php

/**
 * class for working with mysql table with objects for synchronization with sf
 *
 * Class TNW_Salesforce_Model_Localstorage
 */
class TNW_Salesforce_Model_Localstorage extends TNW_Salesforce_Helper_Abstract
{
    protected $_mageModels = array();

    public function __construct() {
        if (empty($this->_mageModels)) {
            $this->_mageModels['order']     =   'sales/order';
            $this->_mageModels['customer']  =   'customer/customer';
            $this->_mageModels['product']   =   'catalog/product';
            $this->_mageModels['website']   =   'core/website';
        }
    }

    public function getAllDependencies() {
        if (!$this->_write) {
            $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        }
        $_dependencies = array();
        $_sql = "SELECT object_id, sf_object_type FROM " . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . " WHERE mage_object_type IN ('" . $this->_mageModels['customer'] . "', '" . $this->_mageModels['product'] . "')";
        $_results = $this->_write->query($_sql)->fetchAll();
        foreach ($_results as $_result) {
            $_dependencies[$_result['sf_object_type']][] = $_result['object_id'];
        }
        return $_dependencies;
    }

    public function updateQueue($_orderIds, $_queueIds, $_results, $_alternativeKeyes = array()) {
        $_errorsSet = array();
        $_successSet = array();

        foreach($_results as $_object => $_responses) {
            foreach ($_responses as $_entityKey => $_response) {
                $_key = (!empty($_alternativeKeyes)) ? $_queueIds[array_search(array_search($_entityKey, $_alternativeKeyes), $_orderIds)] : $_queueIds[array_search($_entityKey, $_orderIds)];

                /**
                 * @comment change variable type to avoid problems with stdClass
                 */
                $_response = (array)$_response;

                if (array_key_exists('success', $_response) && $_response['success'] == "false") {
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

                    $_errorsSet[$_key][] =  '(' . $_object . ') '. $errorMessage;
                } else {
                    // Reset the status from error back to running so we can delete
                    if (!in_array($_key,$_successSet)) {
                        $_successSet[] = $_key;
                    }
                }
            }
        }

           if (!$this->_write) {
            $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        }
        $_sql = '';

// Commented because all items already have the 'sync_running' status
//        if (!empty($_successSet)) {
//            $_sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . "`"
//                . " SET message='', status = 'sync_running' WHERE id IN ('" . join("','", $_successSet) . "');";
//        }

        if (!empty($_errorsSet)) {
            foreach ($_errorsSet as $_id => $_errors) {
                $_sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . "`"
                    . " SET message='" . urlencode(serialize($_errors)) . "', date_sync = '" . gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(time())) . "', sync_attempt = sync_attempt + 1, status = 'sync_error' WHERE id = '" . $_id . "';";
            }
        }

        if (!empty($_sql)) {
            $this->_write->query($_sql);
        }
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
        $sql = "select count(*) as total from $table where sf_object_type in ($typeList)";
        try {
            $res = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($sql);
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
        $idLine = empty($idSet) ? "" : "'" . join("', '", $idSet) ."'";
        $sql = "UPDATE " . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . " SET status = '" . $status . "' where id in (" . $idLine . ")";
        if (!$this->_write) {
            $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        }
        $res = $this->_write->query($sql);

        return $res;
    }

    /**
     * remove objects by id
     * plain sql used to avoid magento collection foreach loop
     *
     * @param array $objectId
     * @return bool
     */
    public function deleteObject(array $objectId = array())
    {
        try {
            if (empty($objectId)) {
                return true;
            }

            if (!$this->_write) {
                $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
            }
/*
            $session = Mage::getSingleton('core/session');

            $errors = $session->getTnwSalesforceErrors();

            if (!empty($errors)) {

                foreach ($errors as $errorMessage => $errorData) {
                    if (empty($errorData)) {
                        continue;
                    }

                    $sql = 'UPDATE `' . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . '`'
                        . ' SET message=:message, sync_attempt = sync_attempt + 1, status = "sync_error" ';

                    $whereSql = array();
                    $bind = array(
                        'message' => $errorMessage
                    );

                    foreach ($errorData as $key => $errorItem) {
                        $whereSql[] = ' (object_id=:object_id'.$key.' AND sf_object_type=:sf_object_type'.$key.') ';
                        foreach ($errorItem as $field => $value) {
                            $bind[$field . $key] = $value;
                        }

                    }

                    $sql .= ' WHERE ' . implode(' OR ', $whereSql);

                    $this->_write->query(
                        $sql,
                        $bind
                    );

                    $session->unsTnwSalesforceErrors();
                }
            }
*/
            $sql = 'DELETE FROM `' . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . '` WHERE id IN (' . join(', ', $objectId) . ') AND status != "sync_error"';
            Mage::helper('tnw_salesforce')->log("SQL: " . $sql, 1, 'sf-cron');
            $this->_write->query($sql);

        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("ERROR quote from queue: " . $e->getMessage(), 1, 'sf-cron');
        }

        return true;
    }

    public function getObject($_objectId = NULL) {
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
        foreach ($idSet as $obId) {
            $insertData = array(
                'object_id' => $obId,
                'mage_object_type' => $this->_mageModels[$mageObType],
                'sf_object_type' => $sfObType,
                'date_created' => date("Y-m-d H:i:s"),
            );
            // check if record with id exists, then we update record
            $row = Mage::getModel('tnw_salesforce/queue_storage')->getCollection()
                ->addObjectidToFilter($obId)
                ->addSftypeToFilter($sfObType);
            if ($row->count() > 0) {
                $rowData = $row->getData();
                $insertData['id'] = $rowData[0]['id'];
            }
            // set data to model instance
            $m = Mage::getModel('tnw_salesforce/queue_storage')->setData($insertData);
            try {
                $getId = $m->save()->getId();
            } catch (Exception $e) {
                Mage::helper('tnw_salesforce')->log("error: object not saved in local storage");
                Mage::helper('tnw_salesforce')->log("mysql response: " . $e->getMessage());

                return false;
            }
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
        $idSetFiltered = array();
        $productsCollection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToFilter('entity_id', array('in' => $idSet))
            ->addAttributeToSelect('salesforce_disable_sync');

        foreach ($productsCollection as $_product) {
            //if ($_product->isSuper() || intval($_product->getData('salesforce_disable_sync')) == 1) {
            if (intval($_product->getData('salesforce_disable_sync')) == 1) {
                continue;
            }
            $idSetFiltered[] = $_product->getId();
        }

        return $this->addObject($idSetFiltered, $sfObType, $mageObType);
    }
}
