<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Localstorage extends TNW_Salesforce_Helper_Abstract
{
    /**
     * @var array
     */
    protected $_mageModels = array();

    public function __construct()
    {
        $this->_mageModels['order'] = 'sales/order';
        $this->_mageModels['abandoned'] = 'sales/quote';
        $this->_mageModels['customer'] = 'customer/customer';
        $this->_mageModels['product'] = 'catalog/product';
        $this->_mageModels['website'] = 'core/website';
        $this->_mageModels['invoice'] = 'sales/order_invoice';
        $this->_mageModels['shipment'] = 'sales/order_shipment';
        $this->_mageModels['creditmemo'] = 'sales/order_creditmemo';
        $this->_mageModels['catalogrule'] = 'catalogrule/rule';
        $this->_mageModels['salesrule'] = 'salesrule/rule';
        $this->_mageModels['wishlist'] = 'wishlist/wishlist';
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

    public function getAllDependencies($types = array())
    {
        if (empty($types)) {
            $types = array('customer', 'product');
        }

        $mageModels = array();
        foreach ($types as $type) {
            if (empty($this->_mageModels[$type])) {
                continue;
            }

            $mageModels[] = $this->_mageModels[$type];
        }

        $_sql = sprintf("SELECT object_id, sf_object_type FROM %s WHERE status != 'success' AND mage_object_type IN ('%s') AND website_id = %s",
            Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage'),
            implode('\', \'', $mageModels),
            Mage::app()->getWebsite()->getId());

        $_results = $this->getDbConnection('read')->query($_sql)->fetchAll();

        $_dependencies = array();
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
            foreach ($_responses as $_entityKey => $_tmpResponse) {
                $__response = key_exists('subObj', $_tmpResponse)
                    ? $_tmpResponse['subObj'] : array($_tmpResponse);

                foreach ($__response as $_response) {
                    $_key = (!empty($_alternativeKeyes)) ? $_queueIds[array_search(array_search($_entityKey, $_alternativeKeyes), $_orderIds)] : $_queueIds[array_search($_entityKey, $_orderIds)];

                    /**
                     * @comment change variable type to avoid problems with stdClass
                     */
                    $_response = (array)$_response;

                    if (
                        array_key_exists('success', $_response)
                        && ((string)$_response['success'] === "false" || $_response['success'] === false)
                        && array_key_exists('errors', $_response)
                    ) {
                        $errors = (array)$_response['errors'];

                        $errorMessage = '';
                        if (isset($errors['message'])) {
                            $errorMessage = $errors['message'];
                        } elseif (is_array($errors)) {
                            foreach ($errors as $errorStdClass) {
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
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SQL: " . $_sql);
            $this->getDbConnection()->query($_sql);
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
     * @param string $message
     *
     * @return mixed
     */
    public function updateObjectStatusById($idSet = array(), $status = 'sync_running', $message = null)
    {
        if (empty($idSet)) {
            return false;
        }

        if (!is_array($idSet)) {
            $idSet = array($idSet);
        }

        $bind = [
            'status' => $status,
            'date_sync' => Mage::getModel('core/date')->date('c')
        ];

        switch ($status) {
            case 'sync_running':
                $bind['sync_attempt'] = new Zend_Db_Expr('sync_attempt + 1');
                break;

            case 'new':
                $bind['sync_attempt'] = '1';
                break;
        }

        if ($message !== null) {
            $bind['message'] = urlencode(serialize([$message]));
        }

        $connection = $this->getDbConnection();
        $tableName = Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage');
        return $connection->update($tableName, $bind, $connection->prepareSqlCondition('id', ['in' => $idSet]));
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
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Deletion Objects are empty!");
                return true;
            }

            $sql = "DELETE FROM `" . Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage') . "` WHERE id IN ('" . join("', '", $objectId) . "')";
            if (!$_isForced) {
                $sql .= " AND status = 'success'";
            }
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SQL: " . $sql);
            $this->getDbConnection('delete')->query($sql);

        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR quote from queue: " . $e->getMessage());
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
     * @param $modelType
     * @param $idSet
     * @return Varien_Db_Select
     */
    public function generateSelectForType($modelType, $idSet)
    {
        switch ($modelType) {
            case 'sales/order':
                /** @var Mage_Sales_Model_Resource_Order $resource */
                $resource   = Mage::getResourceModel('sales/order');
                $connection = $resource->getReadConnection();

                return $connection->select()
                    ->from(array('order'=>$resource->getMainTable()), array('object_id' => 'entity_id'))
                    ->joinLeft(array('store'=>$resource->getTable('core/store')), 'store.store_id = order.store_id', array('website_id'=>new Zend_Db_Expr('IFNULL(website_id, 0)')))
                    ->where($connection->prepareSqlCondition('order.entity_id', array('in'=>$idSet)))
                ;

            case 'sales/quote':
                /** @var Mage_Sales_Model_Resource_Order $resource */
                $resource   = Mage::getResourceModel('sales/quote');
                $connection = $resource->getReadConnection();

                return $connection->select()
                    ->from(array('quote'=>$resource->getMainTable()), array('object_id' => 'entity_id'))
                    ->joinLeft(array('store'=>$resource->getTable('core/store')), 'store.store_id = quote.store_id', array('website_id'=>new Zend_Db_Expr('IFNULL(website_id, 0)')))
                    ->where($connection->prepareSqlCondition('quote.entity_id', array('in'=>$idSet)))
                ;

            case 'customer/customer':
                /** @var Mage_Customer_Model_Resource_Customer $resource */
                $resource   = Mage::getResourceModel('customer/customer');
                $connection = $resource->getReadConnection();

                return $connection->select()
                    ->from(array('customer'=>$resource->getEntityTable()), array('object_id' => 'entity_id', 'website_id' => 'website_id'))
                    ->where($connection->prepareSqlCondition('customer.entity_id', array('in'=>$idSet)))
                ;

            case 'catalog/product':
                /** @var Mage_Catalog_Model_Resource_Product $resource */
                $resource   = Mage::getResourceModel('catalog/product');
                $connection = $resource->getReadConnection();

                $selectDiff = TNW_Salesforce_Helper_Config::generateSelectWebsiteDifferent();
                return $connection->select()
                    ->from(array('product'=>$resource->getEntityTable()), array('object_id' => 'entity_id'))
                    ->joinLeft(array('website'=>$resource->getTable('catalog/product_website')), 'product.entity_id = website.product_id', array('website_id'=>new Zend_Db_Expr('IFNULL(website_id, 0)')))
                    ->joinLeft(array('diff_websites'=>$selectDiff), 'diff_websites.scope_id = website.website_id', array())
                    ->group(array('diff_websites.scope_id', 'product.entity_id'))
                    ->where($connection->prepareSqlCondition('product.entity_id', array('in'=>$idSet)))
                ;

            case 'core/website':
                /** @var Mage_Core_Model_Resource_Website $resource */
                $resource   = Mage::getResourceModel('core/website');
                $connection = $resource->getReadConnection();

                return $connection->select()
                    ->from(array('website'=>$resource->getMainTable()), array('object_id' => 'website_id', 'website_id' => 'website_id'))
                    ->where($connection->prepareSqlCondition('website.website_id', array('in'=>$idSet)))
                ;

            case 'sales/order_invoice':
                /** @var Mage_Sales_Model_Resource_Order_Invoice $resource */
                $resource   = Mage::getResourceModel('sales/order_invoice');
                $connection = $resource->getReadConnection();

                return $connection->select()
                    ->from(array('invoice'=>$resource->getMainTable()), array('object_id' => 'entity_id'))
                    ->joinLeft(array('store'=>$resource->getTable('core/store')), 'store.store_id = invoice.store_id', array('website_id'=>new Zend_Db_Expr('IFNULL(website_id, 0)')))
                    ->where($connection->prepareSqlCondition('invoice.entity_id', array('in'=>$idSet)))
                ;

            case 'sales/order_shipment':
                /** @var Mage_Sales_Model_Resource_Order_Shipment $resource */
                $resource   = Mage::getResourceModel('sales/order_shipment');
                $connection = $resource->getReadConnection();

                return $connection->select()
                    ->from(array('shipment'=>$resource->getMainTable()), array('object_id' => 'entity_id'))
                    ->joinLeft(array('store'=>$resource->getTable('core/store')), 'store.store_id = shipment.store_id', array('website_id'=>new Zend_Db_Expr('IFNULL(website_id, 0)')))
                    ->where($connection->prepareSqlCondition('shipment.entity_id', array('in'=>$idSet)))
                ;

            case 'sales/order_creditmemo':
                /** @var Mage_Sales_Model_Resource_Order_Creditmemo $resource */
                $resource   = Mage::getResourceModel('sales/order_creditmemo');
                $connection = $resource->getReadConnection();

                return $connection->select()
                    ->from(array('creditmemo'=>$resource->getMainTable()), array('object_id' => 'entity_id'))
                    ->joinLeft(array('store'=>$resource->getTable('core/store')), 'store.store_id = creditmemo.store_id', array('website_id'=>new Zend_Db_Expr('IFNULL(website_id, 0)')))
                    ->where($connection->prepareSqlCondition('creditmemo.entity_id', array('in'=>$idSet)))
                ;

            case 'catalogrule/rule':
                /** @var Mage_CatalogRule_Model_Resource_Rule $resource */
                $resource   = Mage::getResourceModel('catalogrule/rule');
                $connection = $resource->getReadConnection();

                return $connection->select()
                    ->from(array('rule'=>$resource->getMainTable()), array('object_id' => 'rule_id'))
                    ->joinLeft(array('website'=>$resource->getTable('catalogrule/website')), 'rule.rule_id = website.rule_id', array('website_id'=>new Zend_Db_Expr('IFNULL(website_id, 0)')))
                    ->group(array('rule.rule_id'))
                    ->where($connection->prepareSqlCondition('rule.rule_id', array('in'=>$idSet)))
                ;

            case 'salesrule/rule':
                /** @var Mage_SalesRule_Model_Resource_Rule $resource */
                $resource   = Mage::getResourceModel('salesrule/rule');
                $connection = $resource->getReadConnection();

                return $connection->select()
                    ->from(array('rule'=>$resource->getMainTable()), array('object_id' => 'rule_id'))
                    ->joinLeft(array('website'=>$resource->getTable('salesrule/website')), 'rule.rule_id = website.rule_id', array('website_id'=>new Zend_Db_Expr('IFNULL(website_id, 0)')))
                    ->group(array('rule.rule_id'))
                    ->where($connection->prepareSqlCondition('rule.rule_id', array('in'=>$idSet)))
                ;

            case 'wishlist/wishlist':
                /** @var Mage_Wishlist_Model_Resource_Wishlist $resource */
                $resource   = Mage::getResourceModel('wishlist/wishlist');
                $connection = $resource->getReadConnection();

                return $connection->select()
                    ->from(array('wishlist'=>$resource->getMainTable()), array('object_id' => 'wishlist_id'))
                    ->joinInner(array('customer'=>$resource->getTable('customer/entity')), 'customer.entity_id = wishlist.customer_id', array('website_id'=>new Zend_Db_Expr('IFNULL(customer.website_id, 0)')))
                    ->where($connection->prepareSqlCondition('wishlist.wishlist_id', array('in'=>$idSet)))
                ;

            default:
                /**
                 * @var $entityModel Mage_Core_Model_Abstract
                 */
                $entityModel = Mage::getModel($modelType);

                /**
                 * @var $collection Mage_Sales_Model_Resource_Order_Collection|Mage_Catalog_Model_Resource_Product_Collection
                 */
                $collection = $entityModel->getCollection();
                $collection->addFieldToFilter($entityModel->getIdFieldName(), array('in'=>$idSet));

                return $collection->getSelect()
                    ->reset(Zend_Db_Select::COLUMNS)
                    ->columns(array(
                        'object_id'  => $entityModel->getIdFieldName(),
                        'website_id' => new Zend_Db_Expr('"0"'),
                    ));
        }
    }

    /**
     * @param $modelType
     * @param $entityId
     * @return int
     */
    public function getWebsiteIdForType($modelType, $entityId)
    {
        $select = $this->generateSelectForType($modelType, array($entityId));
        $row = $select->getAdapter()->fetchRow($select);
        if (empty($row)) {
            return null;
        }

        return $row['website_id'];
    }

    /**
     * insert / update object in table for future sf synchronization
     *
     * @param array $idSet
     * @param $sfObType
     * @param $mageObType
     * @param bool $syncBulk
     * @return bool
     */
    public function addObject(array $idSet = array(), $sfObType, $mageObType, $syncBulk = false)
    {
        $entityModelAlias = $this->getMageModels($mageObType);

        $syncType = $syncBulk
            ? TNW_Salesforce_Model_Cron::SYNC_TYPE_BULK
            : TNW_Salesforce_Model_Cron::SYNC_TYPE_OUTGOING;

        foreach (array_chunk($idSet, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT) as $_chunk) {
            $select = $this->generateSelectForType($entityModelAlias, $_chunk);
            $select->columns(array(
                'mage_object_type'  => new Zend_Db_Expr('"' . $entityModelAlias .'"'),
                'sf_object_type'    => new Zend_Db_Expr('"' . $sfObType . '"'),
                'date_created'      => new Zend_Db_Expr('"' . Mage::helper('tnw_salesforce')->getDate() . '"'),
                'sync_type'         => new Zend_Db_Expr('"' . $syncType . '"'),
                'status'            => new Zend_Db_Expr('"new"'),
                'sync_attempt'      => new Zend_Db_Expr('0'),
            ));

            try {
                $recordsToInsert = Mage::helper('tnw_salesforce')->getDbConnection('read')->fetchAll($select);

                Mage::helper('tnw_salesforce')->getDbConnection('write')->insertOnDuplicate(
                    Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_queue_storage'),
                    $recordsToInsert,
                    array('object_id', 'website_id', 'mage_object_type', 'sf_object_type', 'date_created', 'sync_type', 'status', 'sync_attempt')
                );
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError("ERROR add object from queue: " . $e->getMessage());

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
     * @param bool $syncBulk
     * @return bool
     */
    public function addObjectProduct(array $idSet = array(), $sfObType, $mageObType, $syncBulk = false)
    {
        /** @var Mage_Catalog_Model_Resource_Product $productsResource */
        $productsResource = Mage::getResourceModel('catalog/product');
        $attribute = $productsResource->getAttribute('salesforce_disable_sync');

        $connect = $productsResource->getReadConnection();
        $select = $connect->select()
            ->from(array('product'=>$productsResource->getEntityTable()), 'entity_id')
            ->joinLeft(array('disable_sync'=>$attribute->getBackendTable()), "product.entity_id = disable_sync.entity_id AND disable_sync.attribute_id = {$attribute->getId()}", array())
            ->where($connect->prepareSqlCondition('disable_sync.value', array(
                array('neq' => '1'),
                array('null' => true),
            )))
            ->where($connect->prepareSqlCondition('product.entity_id', array('in'=>$idSet)))
        ;

        $idSetFiltered = $connect->fetchCol($select);
        return $this->addObject($idSetFiltered, $sfObType, $mageObType, $syncBulk);
    }
}
