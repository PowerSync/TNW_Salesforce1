<?php

/**
 * Class TNW_Salesforce_Helper_Magento_Abstract
 */
class TNW_Salesforce_Helper_Magento_Abstract {

    /**
     * @var null
     */
    protected $_write = null;

    /**
     * @var null
     */
    protected $_response = NULL;

    /**
     * @var null
     */
    protected $_prefix = NULL;

    /**
     * @var null
     */
    protected $_magentoIdField = NULL;

    /**
     * @var array
     */
    protected $_websiteSfIds = array();

    /* Core functions */
    protected $_time = NULL;

    /**
     * @param null $_object
     * @return bool|false|Mage_Core_Model_Abstract
     */
    public function process($_object = null)
    {
        if (
            !$_object
            || !Mage::helper('tnw_salesforce')->isWorking()
        ) {
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("No Salesforce object passed on connector is not working");

            return false;
        }
        $this->_response = new stdClass();
        $_type = $_object->attributes->type;
        unset($_object->attributes);
        Mage::getModel('tnw_salesforce/tool_log')->saveTrace("** " . $_type . " #" . $_object->Id . " **");
        $_entity = $this->syncFromSalesforce($_object);
        Mage::getModel('tnw_salesforce/tool_log')->saveTrace("** finished upserting " . $_type . " #" . $_object->Id . " **");

        // Handle success and fail
        if (is_object($_entity)) {
            $this->_response->success = true;
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Salesforce " . $_type . " #" . $_object->Id . " upserted!");
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Magento Id: " . $_entity->getId());
        } else {
            $this->_response->success = false;
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Could not upsert " . $_type . " into Magento, see Magento log for details");
            $_entity = false;
        }

        if (Mage::helper('tnw_salesforce')->isRemoteLogEnabled()) {
            $logger = Mage::helper('tnw_salesforce/report');
            $logger->reset();

            $logger->add('Magento', 'Product', array($_object->Id => $_object), array($_object->Id => $this->_response));

            $logger->send();
        }

        return $_entity;
    }

    public function __destruct()
    {
        foreach ($this as $index => $value) unset($this->$index);
    }

    protected function _addError($_message, $_code) {
        if (!property_exists($this->_response, 'errors')) {
            $this->_response->errors = array();
        }
        $_error = new stdClass();
        $_error->message = $_message;
        $_error->statusCode = $_code;

        $this->_response->errors[] = $_error;
    }

    public function __construct()
    {
        $this->_setTime();
    }

    protected function _prepare() {
        if (!$this->_write) {
            $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        }

        // Get Website Salesforce Id's
        $website = Mage::getModel('core/website')->load(0);
        $this->_websiteSfIds[0] = $this->getWebsiteSfId($website);
        foreach (Mage::app()->getWebsites() as $website) {
            $this->_websiteSfIds[$website->getData('website_id')] = $this->getWebsiteSfId($website);
        }

        if (!$this->_magentoIdField) {
            $this->_magentoIdField = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
        }
    }

    public function getWebsiteSfId($website){
        $sfId = $website->getData('salesforce_id');
        if (empty($sfId)){
            $manualSync = Mage::helper('tnw_salesforce/salesforce_website');
            $manualSync->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
            $manualSync->setSalesforceSessionId(Mage::getSingleton('core/session')->getSalesforceSessionId());

            if ($manualSync->reset()) {
                $manualSync->massAdd(array($website->getData('website_id')));
                $manualSync->process();
                $newWebsite = Mage::getModel('core/website')->load($website->getData('website_id'));
                $sfId = $newWebsite->getData('salesforce_id');
            } else {
                if (Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('Salesforce connection could not be established!');
                }
            }
        }
        $sfId = Mage::helper('tnw_salesforce')->prepareId($sfId);
        return $sfId;
    }

    protected function _getTime() {
        if (!$this->_time) {
            $this->_setTime();
        }
        return $this->_time;
    }

    protected function _setTime() {
        $this->_time = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(time()));
    }
}