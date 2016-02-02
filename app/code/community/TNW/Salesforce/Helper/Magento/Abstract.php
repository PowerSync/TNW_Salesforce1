<?php

/**
 * Class TNW_Salesforce_Helper_Magento_Abstract
 */
abstract class TNW_Salesforce_Helper_Magento_Abstract
{
    /**
     * @var null
     */
    protected $_salesforceAssociation = array();

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
     * @return null
     */
    public function getSalesforceAssociationAndClean()
    {
        $_association = $this->_salesforceAssociation;
        $this->_salesforceAssociation = array();

        return $_association;
    }

    /**
     * @param null $_object
     * @return bool|false|Mage_Core_Model_Abstract
     */
    public function process($_object = null)
    {
        if (!$_object || !Mage::helper('tnw_salesforce')->isWorking()) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("No Salesforce object passed on connector is not working");
            return false;
        }

        $this->_response = new stdClass();
        $_type = $_object->attributes->type;
        unset($_object->attributes);
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("** " . $_type . " #" . $_object->Id . " **");
        $_entity = $this->syncFromSalesforce($_object);
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("** finished upserting " . $_type . " #" . $_object->Id . " **");

        // Handle success and fail
        if (is_object($_entity)) {
            $this->_salesforceAssociation[$_type][] = array(
                'salesforce_id' => $_entity->getData('salesforce_id'),
                'magento_id'    => $_entity->getId()
            );

            $this->_response->success = true;
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Salesforce " . $_type . " #" . $_object->Id . " upserted!");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Magento Id: " . $_entity->getId());
        } else {
            $this->_response->success = false;
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not upsert " . $_type . " into Magento, see Magento log for details");
            $_entity = false;
        }

        if (Mage::helper('tnw_salesforce')->isRemoteLogEnabled()) {
            /** @var TNW_Salesforce_Helper_Report $logger */
            $logger = Mage::helper('tnw_salesforce/report');
            $logger->reset();
            $logger->add('Magento', $_type, array($_object->Id => $_object), array($_object->Id => $this->_response));
            $logger->send();
        }

        return $_entity;
    }

    /**
     * @param null $object
     * @return mixed
     */
    abstract public function syncFromSalesforce($object = null);

    public static function sendMagentoIdToSalesforce($_association)
    {
        /** @var TNW_Salesforce_Model_Connection $_client */
        $_client = Mage::getSingleton('tnw_salesforce/connection');
        if (!$_client->initConnection()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR on sync entity, sf api connection failed");

            return;
        }

        foreach ($_association as $type => $item) {
            $sendData = array();

            $itemsToUpsert = array_chunk($item, TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);
            foreach ($itemsToUpsert as $_itemsToPush) {
                foreach ($_itemsToPush as $_item) {
                    if (empty($_item['salesforce_id'])) {
                        continue;
                    }

                    $prefix = ($type == TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT)
                        ? TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_FULFILMENT
                        : TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_ENTERPRISE;

                    $_obj = new stdClass();
                    $_obj->Id = $_item['salesforce_id'];
                    $_obj->{$prefix . 'Magento_ID__c'} = $_item['magento_id'];
                    $_obj->{$prefix . 'disableMagentoSync__c'} = true;

                    $sendData[] = $_obj;
                }

                try {
                    $_client->getClient()->upsert('Id', $sendData, $type);
                } catch (Exception $e) {}
            }
        }
    }

    /**
     * @param $_message
     * @param $_code
     */
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