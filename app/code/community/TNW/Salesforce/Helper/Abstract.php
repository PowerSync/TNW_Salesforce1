<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Abstract extends Mage_Core_Helper_Abstract
{
    const MIN_LEN_SF_ID = 15;

    /**
     * @var null
     */
    protected $_mageCache = NULL;

    /**
     * @var bool
     */
    protected $_useCache = false;

    /**
     * Get DB writer
     * @var null
     */
    protected $_write = NULL;

    /**
     * Get DB reader
     * @var null
     */
    protected $_read = NULL;

    /**
     * Get DB record remover
     * @var null
     */
    protected $_delete = NULL;

    /**
     * @var array
     */
    protected $_attributes = array();

    /**
     * @var null
     */
    protected $_customerEntityTypeCode = NULL;

    /**
     * this data intended to be used by third parties applications
     *
     * @var array
     */
    protected static $_mageObjectType = array(
        'order'     => 'sales/order',
        'customer'  => 'customer/customer',
        'product'   => 'catalog/product',
        'quote'     => 'qquoteadv/qqadvcustomer',
        'website'   => 'core/website'
    );

    /**
     * sf object type list
     * example: alias (quote) => corresponds to word in sf (Quote)
     *
     * @var array
     */
    protected static $sfObjectType = array(
        'opportunity' => 'Opportunity',
        'quote' => 'Quote',
        'quoteItem' => 'QuoteLineItem',
        'opportunityItem' => 'OpportunityLineItem',
        'product2' => 'Product2',
    );

    /**
     * sf connection entity
     *
     * @var Salesforce_SforceEnterpriseClient
     */
    protected $_mySforceConnection = false;

    /**
     * @var array
     */
    protected $_cache = array();

    /**
     * init sf connection
     * the method duplicated here from childs
     * as well methods checkConnection() left in childs for old code compatibility
     */
    protected function checkConnection()
    {
        try {
            $this->_mySforceConnection = Mage::helper('tnw_salesforce/salesforce_data')->getClient();
            if (!$this->_mySforceConnection) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("error: salesforce connection failed");
                return;
            }
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("error: could not get salesforce connection");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("error info:" . $e->getMessage());
            return;
        }
    }

    /**
     * @return Salesforce_SforceEnterpriseClient
     */
    public function getClient()
    {
        return Mage::getSingleton('tnw_salesforce/connection')->getClient();
    }

    protected function _processErrors($_response, $type = 'order', $_object = null)
    {
        if (is_array($_response->errors)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Failed to upsert ' . $type . '!');
            foreach ($_response->errors as $_error) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $_error->message);
            }
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('CRITICAL ERROR: Failed to upsert ' . $type . ': ' . $_response->errors->message);
        }
    }

    public function clearMemory() {
        if (!gc_enabled()) {
            gc_enable();
        }
        gc_collect_cycles();
        gc_disable();
    }

    /**
     * delete object in sf
     *
     * @param bool $id
     * @return mixed
     */
    protected function deleteSfObjectById($id = false)
    {
        return $this->_mySforceConnection->delete(array($id));
    }

    /**
     * get table name with prefix
     *
     * @param null $name
     * @return bool
     */
    public function getTable($name = NULL)
    {
        if (!$name) {
            return false;
        }
        return Mage::getSingleton('core/resource')->getTableName($name);
    }

    /**
     * date formatting to salesforce standards
     *
     * @param $date
     * @param null $format
     * @param bool $f
     * @return string
     */
    public function formatDate($date, $format = null, $f = false)
    {
        if ($format == null) {
            $format = $this->getDefaultDateFormat();
        }
        $length = strlen($format);
        $buffer = '';

        for ($i = 0; $i < $length; $i++) {
            $buffer .= $this->__(date($format[$i], mktime(substr($date, 11, 2), substr($date, 14, 2), substr($date, 17, 2), substr($date, 5, 2), substr($date, 8, 2), substr($date, 0, 4))));;
        }
        unset($format, $length);
        return $buffer;
    }

    /**
     * time formatting to salesforce standards
     *
     * @param $time
     * @param null $format
     * @return string
     */
    public function formatTime($time, $format = null)
    {
        if ($format == null) {
            $format = $this->getDefaultTimeFormat();
        }

        return $this->formatDate($time, $format);
    }

    /**
     * logs errors for salesforce
     *
     * @param $message
     * @param null $level
     * @param string $file
     *
     * @deprecated
     * @use TNW_Salesforce_Model_Tool_Log and TNW_Salesforce_Model_Tool_Log_File model instead
     */
    public function log($message, $level = null, $file = 'sf-trace')
    {
        $_folder = "";
        if (defined('DS')) {
            $logDir = Mage::getBaseDir('var') . DS . 'log' . DS . 'salesforce';
            if (!is_dir($logDir)) {
                mkdir($logDir,  0777, true);
            }
            $_folder = 'salesforce' . DS;
        }
        $file = $_folder . $file . '-' . $this->getWebsiteId() . '-' . $this->getStoreId() . '.log';

        if ($this->isLoggingEnabled()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($message);
        }
    }

    /**
     * dump object into log
     *
     * @param null $object
     * @param int $level
     */
    public function dump($object = NULL, $level = 0)
    {
        if (!$object) {
            return;
        }
        $indent = "";
        for ($i = 0; $i < $level; $i++) {
            $indent .= "-";
        }
        foreach ($object as $key => $value) {
            if (is_object($value) || is_array($value)) {
                $level++;
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($indent . " " . $key . ":");
                $this->dump($value, $level);
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($indent . " " . $key . ": " . $value);
            }
        }
        unset($indent, $key, $value, $object, $level);
    }

    /**
     * returns true if logging is enabled
     *
     * @return mixed
     */
    public function isLoggingEnabled()
    {
        return Mage::helper('tnw_salesforce')->isLogEnabled();
    }

    /**
     * returns the relevant wordpress helper
     * if no parameter passed, returns the default (data.php) helper
     *
     * @param null $helper
     * @return Mage_Core_Helper_Abstract
     */
    public function _helper($helper = null)
    {
        return Mage::helper('tnw_salesforce' . (($helper != null) ? "/{$helper}" : null));
    }

    /**
     * Shortcut to get Param
     *
     * @param string $field
     * @param null|mixed $default
     * @return mixed
     */
    public function getParam($field, $default = null)
    {
        return Mage::app()->getRequest()->getParam($field, $default);
    }

    /**
     * @deprecated mistake
     * @param $path
     * @return mixed
     */
    protected function getStroreConfig($path)
    {
        return $this->getStoreConfig($path);
    }

    /**
     * @param $path
     * @return mixed|null|string
     */
    protected function getStoreConfig($path, $_currentStoreId = null, $_currentWebsite = null)
    {
        if (!$_currentWebsite) {
            $_currentWebsite = Mage::app()->getStore($_currentStoreId)->getWebsiteId();
        }

        if (!$_currentStoreId) {
            $_currentStoreId = Mage::app()->getWebsite($_currentWebsite)->getDefaultStore()->getId();
        }

        if ($_currentWebsite == 0 && $_currentStoreId == 0) {
            if ($this->getStoreId()) {
                return Mage::getStoreConfig($path, $this->getStoreId());
            }
            if ($this->getWebsiteId()) {
                return Mage::app()->getWebsite($this->getWebsiteId())->getConfig($path);
            }
        }

        $availableStoreIds = Mage::app()->getWebsite($_currentWebsite)->getStoreIds();
        if (!in_array($_currentStoreId, $availableStoreIds)) {
            $_currentStoreId = Mage::app()->getWebsite($_currentWebsite)->getDefaultStore()->getId();
        }

        return Mage::getStoreConfig($path, $_currentStoreId);
    }

    /**
     * @param $path
     * @param null $_currentStoreId
     * @return null|string
     */
    protected function _getPresetConfig($path, $_currentStoreId = NULL)
    {
        if ($_currentStoreId === NULL) {
            $_currentStoreId = Mage::app()->getStore()->getStoreId();
        }

        return Mage::app()->getStore($_currentStoreId)->getConfig($path);
    }

    /**
     * @return int
     */
    public function getStoreId()
    {
        $store = null;
        if ($storeId = Mage::app()->getRequest()->getParam('store')) {
            if ($storeId == 'undefined') {
                $storeId = 0;
            }
            if (!is_array($storeId)) {
                $store = Mage::app()->getStore($storeId);
            }
        }
        if (!$store) {
            $store = Mage::app()->getStore();
        }

        return (int)$store->getId();
    }

    /**
     * @return int
     */
    public function getWebsiteId()
    {
        $website = null;
        if ($websiteId = Mage::app()->getRequest()->getParam('website')) {
            if (!is_array($websiteId)) {
                $website = Mage::app()->getWebsite($websiteId);
            }
        }
        if (!$website) {
            $website = Mage::app()->getWebsite();
        }

        return (int)$website->getId();
    }

    public function _initCache()
    {
        $this->_mageCache = Mage::app()->getCache();
        $this->_useCache = Mage::app()->useCache('tnw_salesforce');
    }

    /**
     * @return Zend_Cache_Core
     */
    public function getCache()
    {
        if (is_null($this->_mageCache)) {
            $this->_initCache();
        }

        return $this->_mageCache;
    }

    /**
     * @return bool
     */
    public function useCache()
    {
        if (is_null($this->_mageCache)) {
            $this->_initCache();
        }

        return $this->_useCache;
    }

    protected function _reset()
    {
        $this->_initCache();

        if (!$this->_mySforceConnection) {
            $this->checkConnection();
        }

        $sql = "SELECT * FROM `" . $this->getTable('eav_entity_type') . "` WHERE entity_type_code = 'customer'";
        $row = $this->getDbConnection('read')->query($sql)->fetch();
        $this->_customerEntityTypeCode = ($row) ? (int)$row['entity_type_id'] : NULL;

        if (empty($this->_attributes)) {
            $resource = Mage::getResourceModel('eav/entity_attribute');
            // Customer Attributes
            $this->_attributes['customer'] = array();
            $this->_attributes['customer']['salesforce_id'] = $resource->getIdByCode('customer', 'salesforce_id');
            $this->_attributes['customer']['salesforce_account_id'] = $resource->getIdByCode('customer', 'salesforce_account_id');
            $this->_attributes['customer']['salesforce_lead_id'] = $resource->getIdByCode('customer', 'salesforce_lead_id');
            $this->_attributes['customer']['salesforce_is_person'] = $resource->getIdByCode('customer', 'salesforce_is_person');
            $this->_attributes['customer']['sf_insync'] = $resource->getIdByCode('customer', 'sf_insync');
        }
    }

    /**
     * @param null $_id
     * @param int $_value
     * @param null $_attributeName
     * @param string $_type
     * @param string $_tableName
     * @return bool
     */
    public function updateMagentoEntityValue($_id = NULL, $_value = 0, $_attributeName = NULL, $_type = 'customer', $_tableName = '_entity_varchar')
    {
        $_table = $this->getTable($_type . $_tableName);

        if (!$_attributeName) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Could not update Magento ' . $_type . ' values: attribute name is not specified');
            return false;
        }
        $sql = '';
        if ($_value || $_value === 0) {
            // Update Account Id
            $sqlCheck = "SELECT value_id FROM `" . $_table . "` WHERE attribute_id = " . $this->_attributes[$_type][$_attributeName] . " AND entity_id = " . $_id;
            $row = $this->getDbConnection('read')->query($sqlCheck)->fetch();
            if ($row && array_key_exists('value_id', $row)) {
                //Update
                $sql .= "UPDATE `" . $_table . "` SET value = '" . $_value . "' WHERE value_id = " . $row['value_id'] . ";";
            } else {
                // Insert
                $sql .= "INSERT INTO `" . $_table . "` VALUES (NULL," . $this->_customerEntityTypeCode . "," . $this->_attributes[$_type][$_attributeName] . "," . $_id . ",'" . $_value . "');";
            }
        } else {
            // Reset value
            $sqlCheck = "SELECT value_id FROM `" . $_table . "` WHERE attribute_id = " . $this->_attributes[$_type][$_attributeName] . " AND entity_id = " . $_id;
            $row = $this->getDbConnection('read')->query($sqlCheck)->fetch();
            if ($row && array_key_exists('value_id', $row)) {
                //Update
                $sql .= "DELETE FROM `" . $_table . "` WHERE value_id = " . $row['value_id'] . ";";
            }
        }
        if (!empty($sql)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("SQL: " . $sql);
            $this->getDbConnection()->query($sql . ' commit;');
        }
    }

    /**
     * @param string $_type
     * @return Varien_Db_Adapter_Interface
     */
    public function getDbConnection($_type = 'write') {
        $_function = '_getDb' . ucwords($_type);
        return $this->{$_function}();
    }

    protected function _getDbWrite() {
        if (!$this->_write) {
            $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        }
        return $this->_write;
    }

    protected function _getDbRead() {
        if (!$this->_read) {
            $this->_read = Mage::getSingleton('core/resource')->getConnection('core_read');
        }
        return $this->_read;
    }

    protected function _getDbDelete() {
        if (!$this->_delete) {
            $this->_delete = Mage::getSingleton('core/resource')->getConnection('core_delete');
        }
        return $this->_delete;
    }

    public function numberFormat($value)
    {
        /** @var TNW_Salesforce_Helper_Config_Product $helper */
        $helper = $this->_helper('config_product');
        return number_format($value, $helper->getPriceAccuracy(), ".", "");
    }

    /**
     * remove some technical data from Id, in fact first 15 symbols important only, last 3 - it's technical data for SF
     * @param $id
     * @return string
     */
    public function prepareId($id)
    {
        return substr($id, 0, self::MIN_LEN_SF_ID);
    }
}