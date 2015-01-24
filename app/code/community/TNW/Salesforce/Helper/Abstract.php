<?php

/**
 * Class TNW_Salesforce_Helper_Abstract
 */
class TNW_Salesforce_Helper_Abstract extends Mage_Core_Helper_Abstract
{
    /**
     * @var null
     */
    protected $_mageCache = NULL;

    /**
     * @var bool
     */
    protected $_useCache = false;

    /**
     * @var null
     */
    protected $_write = NULL;

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
     * @var bool
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
                Mage::helper('tnw_salesforce')->log("error: salesforce connection failed");
                return;
            }
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("error: could not get salesforce connection");
            Mage::helper('tnw_salesforce')->log("error info:" . $e->getMessage());
            return;
        }
    }

    protected function _processErrors($_response, $type = 'order')
    {
        if (is_array($_response->errors)) {
            Mage::helper('tnw_salesforce')->log('Failed to upsert ' . $type . '!');
            foreach ($_response->errors as $_error) {
                Mage::helper('tnw_salesforce')->log("ERROR: " . $_error->message);
            }
        } else {
            Mage::helper('tnw_salesforce')->log('CRITICAL ERROR: Failed to upsert ' . $type . ': ' . $_response->errors->message);
        }
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
            if ($message == trim($message)) {
                return Mage::log($message, $level, $file);
            }
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
                $this->log($indent . " " . $key . ":");
                $this->dump($value, $level);
            } else {
                $this->log($indent . " " . $key . ": " . $value);
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
     * @param $path
     * @return mixed|null|string
     */
    protected function getStroreConfig($path)
    {
        $_currentWebsite = Mage::app()->getStore()->getWebsiteId();
        $_currentStoreId = Mage::app()->getStore()->getStoreId();
        if ($_currentWebsite == 0 && $_currentStoreId == 0) {
            if (Mage::app()->getRequest()->getParam('store')) {
                $_currentStoreId = Mage::app()->getRequest()->getParam('store');
                if (is_object(Mage::app()->getStore($_currentStoreId))) {
                    return Mage::app()->getStore($_currentStoreId)->getConfig($path);
                }

            }
            if (Mage::app()->getRequest()->getParam('website')) {
                $_currentWebsite = Mage::app()->getRequest()->getParam('website');
                if (is_object(Mage::app()->getWebsite($_currentWebsite))) {
                    return Mage::app()->getWebsite($_currentWebsite)->getConfig($path);
                }
            }
            return Mage::getStoreConfig($path);
        }

        return Mage::app()->getStore($_currentStoreId)->getConfig($path);
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
        return (Mage::app()->getRequest()->getParam('store')) ? (int)Mage::app()->getRequest()->getParam('store') : (int)Mage::app()->getStore()->getStoreId();
    }

    /**
     * @return int
     */
    public function getWebsiteId()
    {
        return (Mage::app()->getRequest()->getParam('website')) ? (int)Mage::app()->getRequest()->getParam('website') : (int)Mage::app()->getStore()->getWebsiteId();
    }

    public function _initCache()
    {
        $this->_mageCache = Mage::app()->getCache();
        $this->_useCache = Mage::app()->useCache('tnw_salesforce');
    }

    protected function _reset()
    {
        $this->_initCache();

        if (!$this->_mySforceConnection) {
            $this->checkConnection();
        }

        $this->_prefix = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix();

        if (!$this->_write) {
            $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        }

        $sql = "SELECT * FROM `" . $this->getTable('eav_entity_type') . "` WHERE entity_type_code = 'customer'";
        $row = $this->_write->query($sql)->fetch();
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
            Mage::helper('tnw_salesforce')->log('Could not update Magento ' . $_type . ' values: attribute name is not specified', 1, "sf-errors");
            return false;
        }
        $sql = '';
        if ($_value || $_value === 0) {
            // Update Account Id
            $sqlCheck = "SELECT value_id FROM `" . $_table . "` WHERE attribute_id = " . $this->_attributes[$_type][$_attributeName] . " AND entity_id = " . $_id;
            $row = $this->_write->query($sqlCheck)->fetch();
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
            $row = $this->_write->query($sqlCheck)->fetch();
            if ($row && array_key_exists('value_id', $row)) {
                //Update
                $sql .= "DELETE FROM `" . $_table . "` WHERE value_id = " . $row['value_id'] . ";";
            }
        }
        if (!empty($sql)) {
            Mage::helper('tnw_salesforce')->log("SQL: " . $sql, 1, 'sf-cron');
            $this->_write->query($sql . ' commit;');
        }
    }
}